<?php
declare(strict_types=1);

/**
 * Kurulum / bakım CLI betiği — YALNIZCA komut satırından çalışır.
 *
 *   php database/install.php                       Şemayı kurar/GÜNCELLER (veri kaybı olmadan), ilk yöneticiyi oluşturur
 *   php database/install.php --yeni-admin          Var olan sisteme ek yönetici ekler
 *   php database/install.php --parola-sifirla=a@b.c   Kullanıcı parolasını sıfırlar
 *   php database/install.php --2fa-sifirla=a@b.c   Kullanıcının iki adımlı doğrulamasını kapatır (telefon kaybında)
 *   php database/install.php --sema-atla           Şemayı (tabloları) atla, yalnızca yönetici işlemi
 *   php database/install.php --yardim
 *
 * Parola, hiçbir yerde düz metin saklanmadan password_hash (bcrypt, cost 12) ile özetlenir.
 * Etkileşimsiz kullanım için ortam değişkenleri: KURULUM_ADMIN_AD, KURULUM_ADMIN_EPOSTA, KURULUM_ADMIN_PAROLA
 * (kabuk geçmişine yazılmaması için komut satırı argümanı olarak parola KABUL EDİLMEZ).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Dogrulayici;
use App\Core\Veritabani;
use App\Core\Yapilandirma;
use App\Models\Kullanici;

function yaz(string $m): void
{
    fwrite(STDOUT, $m . PHP_EOL);
}

function hata_cik(string $m, int $kod = 1): never
{
    fwrite(STDERR, 'HATA: ' . $m . PHP_EOL);
    exit($kod);
}

function sor(string $soru): string
{
    fwrite(STDOUT, $soru);
    $s = fgets(STDIN);
    return $s === false ? '' : trim($s);
}

function gizli_sor(string $soru): string
{
    fwrite(STDOUT, $soru);
    $deger = null;
    if (DIRECTORY_SEPARATOR === '\\') {
        if (function_exists('shell_exec')) {
            $cmd = 'powershell -NoProfile -Command "$p = Read-Host -AsSecureString; '
                . '[Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"';
            $c = @shell_exec($cmd);
            if (is_string($c) && trim($c) !== '') {
                $deger = rtrim($c, "\r\n");
            }
        }
        if ($deger === null) {
            yaz('(Uyarı: gizli giriş kullanılamadı, parola ekranda görünebilir.)');
            $deger = sor('');
        }
    } else {
        @system('stty -echo');
        $s = fgets(STDIN);
        @system('stty echo');
        fwrite(STDOUT, PHP_EOL);
        $deger = $s === false ? '' : rtrim($s, "\r\n");
    }
    return $deger;
}

/** SQL dosyasını ifadelere böler (satır yorumlarını atar). */
function sql_ifadeleri(string $sql): array
{
    $satirlar = [];
    foreach (preg_split('/\R/', $sql) ?: [] as $satir) {
        if (preg_match('/^\s*--/', $satir) === 1) {
            continue;
        }
        $satirlar[] = $satir;
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

function env_yaz(string $dosya, string $anahtar, string $deger): void
{
    $icerik = (string) file_get_contents($dosya);
    $satir = $anahtar . '=' . $deger;
    if (preg_match('/^' . preg_quote($anahtar, '/') . '=.*$/m', $icerik) === 1) {
        $icerik = (string) preg_replace('/^' . preg_quote($anahtar, '/') . '=.*$/m', $satir, $icerik, 1);
    } else {
        $icerik = rtrim($icerik) . PHP_EOL . $satir . PHP_EOL;
    }
    file_put_contents($dosya, $icerik, LOCK_EX);
}

function parola_iste(): string
{
    $hazir = getenv('KURULUM_ADMIN_PAROLA');
    if (is_string($hazir) && $hazir !== '') {
        $h = Dogrulayici::parolaHatalari($hazir);
        if ($h !== []) {
            hata_cik('KURULUM_ADMIN_PAROLA yeterince güçlü değil: ' . implode(' ', $h));
        }
        return $hazir;
    }
    for ($deneme = 0; $deneme < 3; $deneme++) {
        $p = gizli_sor('Parola (en az 12 karakter; büyük/küçük harf, rakam, özel karakter): ');
        $hatalar = Dogrulayici::parolaHatalari($p);
        if ($hatalar !== []) {
            yaz('  ✗ ' . implode(' ', $hatalar));
            continue;
        }
        $t = gizli_sor('Parola (tekrar): ');
        if (!hash_equals($p, $t)) {
            yaz('  ✗ Parolalar eşleşmiyor.');
            continue;
        }
        return $p;
    }
    hata_cik('Geçerli bir parola belirlenemedi.');
}

// ---------------------------------------------------------------- argümanlar
$secenek = [];
foreach (array_slice($argv ?? [], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m) === 1) {
        $secenek[$m[1]] = $m[2] ?? true;
    } else {
        hata_cik('Bilinmeyen argüman: ' . $a . ' (parola argüman olarak verilemez; --yardim)');
    }
}
if (isset($secenek['yardim'])) {
    $baslik = file_get_contents(__FILE__);
    preg_match('/\/\*\*(.*?)\*\//s', (string) $baslik, $m);
    yaz(trim((string) preg_replace('/^\s*\* ?/m', '', $m[1] ?? '')));
    exit(0);
}

// ---------------------------------------------------------------- .env
$envDosya = KOK . '/.env';
if (!is_file($envDosya)) {
    if (!copy(KOK . '/config/.env.example', $envDosya)) {
        hata_cik('.env oluşturulamadı. config/.env.example dosyasını elle .env olarak kopyalayın.');
    }
    @chmod($envDosya, 0600);
    yaz('• .env dosyası config/.env.example üzerinden oluşturuldu. DB bilgilerini kontrol edin.');
}
Yapilandirma::yukle($envDosya);
if (trim((string) Yapilandirma::env('UYGULAMA_ANAHTARI', '')) === '') {
    env_yaz($envDosya, 'UYGULAMA_ANAHTARI', bin2hex(random_bytes(32)));
    yaz('• UYGULAMA_ANAHTARI rastgele üretilip .env dosyasına yazıldı.');
    Yapilandirma::yukle($envDosya);
}
Yapilandirma::configAyarla(require KOK . '/config/config.php');
$db = (array) config('db');
$dbAd = (string) $db['ad'];
if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbAd) !== 1) {
    hata_cik('DB_NAME yalnızca harf, rakam ve alt çizgi içermelidir.');
}

// ---------------------------------------------------------------- veritabanı + şema
if (!isset($secenek['sema-atla'])) {
    try {
        $kok = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']),
            (string) $db['kullanici'],
            (string) $db['sifre'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $kok->exec('CREATE DATABASE IF NOT EXISTS `' . $dbAd . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $kok->exec('USE `' . $dbAd . '`');
        $sql = file_get_contents(KOK . '/database/schema.sql');
        if ($sql === false) {
            hata_cik('database/schema.sql okunamadı.');
        }
        $sayi = 0;
        foreach (sql_ifadeleri($sql) as $ifade) {
            if (preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $ifade) === 1) {
                continue;
            }
            $kok->exec($ifade);
            $sayi++;
        }
        yaz("✓ Şema uygulandı ({$sayi} ifade) → veritabanı: {$dbAd}");

        require_once KOK . '/database/migrasyon.php';
        foreach (migrasyonlari_uygula($kok, $dbAd, (string) config('yukleme.dizin')) as $satir) {
            yaz('• Geçiş: ' . $satir);
        }
        // Panelin otomatik şema güncellemesi bu sürümü tekrar çalıştırmasın
        $kok->prepare('INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)')
            ->execute(['sema_surumu', (string) \App\Core\Kurulum::SEMA_SURUMU]);
    } catch (PDOException $e) {
        hata_cik('Veritabanı işlemi başarısız: ' . $e->getMessage() . ' (.env DB_* değerlerini ve MySQL servisini kontrol edin)');
    }
}

try {
    Veritabani::baglanti();
} catch (Throwable $e) {
    hata_cik($e->getMessage());
}

// ---------------------------------------------------------------- parola sıfırlama
if (isset($secenek['parola-sifirla'])) {
    $eposta = mb_strtolower(trim((string) $secenek['parola-sifirla']), 'UTF-8');
    $k = Kullanici::epostaIle($eposta);
    if ($k === null) {
        hata_cik('Bu e-posta ile kullanıcı bulunamadı: ' . $eposta);
    }
    $parola = parola_iste();
    Kullanici::parolaGuncelle((int) $k['id'], $parola);
    Veritabani::calistir('DELETE FROM giris_denemeleri WHERE kimlik = ?', [$eposta]);
    yaz('✓ Parola güncellendi: ' . $eposta);
    exit(0);
}

// ---------------------------------------------------------------- iki adımlı doğrulamayı sıfırlama (telefon + yedek kodlar kaybedildiğinde)
if (isset($secenek['2fa-sifirla'])) {
    $eposta = mb_strtolower(trim((string) $secenek['2fa-sifirla']), 'UTF-8');
    $k = Kullanici::epostaIle($eposta);
    if ($k === null) {
        hata_cik('Bu e-posta ile kullanıcı bulunamadı: ' . $eposta);
    }
    Kullanici::totpKapat((int) $k['id']);
    Veritabani::calistir('DELETE FROM giris_denemeleri WHERE kimlik IN (?, ?)', [$eposta, '2fa:' . (int) $k['id']]);
    yaz('✓ İki adımlı doğrulama kapatıldı: ' . $eposta . ' (giriş yaptıktan sonra Profil sayfasından yeniden açın)');
    exit(0);
}

// ---------------------------------------------------------------- yönetici oluşturma
$mevcut = Kullanici::say();
if ($mevcut > 0 && !isset($secenek['yeni-admin'])) {
    yaz("• Sistemde zaten {$mevcut} kullanıcı var; yönetici oluşturma atlandı (ek yönetici için --yeni-admin).");
    exit(0);
}

$ad = (string) (getenv('KURULUM_ADMIN_AD') ?: '');
$eposta = (string) (getenv('KURULUM_ADMIN_EPOSTA') ?: '');
if ($ad === '') {
    $ad = sor('Ad Soyad: ');
}
if ($eposta === '') {
    $eposta = sor('E-posta (giriş kullanıcı adı): ');
}
$ad = Dogrulayici::temizle($ad);
$eposta = mb_strtolower(Dogrulayici::temizle($eposta), 'UTF-8');
if (mb_strlen($ad, 'UTF-8') < 2 || mb_strlen($ad, 'UTF-8') > 120) {
    hata_cik('Ad Soyad 2-120 karakter olmalıdır.');
}
if (!Dogrulayici::epostaGecerli($eposta)) {
    hata_cik('Geçerli bir e-posta girin.');
}
if (Kullanici::epostaVarMi($eposta)) {
    hata_cik('Bu e-posta zaten kayıtlı.');
}
$parola = parola_iste();
$id = Kullanici::olustur($ad, $eposta, $parola, 'admin');
yaz("✓ Yönetici oluşturuldu (id={$id}, e-posta={$eposta}).");
yaz('  Giriş: /admin/giris — Güvenlik için kurulum sonrası bu dosyayı sunucudan silmeniz önerilir.');
exit(0);
