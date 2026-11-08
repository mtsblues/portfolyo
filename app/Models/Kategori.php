<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

final class Kategori extends Model
{
    protected const TABLO = 'kategoriler';

    /** Yayındaki makale sayısıyla birlikte. @return list<array<string,mixed>> */
    public static function tumu(): array
    {
        return Veritabani::hepsi(
            "SELECT k.*, (SELECT COUNT(*) FROM makaleler m WHERE m.kategori_id = k.id) AS makale_sayisi
             FROM kategoriler k ORDER BY k.ad ASC"
        );
    }

    /** @return list<array<string,mixed>> */
    public static function yayindakiler(): array
    {
        return Veritabani::hepsi(
            "SELECT k.id, k.ad, k.slug, COUNT(m.id) AS makale_sayisi
             FROM kategoriler k
             JOIN makaleler m ON m.kategori_id = k.id AND m.durum = 'yayinda' AND m.yayin_tarihi <= UTC_TIMESTAMP()
             GROUP BY k.id, k.ad, k.slug ORDER BY k.ad ASC"
        );
    }

    /** @return array<string,mixed>|null */
    public static function slugIle(string $slug): ?array
    {
        return Veritabani::tek('SELECT * FROM kategoriler WHERE slug = ? LIMIT 1', [$slug]);
    }

    public static function olustur(string $ad, string $slug, ?string $aciklama): int
    {
        Veritabani::calistir('INSERT INTO kategoriler (ad, slug, aciklama) VALUES (?, ?, ?)', [$ad, $slug, $aciklama]);
        return Veritabani::sonId();
    }

    public static function guncelle(int $id, string $ad, string $slug, ?string $aciklama): void
    {
        Veritabani::calistir('UPDATE kategoriler SET ad = ?, slug = ?, aciklama = ? WHERE id = ?', [$ad, $slug, $aciklama, $id]);
    }
}
