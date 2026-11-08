<?php
declare(strict_types=1);

use App\Core\Yapilandirma as Y;

/**
 * Uygulama yapılandırması. Değerler .env dosyasından (veya ortam değişkenlerinden) gelir.
 * Gizli bilgi bu dosyaya YAZILMAZ.
 */
$ortam = (string) Y::env('APP_ENV', 'production');

return [
    'uygulama' => [
        'ortam'        => $ortam,
        'debug'        => Y::envBool('APP_DEBUG', false),
        'url'          => rtrim((string) Y::env('APP_URL', ''), '/'),
        'zaman_dilimi' => (string) Y::env('APP_TIMEZONE', 'Europe/Istanbul'),
        'anahtar'      => (string) Y::env('UYGULAMA_ANAHTARI', ''),
    ],
    'db' => [
        'host'      => (string) Y::env('DB_HOST', '127.0.0.1'),
        'port'      => (int) Y::env('DB_PORT', 3306),
        'ad'        => (string) Y::env('DB_NAME', 'portfolyo'),
        'kullanici' => (string) Y::env('DB_KULLANICI', 'root'),
        'sifre'     => (string) Y::env('DB_SIFRE', ''),
    ],
    'oturum' => [
        'ad'        => (string) Y::env('SESSION_NAME', 'PORTFOLYO_SESSION'),
        // Varsayılan GÜVENLİ: yerel HTTP testinde .env içinde false yapılmalıdır.
        'secure'    => Y::envBool('SESSION_SECURE', true),
        'ip_bagla'  => Y::envBool('SESSION_IP_BAGLA', true),
        'ua_bagla'  => Y::envBool('SESSION_UA_BAGLA', true),
        'bosta_dk'  => max(1, (int) Y::env('SESSION_BOSTA_DK', 30)),
        'mutlak_dk' => max(1, (int) Y::env('SESSION_MUTLAK_DK', 480)),
    ],
    'guvenlik' => [
        // Yalnızca X-Forwarded-For başlığını ezen güvenilir bir ters vekil arkasındaysanız true yapın.
        'guvenilir_proxy' => Y::envBool('GUVENILIR_PROXY', false),
        'hsts'            => Y::envBool('HSTS', false),
    ],
    'giris' => [
        'maks_deneme' => 5,    // kimlik+IP başına
        'ip_maks'     => 20,   // IP başına
        'pencere_dk'  => 15,
    ],
    'yukleme' => [
        // Görsel boyut sınırı (varsayılan 5 MB) ve video boyut sınırı (varsayılan 50 MB).
        // Not: PHP'nin upload_max_filesize ve post_max_size değerleri de bu sınırlardan büyük olmalıdır.
        'maks_bayt'       => max(1, (int) Y::env('YUKLEME_MAX_BOYUT_MB', 5)) * 1024 * 1024,
        'video_maks_bayt' => max(1, (int) Y::env('YUKLEME_VIDEO_MAX_MB', 50)) * 1024 * 1024,
        'dizin'           => KOK . '/storage/yuklemeler',
    ],
    // İletişim formu bildirimleri için SMTP. MAIL_HOST boşsa e-posta gönderilmez (mesajlar yine panelde görünür).
    'posta' => [
        'host'      => (string) Y::env('MAIL_HOST', ''),
        'port'      => (int) Y::env('MAIL_PORT', 587),
        // tls = STARTTLS (587), ssl = örtülü TLS (465), yok = şifresiz (yalnızca yerel/test)
        'guvenli'   => strtolower((string) Y::env('MAIL_SIFRELEME', 'tls')),
        'kullanici' => (string) Y::env('MAIL_KULLANICI', ''),
        'sifre'     => (string) Y::env('MAIL_SIFRE', ''),
        'gonderen'  => (string) Y::env('MAIL_GONDEREN', ''),
        'alici'     => (string) Y::env('MAIL_ALICI', ''),
    ],
    // Tarayıcıdan kurulum sihirbazı (SSH olmayan hostingler). Boş = KAPALI. Kurulumdan sonra bu değeri .env'den silin.
    'kurulum' => [
        'token' => (string) Y::env('KURULUM_TOKEN', ''),
    ],
    'yedek' => [
        'dizin'  => KOK . '/storage/yedekler',
        'sakla'  => max(1, (int) Y::env('YEDEK_SAKLA', 14)),
    ],
];
