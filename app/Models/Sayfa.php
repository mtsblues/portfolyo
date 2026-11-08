<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

/** Serbest sayfalar (gizlilik, aydınlatma metni, "Bu site hakkında" vb.). */
final class Sayfa extends Model
{
    protected const TABLO = 'sayfalar';

    /** @return array<string,mixed>|null */
    public static function yayindaSlugIle(string $slug): ?array
    {
        return Veritabani::tek("SELECT * FROM sayfalar WHERE slug = ? AND durum = 'yayinda' LIMIT 1", [$slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function menu(): array
    {
        return self::guvenli("SELECT baslik, slug FROM sayfalar WHERE durum = 'yayinda' AND menude = 1 ORDER BY sira, id");
    }

    /** @return list<array<string,mixed>> */
    public static function altbilgi(): array
    {
        return self::guvenli("SELECT baslik, slug FROM sayfalar WHERE durum = 'yayinda' AND altbilgide = 1 ORDER BY sira, id");
    }

    /** @return list<array<string,mixed>> */
    public static function adminListe(): array
    {
        return Veritabani::hepsi('SELECT id, baslik, slug, durum, menude, altbilgide, sira, guncellenme FROM sayfalar ORDER BY sira, id');
    }

    /** @return list<array<string,mixed>> */
    public static function sitemapIcin(): array
    {
        return self::guvenli("SELECT slug, guncellenme FROM sayfalar WHERE durum = 'yayinda' ORDER BY id");
    }

    public static function yayindaMi(string $slug): bool
    {
        return $slug !== '' && self::yayindaSlugIle($slug) !== null;
    }

    /** @param array<string,mixed> $v */
    public static function olustur(array $v): int
    {
        Veritabani::calistir(
            'INSERT INTO sayfalar (baslik, slug, icerik, durum, menude, altbilgide, sira, meta_aciklama) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['baslik'], $v['slug'], $v['icerik'], $v['durum'], $v['menude'], $v['altbilgide'], $v['sira'], $v['meta_aciklama']]
        );
        return Veritabani::sonId();
    }

    /** @param array<string,mixed> $v */
    public static function guncelle(int $id, array $v): void
    {
        Veritabani::calistir(
            'UPDATE sayfalar SET baslik = ?, slug = ?, icerik = ?, durum = ?, menude = ?, altbilgide = ?, sira = ?, meta_aciklama = ? WHERE id = ?',
            [$v['baslik'], $v['slug'], $v['icerik'], $v['durum'], $v['menude'], $v['altbilgide'], $v['sira'], $v['meta_aciklama'], $id]
        );
    }

    /** Menü/altbilgi sorguları veritabanı hatasında sayfayı bozmasın. @return list<array<string,mixed>> */
    private static function guvenli(string $sql): array
    {
        try {
            return Veritabani::hepsi($sql);
        } catch (\Throwable) {
            return [];
        }
    }
}
