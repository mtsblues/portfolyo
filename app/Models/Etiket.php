<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Dogrulayici;
use App\Core\Model;
use App\Core\Veritabani;

final class Etiket extends Model
{
    protected const TABLO = 'etiketler';

    /** @return list<array<string,mixed>> */
    public static function tumu(): array
    {
        return Veritabani::hepsi(
            'SELECT e.*, (SELECT COUNT(*) FROM makale_etiket me WHERE me.etiket_id = e.id) AS makale_sayisi
             FROM etiketler e ORDER BY e.ad ASC'
        );
    }

    /** @return array<string,mixed>|null */
    public static function slugIle(string $slug): ?array
    {
        return Veritabani::tek('SELECT * FROM etiketler WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function olustur(string $ad, string $slug): int
    {
        Veritabani::calistir('INSERT INTO etiketler (ad, slug) VALUES (?, ?)', [$ad, $slug]);
        return Veritabani::sonId();
    }

    public static function guncelle(int $id, string $ad, string $slug): void
    {
        Veritabani::calistir('UPDATE etiketler SET ad = ?, slug = ? WHERE id = ?', [$ad, $slug, $id]);
    }

    /**
     * Virgülle ayrılmış etiket adlarından kimlik listesi üretir (yoksa oluşturur). En çok 12 etiket.
     * @return list<int>
     */
    public static function adlardanKimlikler(string $virgullu): array
    {
        $kimlikler = [];
        $gorulen = [];
        foreach (explode(',', $virgullu) as $ham) {
            $ad = trim((string) preg_replace('/\s+/u', ' ', $ham));
            if ($ad === '' || mb_strlen($ad, 'UTF-8') > 80) {
                continue;
            }
            $slug = Dogrulayici::slugOlustur($ad);
            if ($slug === '' || isset($gorulen[$slug])) {
                continue;
            }
            $gorulen[$slug] = true;
            $var = self::slugIle($slug);
            $kimlikler[] = $var !== null ? (int) $var['id'] : self::olustur($ad, $slug);
            if (count($kimlikler) >= 12) {
                break;
            }
        }
        return $kimlikler;
    }
}
