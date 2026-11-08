<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Veritabani;

/** Brute-force koruması: giriş denemelerini kaydeder ve kilit süresini hesaplar. */
final class GirisDenemesi
{
    public static function kaydet(string $kimlik, string $ipIkili, bool $basarili): void
    {
        Veritabani::calistir(
            'INSERT INTO giris_denemeleri (kimlik, ip_adresi, basarili) VALUES (?, ?, ?)',
            [mb_substr($kimlik, 0, 180, 'UTF-8'), $ipIkili, $basarili ? 1 : 0]
        );
        if (random_int(1, 50) === 1) {
            Veritabani::calistir('DELETE FROM giris_denemeleri WHERE deneme_zamani < (UTC_TIMESTAMP() - INTERVAL 2 DAY)');
        }
    }

    /**
     * Kilit varsa kalan saniyeyi, yoksa 0 döner.
     * Kimlik+IP için maks_deneme başarısız deneme VEYA IP için ip_maks başarısız deneme (pencere_dk içinde).
     */
    public static function kalanKilitSaniye(string $kimlik, string $ipIkili, int $maks, int $ipMaks, int $pencereDk): int
    {
        $pencereSn = $pencereDk * 60;
        $kimlik = mb_substr($kimlik, 0, 180, 'UTF-8');

        $a = Veritabani::tek(
            'SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MAX(deneme_zamani)) AS son, UNIX_TIMESTAMP() AS simdi
             FROM giris_denemeleri
             WHERE kimlik = ? AND ip_adresi = ? AND basarili = 0 AND deneme_zamani > (UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [$kimlik, $ipIkili, $pencereDk]
        );
        $b = Veritabani::tek(
            'SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MAX(deneme_zamani)) AS son, UNIX_TIMESTAMP() AS simdi
             FROM giris_denemeleri
             WHERE ip_adresi = ? AND basarili = 0 AND deneme_zamani > (UTC_TIMESTAMP() - INTERVAL ? MINUTE)',
            [$ipIkili, $pencereDk]
        );

        $kalan = 0;
        foreach ([[$a, $maks], [$b, $ipMaks]] as [$satir, $limit]) {
            if ($satir !== null && (int) $satir['c'] >= $limit && $satir['son'] !== null) {
                $kalan = max($kalan, (int) $satir['son'] + $pencereSn - (int) $satir['simdi']);
            }
        }
        return max(0, $kalan);
    }

    public static function basariliGirisSonrasiTemizle(string $kimlik, string $ipIkili): void
    {
        Veritabani::calistir(
            'DELETE FROM giris_denemeleri WHERE kimlik = ? AND ip_adresi = ? AND basarili = 0',
            [mb_substr($kimlik, 0, 180, 'UTF-8'), $ipIkili]
        );
    }

    public static function son24SaatBasarisiz(): int
    {
        return (int) Veritabani::skaler(
            'SELECT COUNT(*) FROM giris_denemeleri WHERE basarili = 0 AND deneme_zamani > (UTC_TIMESTAMP() - INTERVAL 1 DAY)'
        );
    }
}
