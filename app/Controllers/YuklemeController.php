<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Yukleme;

/**
 * Web root dışındaki yüklemeleri güvenli başlıklarla sunar. {dosya} rota deseni zaten
 * ^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm)$ ile sınırlıdır. Videolar için HTTP Range (kısmi içerik)
 * desteklenir; böylece tarayıcı videoyu ileri/geri sarabilir.
 */
final class YuklemeController extends Denetleyici
{
    public function goster(string $dosya): void
    {
        $yol = Yukleme::yol($dosya);
        $mime = Yukleme::mimeTuru($dosya);
        if ($yol === null || $mime === null) {
            $this->hata(404);
            return;
        }
        $toplam = (int) filesize($yol);
        $bas = 0;
        $son = $toplam - 1;
        $kod = 200;

        $aralik = (string) ($_SERVER['HTTP_RANGE'] ?? '');
        if ($aralik !== '' && $toplam > 0) {
            // Tek aralık desteklenir: bytes=0-499 | bytes=500- | bytes=-500
            if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($aralik), $m) === 1 && ($m[1] !== '' || $m[2] !== '')) {
                if ($m[1] === '') {
                    $bas = max(0, $toplam - (int) $m[2]);
                } else {
                    $bas = (int) $m[1];
                    $son = $m[2] !== '' ? min((int) $m[2], $toplam - 1) : $toplam - 1;
                }
                if ($bas > $son || $bas >= $toplam) {
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    http_response_code(416);
                    header('Content-Range: bytes */' . $toplam);
                    return;
                }
                $kod = 206;
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($kod);
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . (string) ($son - $bas + 1));
        if ($kod === 206) {
            header('Content-Range: bytes ' . $bas . '-' . $son . '/' . $toplam);
        }
        header('Cache-Control: public, max-age=604800, immutable');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; sandbox");
        header('Content-Disposition: inline');

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD' || $toplam === 0) {
            return;
        }
        $f = fopen($yol, 'rb');
        if ($f === false) {
            return;
        }
        @set_time_limit(0);
        fseek($f, $bas);
        $kalan = $son - $bas + 1;
        while ($kalan > 0 && !feof($f) && !connection_aborted()) {
            $parca = fread($f, min(65536, $kalan));
            if ($parca === false || $parca === '') {
                break;
            }
            echo $parca;
            $kalan -= strlen($parca);
            flush();
        }
        fclose($f);
    }
}
