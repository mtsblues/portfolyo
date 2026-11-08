<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use Throwable;

/** Şablon motoru: app/Views altındaki PHP şablonlarını izole kapsamda çizer. */
final class Gorunum
{
    /** @param array<string,mixed> $veri */
    public static function render(string $sablon, array $veri = [], string $layout = 'main'): string
    {
        $icerik = self::cizdir($sablon, $veri);
        if ($layout === '') {
            return $icerik;
        }
        return self::cizdir('layouts/' . $layout, $veri + ['icerik' => $icerik]);
    }

    /** @param array<string,mixed> $veri */
    public static function partial(string $ad, array $veri = []): void
    {
        echo self::cizdir('partials/' . $ad, $veri);
    }

    /** @param array<string,mixed> $veri */
    private static function cizdir(string $sablon, array $veri): string
    {
        if (preg_match('#^[a-z0-9_]+(?:/[a-z0-9_]+)*$#', $sablon) !== 1) {
            throw new InvalidArgumentException('Geçersiz şablon adı.');
        }
        $dosya = KOK . '/app/Views/' . $sablon . '.php';
        if (!is_file($dosya)) {
            throw new InvalidArgumentException('Şablon bulunamadı: ' . $sablon);
        }
        $cizici = static function (string $__dosya, array $__veri): void {
            extract($__veri, EXTR_SKIP);
            require $__dosya;
        };
        ob_start();
        try {
            $cizici($dosya, $veri);
        } catch (Throwable $t) {
            ob_end_clean();
            throw $t;
        }
        return (string) ob_get_clean();
    }
}
