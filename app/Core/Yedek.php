<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Ayar;
use Generator;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Yedekleme ve geri yükleme motoru (saf PHP; mysqldump, zip eklentisi veya SSH gerektirmez).
 *
 *  - SQL yedeği  : portfolyo-YYYYmmdd-HHMMSS.sql.gz   (veritabanı)
 *  - Medya yedeği: medya-YYYYmmdd-HHMMSS.tar.gz       (yüklenen görsel/video; PharData ile)
 *
 * Geri yükleme GÜVENLİĞİ: dosyadaki SQL körü körüne çalıştırılmaz. Önce tamamı "kuru çalıştırma" ile doğrulanır:
 * yalnızca bu uygulamanın kendi tablolarına ait SET / DROP TABLE / CREATE TABLE / INSERT ... VALUES ifadeleri kabul edilir,
 * INSERT değerleri yalnızca sabit değerlerden (sayı, metin, NULL, 0x...) oluşabilir. Geri yüklemeden önce otomatik yedek alınır;
 * işlem yarıda kesilirse bu yedekten eski duruma dönülür.
 */
final class Yedek
{
    /** Verisi yedeklenmeyen (geçici/oturum) tablolar: yalnızca yapıları yazılır. */
    private const VERISIZ = ['giris_denemeleri', 'oturumlar', 'sifre_sifirlama'];
    private const AD_DESENI = '/^[A-Za-z0-9][A-Za-z0-9._-]{4,119}$/';

    public static function dizin(): string
    {
        $d = (string) config('yedek.dizin', KOK . '/storage/yedekler');
        if (!is_dir($d) && !@mkdir($d, 0700, true) && !is_dir($d)) {
            throw new RuntimeException('Yedek klasörü oluşturulamadı: ' . $d);
        }
        return $d;
    }

    // ------------------------------------------------------------------ listeleme

    /** @return list<array{ad:string,tur:string,boyut:int,zaman:int,geri_yuklenebilir:bool}> en yeniden eskiye */
    public static function liste(): array
    {
        $liste = [];
        foreach (scandir(self::dizin()) ?: [] as $ad) {
            $tur = self::turu($ad);
            if ($tur === null) {
                continue;
            }
            $yol = self::dizin() . '/' . $ad;
            $liste[] = ['ad' => $ad, 'tur' => $tur, 'boyut' => (int) filesize($yol), 'zaman' => (int) filemtime($yol),
                'geri_yuklenebilir' => in_array($tur, ['sql', 'medya'], true)];
        }
        usort($liste, static fn(array $a, array $b): int => $b['zaman'] <=> $a['zaman'] ?: strcmp($b['ad'], $a['ad']));
        return $liste;
    }

    private static function turu(string $ad): ?string
    {
        if (preg_match(self::AD_DESENI, $ad) !== 1) {
            return null;
        }
        if (preg_match('/\.sql(\.gz)?$/', $ad) === 1) {
            return 'sql';
        }
        if (preg_match('/^medya-.*\.tar\.gz$/', $ad) === 1) {
            return 'medya';
        }
        if (preg_match('/^yuklemeler-.*\.zip$/', $ad) === 1) {
            return 'eski_zip';
        }
        return null;
    }

    /** Geçerli ve mevcut yedek dosyasının tam yolu; aksi halde null (yol gezdirme engellenir). */
    public static function yol(string $ad): ?string
    {
        if (self::turu($ad) === null) {
            return null;
        }
        $yol = self::dizin() . '/' . $ad;
        return is_file($yol) ? $yol : null;
    }

    public static function sil(string $ad): bool
    {
        $y = self::yol($ad);
        return $y !== null && @unlink($y);
    }

    public static function sonSqlZamani(): int
    {
        $son = 0;
        foreach (self::liste() as $y) {
            if ($y['tur'] === 'sql' && str_starts_with($y['ad'], 'portfolyo-')) {
                $son = max($son, $y['zaman']);
            }
        }
        return $son;
    }

    // ------------------------------------------------------------------ yedek alma

    /** Veritabanı yedeği alır. @return string oluşturulan dosyanın tam yolu */
    public static function sqlAl(string $onek = 'portfolyo'): string
    {
        $pdo = Veritabani::baglanti();
        $yol = self::dizin() . '/' . $onek . '-' . date('Ymd-His') . '.sql.gz';
        for ($i = 1; is_file($yol); $i++) {
            $yol = self::dizin() . '/' . $onek . '-' . date('Ymd-His') . '-' . $i . '.sql.gz';
        }
        $gz = function_exists('gzopen');
        if (!$gz) {
            $yol = substr($yol, 0, -3);
        }
        $f = $gz ? gzopen($yol, 'wb6') : fopen($yol, 'wb');
        if ($f === false) {
            throw new RuntimeException('Yedek dosyası yazılamadı.');
        }
        $w = static function (string $s) use ($f, $gz): void {
            $gz ? gzwrite($f, $s) : fwrite($f, $s);
        };
        try {
            $w('-- Portfolyo yedeği · ' . gmdate('Y-m-d H:i:s') . " UTC\n-- sema_surumu: " . Kurulum::SEMA_SURUMU
                . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET time_zone='+00:00';\n\n");
            $tablolar = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tablolar as $tablo) {
                $tablo = (string) $tablo;
                if (preg_match('/^[A-Za-z0-9_]+$/', $tablo) !== 1) {
                    continue;
                }
                $t = '`' . $tablo . '`';
                $olustur = $pdo->query('SHOW CREATE TABLE ' . $t)->fetch(PDO::FETCH_NUM);
                $w("DROP TABLE IF EXISTS {$t};\n" . $olustur[1] . ";\n\n");
                if (in_array($tablo, self::VERISIZ, true)) {
                    continue;
                }
                $st = $pdo->query('SELECT * FROM ' . $t);
                $blok = [];
                while (($satir = $st->fetch(PDO::FETCH_NUM)) !== false) {
                    $degerler = [];
                    foreach ($satir as $d) {
                        if ($d === null) {
                            $degerler[] = 'NULL';
                        } elseif (is_int($d) || is_float($d)) {
                            $degerler[] = (string) $d;
                        } else {
                            $d = (string) $d;
                            // UTF-8 olmayan (ikili) değerler hex olarak yazılır (örn. VARBINARY IP adresleri).
                            $degerler[] = mb_check_encoding($d, 'UTF-8') && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $d) !== 1
                                ? $pdo->quote($d)
                                : ($d === '' ? "''" : '0x' . bin2hex($d));
                        }
                    }
                    $blok[] = '(' . implode(',', $degerler) . ')';
                    if (count($blok) >= 200) {
                        $w("INSERT INTO {$t} VALUES\n" . implode(",\n", $blok) . ";\n");
                        $blok = [];
                    }
                }
                if ($blok !== []) {
                    $w("INSERT INTO {$t} VALUES\n" . implode(",\n", $blok) . ";\n");
                }
                $w("\n");
            }
            $w("SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            $gz ? gzclose($f) : fclose($f);
        }
        @chmod($yol, 0600);
        return $yol;
    }

    /** Yüklenen medyayı .tar.gz olarak yedekler. @return string|null dosya yolu; medya yoksa null */
    public static function medyaAl(): ?string
    {
        if (!class_exists(\PharData::class) || !\Phar::canCompress(\Phar::GZ)) {
            throw new RuntimeException('Medya yedeği için PHP phar ve zlib eklentileri gerekir.');
        }
        $kaynak = (string) config('yukleme.dizin');
        $dosyalar = [];
        foreach (scandir($kaynak) ?: [] as $d) {
            if (Yukleme::gecerliAd($d) && is_file($kaynak . '/' . $d)) {
                $dosyalar[] = $d;
            }
        }
        if ($dosyalar === []) {
            return null;
        }
        $taban = self::dizin() . '/medya-' . date('Ymd-His');
        $tar = $taban . '.tar';
        $arsiv = new \PharData($tar);
        foreach ($dosyalar as $d) {
            $arsiv->addFile($kaynak . '/' . $d, $d);
        }
        $arsiv->compress(\Phar::GZ);
        unset($arsiv);
        @unlink($tar);
        @chmod($taban . '.tar.gz', 0600);
        return $taban . '.tar.gz';
    }

    /** En yeni $sakla yedeği tutar, gerisini siler. */
    public static function dondur(int $sakla): void
    {
        $gruplar = ['portfolyo-' => max(1, $sakla), 'medya-' => max(1, $sakla), 'geriyukleme-oncesi-' => 5, 'yuklenen-' => 10, 'yuklemeler-' => max(1, $sakla)];
        foreach ($gruplar as $onek => $tut) {
            $dosyalar = array_values(array_filter(self::liste(), static fn(array $y): bool => str_starts_with($y['ad'], $onek)));
            foreach (array_slice($dosyalar, $tut) as $eski) {
                @unlink(self::dizin() . '/' . $eski['ad']);
            }
        }
    }

    // ------------------------------------------------------------------ doğrulama (kuru çalıştırma)

    /** @return array{ok:bool,hatalar:list<string>,tarih:?string,sema:?int,tablolar:array<string,int>,ifade:int} */
    public static function dogrula(string $yol): array
    {
        $r = ['ok' => false, 'hatalar' => [], 'tarih' => null, 'sema' => null, 'tablolar' => [], 'ifade' => 0];
        $izinli = self::izinliTablolar();
        $baslik = self::baslikOku($yol);
        if ($baslik === null || !str_contains($baslik, 'Portfolyo yedeği')) {
            $r['hatalar'][] = 'Bu dosya bir Portfolyo veritabanı yedeği değil (başlık satırı bulunamadı).';
            return $r;
        }
        if (preg_match('/^-- Portfolyo yedeği · (.+?) UTC/m', $baslik, $m) === 1) {
            $r['tarih'] = $m[1];
        }
        if (preg_match('/^-- sema_surumu: (\d+)/m', $baslik, $m) === 1) {
            $r['sema'] = (int) $m[1];
        }
        if ($r['sema'] !== null && $r['sema'] > Kurulum::SEMA_SURUMU) {
            $r['hatalar'][] = 'Bu yedek daha YENİ bir sürümden (şema ' . $r['sema'] . ', bu kurulum ' . Kurulum::SEMA_SURUMU . '). Önce yazılımı güncelleyin.';
            return $r;
        }
        $olusturulan = [];
        try {
            foreach (self::ifadeler($yol) as $s) {
                $r['ifade']++;
                $hata = self::ifadeDogrula($s, $izinli, $tablo, $tur);
                if ($hata !== null) {
                    $r['hatalar'][] = 'İfade ' . $r['ifade'] . ': ' . $hata;
                    if (count($r['hatalar']) >= 8) {
                        break;
                    }
                    continue;
                }
                if ($tur === 'create') {
                    $olusturulan[$tablo] = true;
                } elseif ($tur === 'insert') {
                    $r['tablolar'][$tablo] = ($r['tablolar'][$tablo] ?? 0) + substr_count($s, "),\n(") + 1;
                }
            }
        } catch (Throwable $t) {
            $r['hatalar'][] = 'Dosya okunamadı: ' . $t->getMessage();
        }
        foreach (['kullanicilar', 'ayarlar'] as $zorunlu) {
            if (!isset($olusturulan[$zorunlu])) {
                $r['hatalar'][] = "Yedekte '{$zorunlu}' tablosu yok.";
            }
        }
        if (($r['tablolar']['kullanicilar'] ?? 0) < 1) {
            $r['hatalar'][] = 'Yedekte hiç yönetici hesabı yok; geri yüklenirse siteye giriş yapılamazdı.';
        }
        $r['ok'] = $r['hatalar'] === [];
        return $r;
    }

    /** @return list<string> bu sürümün tabloları (schema.sql'den) */
    private static function izinliTablolar(): array
    {
        $tablolar = [];
        foreach (Kurulum::sqlIfadeleri((string) file_get_contents(KOK . '/database/schema.sql')) as $i) {
            if (preg_match('/^CREATE TABLE IF NOT EXISTS `([A-Za-z0-9_]+)`/i', $i, $m) === 1) {
                $tablolar[] = $m[1];
            }
        }
        return $tablolar;
    }

    private static function baslikOku(string $yol): ?string
    {
        $gz = str_ends_with($yol, '.gz');
        $f = $gz ? @gzopen($yol, 'rb') : @fopen($yol, 'rb');
        if ($f === false) {
            return null;
        }
        $s = $gz ? gzread($f, 1024) : fread($f, 1024);
        $gz ? gzclose($f) : fclose($f);
        return is_string($s) ? $s : null;
    }

    /**
     * @param list<string> $izinli
     * @param-out string $tablo
     * @param-out string $tur
     */
    private static function ifadeDogrula(string $s, array $izinli, ?string &$tablo, ?string &$tur): ?string
    {
        $tablo = '';
        $tur = '';
        if (preg_match("/^SET (?:NAMES utf8mb4|FOREIGN_KEY_CHECKS=[01]|time_zone='\\+00:00')$/", $s) === 1) {
            $tur = 'set';
            return null;
        }
        // metin sabitleri çıkarılmış hâl (anahtar kelime/ifade denetimi için)
        $kuru = (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", 'S', $s);

        if (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`$/', $s, $m) === 1) {
            $tur = 'drop';
            $tablo = $m[1];
            return in_array($tablo, $izinli, true) ? null : "tanınmayan tablo '{$tablo}'";
        }
        if (preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` \(/', $s, $m) === 1) {
            $tur = 'create';
            $tablo = $m[1];
            if (!in_array($tablo, $izinli, true)) {
                return "tanınmayan tablo '{$tablo}'";
            }
            $temiz = (string) preg_replace('/ON (?:DELETE|UPDATE) (?:CASCADE|RESTRICT|SET NULL|NO ACTION|CURRENT_TIMESTAMP)/i', '', $kuru);
            if (preg_match('/\b(?:SELECT|INSERT|UPDATE|DELETE|GRANT|TRIGGER|PROCEDURE|FUNCTION|EVENT|VIEW|LOAD|OUTFILE|DUMPFILE|DELIMITER|CALL|EXECUTE|PREPARE|DO|SLEEP|BENCHMARK)\b/i', $temiz) === 1) {
                return "'{$tablo}' tablo tanımında izin verilmeyen anahtar kelime";
            }
            return null;
        }
        if (preg_match('/^INSERT INTO `([A-Za-z0-9_]+)` VALUES\s*\(/', $s, $m) === 1) {
            $tur = 'insert';
            $tablo = $m[1];
            if (!in_array($tablo, $izinli, true)) {
                return "tanınmayan tablo '{$tablo}'";
            }
            $govde = (string) preg_replace('/^INSERT INTO `[A-Za-z0-9_]+` VALUES/', '', $kuru);
            $govde = (string) preg_replace('/\b(?:NULL|0x[0-9a-fA-F]*|S)\b|\bS\b/', '', $govde);
            if (preg_match('/^[\s0-9.,()eE+\-]*$/', $govde) !== 1) {
                return "'{$tablo}' verisinde sabit olmayan ifade (yalnızca sayı, metin, NULL ve 0x... kabul edilir)";
            }
            return null;
        }
        return 'izin verilmeyen ifade: ' . mb_substr(preg_replace('/\s+/', ' ', $s) ?? '', 0, 60, 'UTF-8');
    }

    /**
     * SQL dosyasını ifadelere böler (metin sabitlerindeki ; ve ' işaretlerine saygılı, akışlı okuma).
     * @return Generator<int,string>
     */
    private static function ifadeler(string $yol): Generator
    {
        $gz = str_ends_with($yol, '.gz');
        $f = $gz ? gzopen($yol, 'rb') : fopen($yol, 'rb');
        if ($f === false) {
            throw new RuntimeException('Yedek dosyası açılamadı.');
        }
        $eof = static fn(): bool => $gz ? gzeof($f) : feof($f);
        $oku = static function () use ($f, $gz): string {
            $s = $gz ? gzread($f, 262144) : fread($f, 262144);
            return is_string($s) ? $s : '';
        };
        try {
            $buf = '';
            $pos = 0;
            $ifade = '';
            $dize = false;
            while (true) {
                if ($pos >= strlen($buf)) {
                    if ($eof()) {
                        break;
                    }
                    $buf = $oku();
                    $pos = 0;
                    if ($buf === '') {
                        if ($eof()) {
                            break;
                        }
                        continue;
                    }
                }
                $n = strlen($buf);
                if ($dize) {
                    $k = strcspn($buf, "'\\", $pos);
                    $ifade .= substr($buf, $pos, $k);
                    $pos += $k;
                    if ($pos >= $n) {
                        continue;
                    }
                    $c = $buf[$pos];
                    if ($pos + 1 >= $n && !$eof()) { // bir sonraki karaktere ihtiyaç var: tamponu ilerlet
                        $buf = substr($buf, $pos) . $oku();
                        $pos = 0;
                        $n = strlen($buf);
                    }
                    if ($c === '\\') {
                        $ifade .= $c . ($pos + 1 < $n ? $buf[$pos + 1] : '');
                        $pos += 2;
                    } elseif ($pos + 1 < $n && $buf[$pos + 1] === "'") {
                        $ifade .= "''";
                        $pos += 2;
                    } else {
                        $ifade .= "'";
                        $pos++;
                        $dize = false;
                    }
                } else {
                    $k = strcspn($buf, "';", $pos);
                    $ifade .= substr($buf, $pos, $k);
                    $pos += $k;
                    if ($pos >= $n) {
                        continue;
                    }
                    if ($buf[$pos] === "'") {
                        $ifade .= "'";
                        $pos++;
                        $dize = true;
                    } else {
                        $pos++;
                        $t = self::yorumlariAt(trim($ifade));
                        $ifade = '';
                        if ($t !== '') {
                            yield $t;
                        }
                    }
                }
            }
            $son = self::yorumlariAt(trim($ifade));
            if ($son !== '') {
                yield $son;
            }
        } finally {
            $gz ? gzclose($f) : fclose($f);
        }
    }

    private static function yorumlariAt(string $s): string
    {
        while (str_starts_with($s, '--')) {
            $nl = strpos($s, "\n");
            $s = $nl === false ? '' : ltrim(substr($s, $nl + 1));
        }
        return $s;
    }

    // ------------------------------------------------------------------ geri yükleme

    /**
     * Doğrulanmış bir SQL yedeğini geri yükler.
     * @return array{ok:bool,mesaj:string,oncesi:?string,ifade:int}
     */
    public static function geriYukle(string $yol): array
    {
        $d = self::dogrula($yol);
        if (!$d['ok']) {
            return ['ok' => false, 'mesaj' => 'Yedek doğrulanamadı: ' . implode(' | ', $d['hatalar']), 'oncesi' => null, 'ifade' => 0];
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        $oncesi = self::sqlAl('geriyukleme-oncesi');
        $bayrak = KOK . '/storage/cache/bakim.flag';
        @file_put_contents($bayrak, (string) time());
        try {
            $n = self::uygula($yol);
        } catch (Throwable $t) {
            gunluk('Geri yükleme başarısız, önceki duruma dönülüyor: ' . $t->getMessage());
            try {
                self::uygula($oncesi);
                $mesaj = 'Geri yükleme başarısız oldu; veritabanı önceki durumuna döndürüldü. Ayrıntı: ' . $t->getMessage();
            } catch (Throwable $t2) {
                gunluk('Önceki duruma dönüş de başarısız: ' . $t2->getMessage());
                $mesaj = 'Geri yükleme başarısız oldu VE otomatik geri alma tamamlanamadı. Elle geri yükleyin: storage/yedekler/' . basename($oncesi);
            }
            @unlink($bayrak);
            return ['ok' => false, 'mesaj' => $mesaj, 'oncesi' => basename($oncesi), 'ifade' => 0];
        }
        try {
            Kurulum::semaUygula(); // eski bir yedekte eksik tablo/sütunları tamamla
        } catch (Throwable $t) {
            gunluk('Geri yükleme sonrası şema tamamlama hatası: ' . $t->getMessage());
        }
        @unlink($bayrak);
        return ['ok' => true, 'mesaj' => 'Yedek geri yüklendi.', 'oncesi' => basename($oncesi), 'ifade' => $n];
    }

    /** @return int çalıştırılan ifade sayısı */
    private static function uygula(string $yol): int
    {
        $pdo = Veritabani::baglanti();
        $n = 0;
        foreach (self::ifadeler($yol) as $s) {
            $pdo->exec($s);
            $n++;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        return $n;
    }

    /** Medya arşivini (.tar.gz) yalnızca geçerli yükleme adlarıyla storage/yuklemeler içine açar. @return array{ok:bool,mesaj:string,adet:int} */
    public static function medyaGeriYukle(string $yol): array
    {
        if (!class_exists(\PharData::class)) {
            return ['ok' => false, 'mesaj' => 'PHP phar eklentisi yok.', 'adet' => 0];
        }
        $hedef = (string) config('yukleme.dizin');
        if (!is_dir($hedef) && !@mkdir($hedef, 0750, true) && !is_dir($hedef)) {
            return ['ok' => false, 'mesaj' => 'Yükleme klasörü oluşturulamadı.', 'adet' => 0];
        }
        $adet = 0;
        try {
            $arsiv = new \PharData($yol);
            $onek = 'phar://' . str_replace('\\', '/', $yol) . '/';
            foreach (new \RecursiveIteratorIterator($arsiv) as $dosya) {
                $ad = basename((string) $dosya->getFilename());
                $mutlak = str_replace('\\', '/', (string) $dosya->getPathname());
                // yalnızca arşiv kökündeki, doğrulanmış yükleme adlı dosyalar (zip-slip/yol gezdirme yok)
                if (!Yukleme::gecerliAd($ad) || $mutlak !== $onek . $ad) {
                    continue;
                }
                $veri = file_get_contents($mutlak);
                if ($veri === false || strlen($veri) > 200 * 1048576) {
                    continue;
                }
                file_put_contents($hedef . '/' . $ad, $veri, LOCK_EX);
                @chmod($hedef . '/' . $ad, 0640);
                $adet++;
            }
        } catch (Throwable $t) {
            return ['ok' => false, 'mesaj' => 'Medya arşivi açılamadı: ' . $t->getMessage(), 'adet' => $adet];
        }
        try { // geri yüklenen dosyaları medya kütüphanesine kaydet
            require_once KOK . '/database/migrasyon.php';
            migrasyonlari_uygula(Veritabani::baglanti(), (string) config('db.ad'), $hedef);
        } catch (Throwable $t) {
            gunluk('Medya kaydı yenilenemedi: ' . $t->getMessage());
        }
        return ['ok' => true, 'mesaj' => $adet . ' dosya geri yüklendi.', 'adet' => $adet];
    }

    // ------------------------------------------------------------------ dış hedefler

    /** @return list<string> yapılandırılmış (kullanılabilir) hedefler */
    public static function hedefler(): array
    {
        $h = [];
        if (Posta::yapilandirildi()) {
            $h[] = 'eposta';
        }
        if ((string) config('yedek.ftp.host', '') !== '') {
            $h[] = 'ftp';
        }
        if ((string) config('yedek.webdav.url', '') !== '') {
            $h[] = 'webdav';
        }
        return $h;
    }

    /**
     * @param list<string> $hedefler eposta|ftp|webdav
     * @return array<string,array{ok:bool,mesaj:string}>
     */
    public static function disaGonder(string $yol, array $hedefler): array
    {
        $sonuc = [];
        foreach (array_unique($hedefler) as $h) {
            try {
                $sonuc[$h] = match ($h) {
                    'eposta' => self::epostayla($yol),
                    'ftp'    => self::ftpye($yol),
                    'webdav' => self::webdave($yol),
                    default  => ['ok' => false, 'mesaj' => 'bilinmeyen hedef'],
                };
            } catch (Throwable $t) {
                gunluk('Dış yedek hedefi hatası (' . $h . '): ' . $t->getMessage());
                $sonuc[$h] = ['ok' => false, 'mesaj' => $t->getMessage()];
            }
        }
        return $sonuc;
    }

    /** @return array{ok:bool,mesaj:string} */
    private static function epostayla(string $yol): array
    {
        $boyut = (int) filesize($yol);
        if ($boyut > 15 * 1048576) {
            return ['ok' => false, 'mesaj' => 'Dosya e-posta için çok büyük (' . round($boyut / 1048576, 1) . ' MB > 15 MB).'];
        }
        $alici = trim((string) config('posta.alici', '')) !== '' ? (string) config('posta.alici') : Ayar::al('site_eposta');
        $s = Posta::gonder($alici, '[' . Ayar::al('site_baslik', 'Portfolyo') . '] Yedek: ' . basename($yol),
            "Otomatik yedek ekte.\nDosya: " . basename($yol) . "\nBoyut: " . round($boyut / 1024) . " KB\n\nBu dosyayı güvenli bir yerde saklayın.",
            null, null, [['ad' => basename($yol), 'icerik' => (string) file_get_contents($yol), 'tur' => 'application/octet-stream']]);
        return ['ok' => $s['ok'], 'mesaj' => $s['ok'] ? 'E-posta gönderildi.' : (string) $s['hata']];
    }

    /** @return array{ok:bool,mesaj:string} */
    private static function ftpye(string $yol): array
    {
        $c = (array) config('yedek.ftp', []);
        if (!function_exists('ftp_connect')) {
            return ['ok' => false, 'mesaj' => 'PHP ftp eklentisi yok.'];
        }
        $b = !empty($c['ssl']) && function_exists('ftp_ssl_connect')
            ? @ftp_ssl_connect((string) $c['host'], (int) $c['port'], 20)
            : @ftp_connect((string) $c['host'], (int) $c['port'], 20);
        if ($b === false) {
            return ['ok' => false, 'mesaj' => 'FTP sunucusuna bağlanılamadı.'];
        }
        try {
            if (!@ftp_login($b, (string) $c['kullanici'], (string) $c['sifre'])) {
                return ['ok' => false, 'mesaj' => 'FTP girişi başarısız (kullanıcı/parola).'];
            }
            ftp_pasv($b, true);
            $dizin = '/' . trim((string) ($c['dizin'] ?? '/'), '/');
            if ($dizin !== '/') {
                @ftp_mkdir($b, $dizin);
            }
            $hedef = rtrim($dizin, '/') . '/' . basename($yol);
            return @ftp_put($b, $hedef, $yol, FTP_BINARY)
                ? ['ok' => true, 'mesaj' => 'FTP: ' . $hedef]
                : ['ok' => false, 'mesaj' => 'FTP yükleme başarısız (dizin yetkisi?).'];
        } finally {
            @ftp_close($b);
        }
    }

    /** @return array{ok:bool,mesaj:string} */
    private static function webdave(string $yol): array
    {
        $c = (array) config('yedek.webdav', []);
        $url = (string) $c['url'];
        if (!str_starts_with($url, 'https://')) {
            return ['ok' => false, 'mesaj' => 'WebDAV adresi https:// ile başlamalıdır.'];
        }
        if ((int) filesize($yol) > 100 * 1048576) {
            return ['ok' => false, 'mesaj' => 'Dosya WebDAV yüklemesi için çok büyük (>100 MB).'];
        }
        $baglam = stream_context_create(['http' => [
            'method' => 'PUT', 'timeout' => 180, 'ignore_errors' => true,
            'header' => "Authorization: Basic " . base64_encode((string) $c['kullanici'] . ':' . (string) $c['sifre']) . "\r\nContent-Type: application/octet-stream\r\n",
            'content' => (string) file_get_contents($yol),
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        @file_get_contents($url . '/' . rawurlencode(basename($yol)), false, $baglam);
        $kod = 0;
        foreach ($http_response_header ?? [] as $satir) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $satir, $m) === 1) {
                $kod = (int) $m[1];
            }
        }
        return in_array($kod, [200, 201, 204], true)
            ? ['ok' => true, 'mesaj' => 'WebDAV: ' . basename($yol)]
            : ['ok' => false, 'mesaj' => 'WebDAV yanıtı: HTTP ' . $kod . ($kod === 401 ? ' (kullanıcı/parola?)' : '')];
    }

    // ------------------------------------------------------------------ otomatik

    /** Ayarlara göre zamanı gelmişse yedek alır ve dış hedeflere gönderir. @return array<string,mixed> */
    public static function otomatik(bool $zorla = false): array
    {
        $gun = (int) Ayar::al('otomatik_yedek_gun', '0');
        if (!$zorla && $gun <= 0) {
            return ['calisti' => false, 'neden' => 'otomatik yedek kapalı'];
        }
        if (!$zorla && time() - self::sonSqlZamani() < max(1, $gun) * 86400 - 3600) {
            return ['calisti' => false, 'neden' => 'yedek güncel'];
        }
        $sql = self::sqlAl();
        $medya = Ayar::al('yedek_medya_dahil', '0') === '1' ? self::medyaAl() : null;
        $istenen = [];
        foreach (['eposta' => 'yedek_eposta', 'ftp' => 'yedek_ftp', 'webdav' => 'yedek_webdav'] as $h => $anahtar) {
            if (Ayar::al($anahtar, '0') === '1' && in_array($h, self::hedefler(), true)) {
                $istenen[] = $h;
            }
        }
        $disa = $istenen === [] ? [] : self::disaGonder($sql, $istenen);
        if ($medya !== null) { // medya arşivi e-postayla gönderilmez (boyut), yalnızca FTP/WebDAV
            $disa['medya'] = self::disaGonder($medya, array_values(array_intersect($istenen, ['ftp', 'webdav'])));
        }
        self::dondur((int) config('yedek.sakla', 14));
        Denetim::kaydet('yedek.alindi', basename($sql), 'otomatik' . ($medya !== null ? ' + medya' : ''));
        return ['calisti' => true, 'sql' => basename($sql), 'medya' => $medya !== null ? basename($medya) : null, 'dis' => $disa];
    }
}
