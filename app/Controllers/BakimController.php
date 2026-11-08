<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Bakim;
use App\Core\Denetleyici;
use App\Core\HizSiniri;

/**
 * Web-cron ucu: zamanlanmış görev (cPanel "Cron Jobs" + curl, ücretsiz cron servisleri) için.
 * BAKIM_TOKEN .env'de tanımlı değilse uç KAPALIDIR (404). Token sabit zamanlı karşılaştırılır.
 */
final class BakimController extends Denetleyici
{
    public function calistir(string $anahtar): void
    {
        $token = (string) config('bakim.token', '');
        // Kapalıyken ve yanlış anahtarda aynı 404: ucun varlığı sızmaz.
        if (strlen($token) < 24 || !hash_equals($token, $anahtar) || !HizSiniri::izinVer('bakim-web', 12, 3600)) {
            $this->hata(404);
            return;
        }
        $ozet = Bakim::calistir();
        header('Cache-Control: no-store');
        $this->json(['basarili' => true, 'ozet' => $ozet]);
    }
}
