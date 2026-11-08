<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Gorunum;
use App\Core\Guvenlik;
use App\Core\Oturum;
use App\Core\Yapilandirma;
use App\Core\Yonlendirici;

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    exit('PHP 8.2 veya üzeri gereklidir.');
}

/** PSR-4 benzeri otomatik yükleyici: App\X\Y -> app/X/Y.php */
spl_autoload_register(static function (string $sinif): void {
    if (!str_starts_with($sinif, 'App\\')) {
        return;
    }
    $yol = KOK . '/app/' . str_replace('\\', '/', substr($sinif, 4)) . '.php';
    if (is_file($yol)) {
        require $yol;
    }
});

require_once KOK . '/app/helpers/fonksiyonlar.php';

/** Uygulamayı başlatır ve isteği işler. */
function uygulamayi_calistir(): void
{
    Yapilandirma::yukle(KOK . '/.env');
    Yapilandirma::configAyarla(require KOK . '/config/config.php');

    $debug = (bool) config('uygulama.debug', false);
    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    @mkdir(KOK . '/storage/logs', 0750, true);
    ini_set('error_log', KOK . '/storage/logs/php-hata.log');
    date_default_timezone_set((string) config('uygulama.zaman_dilimi', 'Europe/Istanbul'));
    mb_internal_encoding('UTF-8');

    ob_start();

    $hataGoster = static function (Throwable $t) use ($debug): void {
        gunluk(get_class($t) . ': ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        try {
            echo Gorunum::render('hata/hata', [
                'kod'         => 500,
                'baslik'      => 'Sunucu hatası',
                'mesaj'       => $debug ? $t->getMessage() : '',
                'sayfaBaslik' => 'Sunucu hatası',
            ]);
        } catch (Throwable) {
            echo '<!doctype html><meta charset="utf-8"><title>500</title><h1>Sunucu hatası</h1>';
        }
    };
    set_exception_handler($hataGoster);
    register_shutdown_function(static function () use ($hataGoster): void {
        $h = error_get_last();
        if ($h !== null && in_array($h['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $hataGoster(new ErrorException($h['message'], 0, $h['type'], $h['file'], $h['line']));
        }
    });

    $metot = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $yonlendirici = new Yonlendirici(temel_yol());
    $yol = $yonlendirici->yolNormallestir($uri) ?? '/';
    $adminMi = $yol === '/admin' || str_starts_with($yol, '/admin/');

    Guvenlik::basliklariGonder($adminMi);
    if ($adminMi) {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
    } else {
        // Dinamik sayfalar oturum/CSRF içerir: paylaşımlı önbelleklerde saklanmasın.
        header('Cache-Control: private, no-cache');
    }
    header('Content-Type: text/html; charset=utf-8');

    Oturum::baslat();

    $hataSayfasi = static function (int $kod) use ($metot): void {
        if ($metot !== 'GET' && $metot !== 'HEAD' && (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code($kod);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['basarili' => false, 'hata' => $kod === 403 ? 'CSRF doğrulaması başarısız.' : 'İstek reddedildi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $basliklar = [403 => 'Erişim reddedildi', 404 => 'Sayfa bulunamadı', 405 => 'İzin verilmeyen yöntem'];
        http_response_code($kod);
        echo Gorunum::render('hata/hata', [
            'kod'         => $kod,
            'baslik'      => $basliklar[$kod] ?? 'Hata',
            'mesaj'       => $kod === 403 ? 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.' : '',
            'sayfaBaslik' => ($basliklar[$kod] ?? 'Hata') . ' · ' . $kod,
        ]);
    };

    $yonlendirici->bulunamadiIsleyici(static fn() => $hataSayfasi(404));
    $yonlendirici->izinVerilmediIsleyici(static fn(array $izinli) => $hataSayfasi(405));

    // POST isteklerinin tamamı CSRF doğrulamasına tabidir (giriş, iletişim, tüm admin işlemleri)
    $yonlendirici->ara(static function (string $m, string $y) use ($hataSayfasi): void {
        if ($m === 'POST' && !Csrf::istegiDogrula()) {
            $hataSayfasi(403);
            exit;
        }
    });

    (require KOK . '/config/rotalar.php')($yonlendirici);
    $yonlendirici->calistir($metot, $uri);

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}
