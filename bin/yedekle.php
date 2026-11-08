<?php
declare(strict_types=1);

/**
 * Veritabanı (ve isteğe bağlı olarak yüklenen medya) yedeği — saf PHP, mysqldump gerektirmez.
 *
 *   php bin/yedekle.php                 SQL yedeği (gzip) → storage/yedekler/
 *   php bin/yedekle.php --yuklemeler    Ek olarak storage/yuklemeler klasörünü .zip olarak yedekler
 *   php bin/yedekle.php --sakla=30      En yeni 30 yedeği tutar (varsayılan: .env YEDEK_SAKLA, 14)
 *   php bin/yedekle.php --dizin=/yol    Yedek klasörünü değiştirir
 *
 * Geri yükleme:  gunzip -c storage/yedekler/portfolyo-....sql.gz | mysql -u kullanici -p veritabani
 * Zamanlama: docs/KURULUM.md → "Yedekleme" (cron / Windows Görev Zamanlayıcı örnekleri).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Veritabani;
use App\Core\Yapilandirma;

function y_yaz(string $m): void { fwrite(STDOUT, $m . PHP_EOL); }
function y_hata(string $m): never { fwrite(STDERR, 'HATA: ' . $m . PHP_EOL); exit(1); }

$secenek = [];
foreach (array_slice($argv ?? [], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m) === 1) {
        $secenek[$m[1]] = $m[2] ?? true;
    } else {
        y_hata('Bilinmeyen argüman: ' . $a);
    }
}

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');
$dizin = is_string($secenek['dizin'] ?? null) ? (string) $secenek['dizin'] : (string) config('yedek.dizin');
$sakla = isset($secenek['sakla']) ? max(1, (int) $secenek['sakla']) : (int) config('yedek.sakla', 14);
if (!is_dir($dizin) && !@mkdir($dizin, 0700, true) && !is_dir($dizin)) {
    y_hata('Yedek klasörü oluşturulamadı: ' . $dizin);
}

try {
    $pdo = Veritabani::baglanti();
} catch (Throwable $t) {
    y_hata($t->getMessage());
}

$damga = date('Ymd-His');
$gz = function_exists('gzopen');
$sqlYol = $dizin . '/portfolyo-' . $damga . '.sql' . ($gz ? '.gz' : '');
$yaz = $gz ? gzopen($sqlYol, 'wb9') : fopen($sqlYol, 'wb');
if ($yaz === false) {
    y_hata('Yedek dosyası yazılamadı: ' . $sqlYol);
}
$w = static function (string $s) use ($yaz, $gz): void {
    $gz ? gzwrite($yaz, $s) : fwrite($yaz, $s);
};

$w("-- Portfolyo yedeği · " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET time_zone='+00:00';\n\n");
$tablolar = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
$satirToplam = 0;
foreach ($tablolar as $tablo) {
    $t = '`' . str_replace('`', '``', (string) $tablo) . '`';
    $olustur = $pdo->query('SHOW CREATE TABLE ' . $t)->fetch(PDO::FETCH_NUM);
    $w("DROP TABLE IF EXISTS {$t};\n" . $olustur[1] . ";\n\n");
    $st = $pdo->query('SELECT * FROM ' . $t);
    $blok = [];
    $sayi = 0;
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
                $degerler[] = mb_check_encoding($d, 'UTF-8') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $d)
                    ? $pdo->quote($d)
                    : ($d === '' ? "''" : '0x' . bin2hex($d));
            }
        }
        $blok[] = '(' . implode(',', $degerler) . ')';
        $sayi++;
        if (count($blok) >= 200) {
            $w("INSERT INTO {$t} VALUES\n" . implode(",\n", $blok) . ";\n");
            $blok = [];
        }
    }
    if ($blok !== []) {
        $w("INSERT INTO {$t} VALUES\n" . implode(",\n", $blok) . ";\n");
    }
    $w("\n");
    $satirToplam += $sayi;
    y_yaz("  • {$tablo}: {$sayi} satır");
}
$w("SET FOREIGN_KEY_CHECKS=1;\n");
$gz ? gzclose($yaz) : fclose($yaz);
@chmod($sqlYol, 0600);
y_yaz('✓ Veritabanı yedeği: ' . $sqlYol . ' (' . number_format((int) filesize($sqlYol) / 1024, 1, ',', '.') . ' KB, ' . count($tablolar) . ' tablo, ' . $satirToplam . ' satır)');

if (isset($secenek['yuklemeler'])) {
    $kaynak = (string) config('yukleme.dizin');
    if (!class_exists('ZipArchive')) {
        y_yaz('! php-zip eklentisi yok: yüklemeler yedeklenemedi (storage/yuklemeler klasörünü elle kopyalayın).');
    } elseif (is_dir($kaynak)) {
        $zipYol = $dizin . '/yuklemeler-' . $damga . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipYol, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $n = 0;
            foreach (scandir($kaynak) ?: [] as $d) {
                if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]{3,4}$/', $d) === 1) {
                    $zip->addFile($kaynak . '/' . $d, $d);
                    $n++;
                }
            }
            $zip->close();
            @chmod($zipYol, 0600);
            y_yaz("✓ Yüklemeler yedeği: {$zipYol} ({$n} dosya)");
        }
    }
}

// Eski yedekleri temizle
foreach (['portfolyo-*.sql*', 'yuklemeler-*.zip'] as $desen) {
    $liste = glob($dizin . '/' . $desen) ?: [];
    rsort($liste);
    foreach (array_slice($liste, $sakla) as $eski) {
        @unlink($eski);
        y_yaz('  - eski yedek silindi: ' . basename($eski));
    }
}
