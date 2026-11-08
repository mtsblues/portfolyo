<?php
declare(strict_types=1);

/**
 * Front Controller — tüm istekler buradan geçer.
 */

define('KOK', dirname(__DIR__));

// PHP yerleşik sunucusu (php -S ... public/index.php): gerçek statik dosyaları doğrudan sun.
if (PHP_SAPI === 'cli-server') {
    $istenen = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($istenen) && $istenen !== '/' && (str_starts_with($istenen, '/assets/') || $istenen === '/robots.txt')) {
        // Kodu çözülmüş ham yol ile yol gezdirme girişimlerini reddet (\ ve .. dahil).
        $cozulmus = rawurldecode($istenen);
        if (
            str_contains($cozulmus, '..')
            || str_contains($cozulmus, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $cozulmus) === 1
        ) {
            http_response_code(400);
            exit('Geçersiz istek.');
        }
        $tamYol = realpath(__DIR__ . $cozulmus);
        $kokPath = realpath(__DIR__);
        if ($tamYol !== false && $kokPath !== false && str_starts_with($tamYol, $kokPath . DIRECTORY_SEPARATOR) && is_file($tamYol)) {
            return false;
        }
    }
}

require KOK . '/app/bootstrap.php';
uygulamayi_calistir();
