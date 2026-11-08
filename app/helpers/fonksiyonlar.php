<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Gorunum;
use App\Core\Guvenlik;
use App\Core\Oturum;
use App\Core\Yapilandirma;

/** XSS koruması: tüm çıktılar bu fonksiyondan geçer. */
function e(mixed $deger): string
{
    return htmlspecialchars((string) ($deger ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function env(string $anahtar, mixed $varsayilan = null): mixed
{
    return Yapilandirma::env($anahtar, $varsayilan);
}

function config(string $anahtar, mixed $varsayilan = null): mixed
{
    return Yapilandirma::al($anahtar, $varsayilan);
}

/** Uygulama bir alt klasörde çalışıyorsa (örn. /portfolyo/public) o klasör yolu. */
function temel_yol(): string
{
    if (PHP_SAPI === 'cli') {
        return '';
    }
    return temel_yol_hesapla((string) ($_SERVER['SCRIPT_NAME'] ?? ''), (string) ($_SERVER['REQUEST_URI'] ?? ''));
}

/**
 * Taban yolu hesaplar (saf fonksiyon; her barındırma biçimi için test edilir):
 *  - DocumentRoot = public/ (önerilen)         : SCRIPT_NAME /index.php            → ''
 *  - Alan adı kökünde tüm proje + kök .htaccess : SCRIPT_NAME /public/index.php, istek /blog → ''
 *  - Alt klasör (örn. XAMPP /portfolyo)         : SCRIPT_NAME /portfolyo/public/index.php, istek /portfolyo/blog → '/portfolyo'
 *  - Alt klasör, public/ doğrudan çağrılırsa    : istek /portfolyo/public/blog → '/portfolyo/public'
 */
function temel_yol_hesapla(string $betik, string $istekUri): string
{
    $dizin = str_replace('\\', '/', dirname($betik));
    if ($dizin === '/' || $dizin === '.' || $dizin === '') {
        return '';
    }
    $dizin = rtrim($dizin, '/');

    // Kök .htaccess isteği iç olarak public/'e yönlendiriyorsa SCRIPT_NAME ".../public/index.php" olur ama
    // tarayıcıdaki yol "public" içermez. Bu durumda taban yol "/public" eki olmadan hesaplanır.
    if (str_ends_with($dizin, '/public')) {
        $istek = (string) parse_url($istekUri, PHP_URL_PATH);
        if ($istek !== '' && $istek !== $dizin && !str_starts_with($istek, $dizin . '/')) {
            $dizin = substr($dizin, 0, -strlen('/public'));
        }
    }
    return $dizin;
}

function url(string $yol = ''): string
{
    return temel_yol() . '/' . ltrim($yol, '/');
}

function asset(string $dosya): string
{
    $dosya = ltrim($dosya, '/');
    $tam = KOK . '/public/assets/' . $dosya;
    $surum = is_file($tam) ? '?v=' . substr(md5((string) filemtime($tam)), 0, 8) : '';
    return temel_yol() . '/assets/' . $dosya . $surum;
}

/** Canonical / sitemap için mutlak URL. APP_URL yoksa göreli yol döner. */
function mutlak_url(string $yol = ''): string
{
    $taban = (string) config('uygulama.url', '');
    return $taban !== '' ? $taban . '/' . ltrim($yol, '/') : url($yol);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(Csrf::token()) . '">';
}

function nonce(): string
{
    return Guvenlik::nonce();
}

/** Önceki form girdisi (doğrulama hatasından sonra). */
function eski(string $anahtar, mixed $varsayilan = ''): string
{
    $eski = Oturum::flashGelen('eski');
    if (is_array($eski) && array_key_exists($anahtar, $eski)) {
        return (string) $eski[$anahtar];
    }
    return (string) $varsayilan;
}

function hata_mesaji(string $alan): string
{
    $hatalar = Oturum::flashGelen('hatalar');
    if (is_array($hatalar) && isset($hatalar[$alan])) {
        return (string) $hatalar[$alan];
    }
    return '';
}

/** Site ayarı (veritabanı erişilemezse varsayılanı döner). */
function ayar(string $anahtar, string $varsayilan = ''): string
{
    $deger = \App\Models\Ayar::al($anahtar, $varsayilan);
    return $deger !== '' ? $deger : $varsayilan;
}

function partial(string $ad, array $veri = []): void
{
    Gorunum::partial($ad, $veri);
}

/** URL yolunun (temel yol çıkarılmış) güncel hâli. */
function gecerli_yol(): string
{
    $yol = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $taban = temel_yol();
    if ($taban !== '' && str_starts_with($yol, $taban)) {
        $yol = substr($yol, strlen($taban));
    }
    return '/' . trim($yol, '/');
}

function menu_aktif(string $oneki): string
{
    $yol = gecerli_yol();
    if ($oneki === '/') {
        return $yol === '/' ? ' aria-current="page"' : '';
    }
    return ($yol === $oneki || str_starts_with($yol, $oneki . '/')) ? ' aria-current="page"' : '';
}

function sayfa_no(): int
{
    $s = $_GET['sayfa'] ?? '1';
    if (!is_string($s) || preg_match('/^[1-9][0-9]{0,5}$/', $s) !== 1) {
        return 1;
    }
    return (int) $s;
}

function istemci_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (config('guvenlik.guvenilir_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $liste = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
        $son = end($liste);
        if (is_string($son) && filter_var($son, FILTER_VALIDATE_IP) !== false) {
            $ip = $son;
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

/** IP'yi VARBINARY(16) için ikili forma çevirir. */
function ip_ikili(?string $ip = null): string
{
    $ikili = @inet_pton($ip ?? istemci_ip());
    return $ikili === false ? "\0\0\0\0" : $ikili;
}

/**
 * Kişisel veriyi azaltmak için "ip_saklama" ayarına göre IP'yi (tam | kısaltılmış | hiç) ikili forma çevirir.
 * Mesaj ve denetim kayıtları bunu kullanır; giriş kilitleri güvenlik için tam IP'yi (kısa süre) kullanmaya devam eder.
 */
function ip_sakla_ikili(?string $ip = null): string
{
    $ikili = ip_ikili($ip);
    return match (ayar('ip_saklama', 'tam')) {
        'yok'   => strlen($ikili) === 16 ? str_repeat("\0", 16) : "\0\0\0\0",
        'kisa'  => strlen($ikili) === 4 ? substr($ikili, 0, 3) . "\0" : substr($ikili, 0, 8) . str_repeat("\0", 8),
        default => $ikili,
    };
}

function ip_metin(string $ikili): string
{
    $m = @inet_ntop($ikili);
    return $m === false ? '' : $m;
}

function ozet_kes(string $metin, int $uzunluk = 160): string
{
    $metin = trim((string) preg_replace('/\s+/u', ' ', $metin));
    return mb_strimwidth($metin, 0, $uzunluk, '…', 'UTF-8');
}

/** UTC DATETIME -> yerel saat dilimi, Türkçe ay adıyla. */
function tarih(?string $utc, bool $saat = false): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    static $aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    try {
        $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $d = $d->setTimezone(new DateTimeZone((string) config('uygulama.zaman_dilimi', 'Europe/Istanbul')));
    } catch (Throwable) {
        return '';
    }
    $s = $d->format('j') . ' ' . $aylar[(int) $d->format('n')] . ' ' . $d->format('Y');
    return $saat ? $s . ' ' . $d->format('H:i') : $s;
}

/** datetime-local girdisi (yerel) -> UTC 'Y-m-d H:i:s'. Geçersizse null. */
function yerel_den_utc(string $deger): ?string
{
    $deger = trim($deger);
    if ($deger === '') {
        return null;
    }
    try {
        $d = new DateTimeImmutable(str_replace('T', ' ', $deger), new DateTimeZone((string) config('uygulama.zaman_dilimi', 'Europe/Istanbul')));
    } catch (Throwable) {
        return null;
    }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** UTC DATETIME -> datetime-local değeri. */
function utc_den_yerel(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $d = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) config('uygulama.zaman_dilimi', 'Europe/Istanbul')));
    } catch (Throwable) {
        return '';
    }
    return $d->format('Y-m-d\TH:i');
}

function gunluk(string $mesaj): void
{
    $dizin = KOK . '/storage/logs';
    if (!is_dir($dizin)) {
        @mkdir($dizin, 0750, true);
    }
    $satir = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . str_replace(["\r", "\n"], ' ', $mesaj) . PHP_EOL;
    @file_put_contents($dizin . '/uygulama.log', $satir, FILE_APPEND | LOCK_EX);
}

/** Sitenin kökü (şema + alan adı). APP_URL varsa ondan, yoksa güvenli biçimde doğrulanmış Host'tan. */
function site_kok_url(): string
{
    $taban = (string) config('uygulama.url', '');
    $p = $taban !== '' ? parse_url($taban) : false;
    if (is_array($p) && isset($p['scheme'], $p['host'])) {
        return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host) !== 1) {
        return '';
    }
    return (Guvenlik::https() ? 'https' : 'http') . '://' . $host;
}

/** Ayardaki görsel değeri (site içi /yuklemeler/... ya da https adresi) → kullanılabilir URL. */
function gorsel_url(string $deger): string
{
    $deger = trim($deger);
    if ($deger === '') {
        return '';
    }
    return $deger[0] === '/' ? url($deger) : $deger;
}

/** gorsel_url ile aynı, ancak site içi yollar mutlak (alan adlı) olur: og:image, RSS vb. için. */
function mutlak_gorsel(string $deger): string
{
    $u = gorsel_url($deger);
    return ($u !== '' && $u[0] === '/') ? site_kok_url() . $u : $u;
}

/** @return array<string,string> etiket => adres (yalnızca dolu ve http/https olanlar) */
function sosyal_baglantilar(): array
{
    $harita = [
        'sosyal_github' => 'GitHub', 'sosyal_linkedin' => 'LinkedIn', 'sosyal_x' => 'X',
        'sosyal_youtube' => 'YouTube', 'sosyal_instagram' => 'Instagram',
    ];
    $cikti = [];
    foreach ($harita as $anahtar => $etiket) {
        $d = trim(ayar($anahtar));
        if ($d !== '' && preg_match('#^https?://#i', $d) === 1) {
            $cikti[$etiket] = $d;
        }
    }
    return $cikti;
}

/** Hakkımda sayfası etkin mi (içerik girilmiş mi)? */
function hakkimda_var(): bool
{
    return trim(ayar('hakkimda_icerik')) !== '';
}

/** JSON-LD (yapılandırılmış veri) için güvenli JSON: <script> bloğunu kırabilecek karakterler kaçışlanır. */
function jsonld_json(array $veri): string
{
    return (string) json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
