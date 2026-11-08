<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

/**
 * Tarayıcı üzerinden kurulum (SSH/komut satırı olmayan paylaşımlı hostingler için).
 * Varsayılan olarak KAPALIDIR: yalnızca .env içinde en az 16 karakterlik KURULUM_TOKEN tanımlıysa
 * VE veritabanında hiç kullanıcı yokken çalışır. İlk yönetici oluşturulduktan sonra kendiliğinden kapanır.
 */
final class Kurulum
{
    public const MIN_TOKEN = 16;

    /**
     * Veritabanı şeması sürümü. schema.sql / migrasyon.php'ye yeni tablo veya sütun eklendiğinde BİR ARTIRIN:
     * yönetici panele girdiğinde sürüm farkı otomatik fark edilir ve geçişler uygulanır (SSH gerektirmez).
     */
    public const SEMA_SURUMU = 2;

    /** Kayıtlı şema sürümü eskiyse geçişleri uygular. Hata verirse siteyi bozmaz, günlüğe yazar. */
    public static function otomatikGuncelle(): void
    {
        try {
            if ((int) \App\Models\Ayar::al('sema_surumu', '0') >= self::SEMA_SURUMU) {
                return;
            }
            $sonuc = self::semaUygula();
            \App\Models\Ayar::kaydet('sema_surumu', (string) self::SEMA_SURUMU);
            gunluk('Şema otomatik güncellendi (sürüm ' . self::SEMA_SURUMU . '): ' . ($sonuc['gecis'] === [] ? 'yeni geçiş yok' : implode('; ', $sonuc['gecis'])));
        } catch (Throwable $t) {
            gunluk('Otomatik şema güncellemesi başarısız (php database/install.php ile elle çalıştırın): ' . $t->getMessage());
        }
    }

    public static function token(): string
    {
        return trim((string) config('kurulum.token', ''));
    }

    /** Sihirbaz açılabilir mi? (token yeterince uzun ve henüz kullanıcı yok) */
    public static function etkinMi(string $token, ?int $kullaniciSayisi): bool
    {
        return strlen($token) >= self::MIN_TOKEN && $kullaniciSayisi === 0;
    }

    /**
     * Kayıtlı kullanıcı sayısı. Tablo henüz yoksa 0; veritabanına hiç bağlanılamıyorsa
     * de 0 döner (kurulum bu durumda ön kontrol listesinde hatayı gösterir).
     */
    public static function kullaniciSayisi(): int
    {
        try {
            return (int) Veritabani::skaler('SELECT COUNT(*) FROM kullanicilar');
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array{ok:bool,ad:string,not:string}>
     */
    public static function onKontrol(): array
    {
        $liste = [];
        // not: başarısızsa gösterilen ipucu · bilgi: başarılıyken gösterilen açıklama
        $ekle = static function (bool $ok, string $ad, string $not = '', string $bilgi = '') use (&$liste): void {
            $liste[] = ['ok' => $ok, 'ad' => $ad, 'not' => $not, 'bilgi' => $bilgi];
        };
        $ekle(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP ' . PHP_VERSION, 'PHP 8.2 veya üzeri gerekir.');
        foreach (['pdo_mysql', 'mbstring', 'fileinfo', 'openssl'] as $e) {
            $ekle(extension_loaded($e), 'PHP eklentisi: ' . $e, 'Hosting panelinden (PHP Seçenekleri / Select PHP Version) etkinleştirin.');
        }
        $env = KOK . '/.env';
        $ekle(is_file($env), '.env dosyası', 'config/.env.example dosyasını .env olarak kopyalayın.');
        foreach (['storage/yuklemeler', 'storage/oturum', 'storage/logs', 'storage/cache'] as $d) {
            $yol = KOK . '/' . $d;
            if (!is_dir($yol)) {
                @mkdir($yol, 0750, true);
            }
            $ekle(is_dir($yol) && is_writable($yol), $d . ' yazılabilir', 'Klasör izni 755/775 olmalı ve web sunucusu kullanıcısı yazabilmelidir.');
        }
        $db = (array) config('db', []);
        $dbAd = (string) ($db['ad'] ?? '');
        try {
            self::pdo($dbAd, true);
            $ekle(true, 'Veritabanı bağlantısı (' . $dbAd . ')');
        } catch (Throwable $t) {
            gunluk('Kurulum ön kontrol: veritabanına bağlanılamadı: ' . $t->getMessage());
            // Veritabanı henüz yoksa ama sunucuya bağlanılabiliyorsa sihirbaz onu oluşturmayı dener.
            try {
                self::pdo($dbAd, false);
                $ekle(true, 'Veritabanı sunucusuna bağlanıldı', '', '"' . $dbAd . '" veritabanı bulunamadı; kurulum oluşturmayı deneyecek. Yetkiniz yoksa önce hosting panelinden bu adla boş bir veritabanı oluşturun.');
            } catch (Throwable $t2) {
                gunluk('Kurulum ön kontrol: veritabanı sunucusuna bağlanılamadı: ' . $t2->getMessage());
                $ekle(false, 'Veritabanı sunucusuna bağlantı', '.env içindeki DB_HOST, DB_KULLANICI, DB_SIFRE değerlerini kontrol edin (ayrıntı: storage/logs/uygulama.log).');
            }
        }
        return $liste;
    }

    /** @throws PDOException */
    public static function pdo(string $dbAd, bool $dbVar = true): PDO
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbAd) !== 1) {
            throw new PDOException('DB_NAME yalnızca harf, rakam ve alt çizgi içermelidir.');
        }
        $c = (array) config('db', []);
        $dsn = sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', (string) ($c['host'] ?? '127.0.0.1'), (int) ($c['port'] ?? 3306),
            $dbVar ? 'dbname=' . $dbAd . ';' : '');
        return new PDO($dsn, (string) ($c['kullanici'] ?? ''), (string) ($c['sifre'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }

    /** SQL dosyasını ifadelere böler (satır yorumlarını atar). @return list<string> */
    public static function sqlIfadeleri(string $sql): array
    {
        $satirlar = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $satir) {
            if (preg_match('/^\s*--/', $satir) !== 1) {
                $satirlar[] = $satir;
            }
        }
        $ifadeler = [];
        foreach (preg_split('/;\s*(?:\R|$)/', implode("\n", $satirlar)) ?: [] as $i) {
            $i = trim($i);
            if ($i !== '') {
                $ifadeler[] = $i;
            }
        }
        return $ifadeler;
    }

    /**
     * Veritabanını (yoksa oluşturmayı dener), şemayı ve geçişleri uygular.
     * @return array{ifade:int,gecis:list<string>}
     */
    public static function semaUygula(): array
    {
        $dbAd = (string) config('db.ad', '');
        // Paylaşımlı hostingde CREATE DATABASE yetkisi genelde yoktur ve veritabanı panelden önceden açılmıştır.
        try {
            self::pdo($dbAd, false)->exec('CREATE DATABASE IF NOT EXISTS `' . $dbAd . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable) {
            // yok sayılır: veritabanı zaten varsa aşağıdaki bağlantı çalışır
        }
        $pdo = self::pdo($dbAd, true);
        $sql = file_get_contents(KOK . '/database/schema.sql');
        if ($sql === false) {
            throw new PDOException('database/schema.sql okunamadı.');
        }
        $sayi = 0;
        foreach (self::sqlIfadeleri($sql) as $ifade) {
            if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $ifade) === 1) {
                continue;
            }
            $pdo->exec($ifade);
            $sayi++;
        }
        require_once KOK . '/database/migrasyon.php';
        $gecis = migrasyonlari_uygula($pdo, $dbAd, (string) config('yukleme.dizin'));
        $pdo->prepare('INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)')
            ->execute(['sema_surumu', (string) self::SEMA_SURUMU]);
        return ['ifade' => $sayi, 'gecis' => $gecis];
    }

    /** UYGULAMA_ANAHTARI boşsa üretip .env dosyasına yazar. true: anahtar mevcut/yazıldı. */
    public static function anahtarHazirla(): bool
    {
        if (trim((string) config('uygulama.anahtar', '')) !== '') {
            return true;
        }
        $dosya = KOK . '/.env';
        if (!is_file($dosya) || !is_writable($dosya)) {
            return false;
        }
        $anahtar = bin2hex(random_bytes(32));
        $icerik = (string) file_get_contents($dosya);
        if (preg_match('/^UYGULAMA_ANAHTARI=.*$/m', $icerik) === 1) {
            $icerik = (string) preg_replace('/^UYGULAMA_ANAHTARI=.*$/m', 'UYGULAMA_ANAHTARI=' . $anahtar, $icerik, 1);
        } else {
            $icerik = rtrim($icerik) . PHP_EOL . 'UYGULAMA_ANAHTARI=' . $anahtar . PHP_EOL;
        }
        return file_put_contents($dosya, $icerik, LOCK_EX) !== false;
    }
}
