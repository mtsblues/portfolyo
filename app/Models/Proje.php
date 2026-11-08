<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

final class Proje extends Model
{
    protected const TABLO = 'projeler';

    public const DURUMLAR = [
        'aktif'        => 'Aktif',
        'devam_ediyor' => 'Devam ediyor',
        'tamamlandi'   => 'Tamamlandı',
        'arsiv'        => 'Arşiv',
    ];

    public static function yayindaSay(): int
    {
        return (int) Veritabani::skaler("SELECT COUNT(*) FROM projeler WHERE durum <> 'arsiv'");
    }

    /** @return list<array<string,mixed>> */
    public static function yayindaListe(int $limit, int $offset): array
    {
        return Veritabani::hepsi(
            "SELECT id, baslik, slug, aciklama, gorsel, proje_url, github_url, one_cikan, durum, tamamlanma_tarihi
             FROM projeler WHERE durum <> 'arsiv'
             ORDER BY sira ASC, id DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function son(int $limit): array
    {
        return self::yayindaListe($limit, 0);
    }

    /** @return array<string,mixed>|null */
    public static function yayindaSlugIle(string $slug): ?array
    {
        return Veritabani::tek("SELECT * FROM projeler WHERE slug = ? AND durum <> 'arsiv' LIMIT 1", [$slug]);
    }

    /** @return list<array<string,mixed>> */
    public static function adminListe(int $limit, int $offset): array
    {
        return Veritabani::hepsi(
            'SELECT id, baslik, slug, durum, one_cikan, sira, guncellenme FROM projeler
             ORDER BY sira ASC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function sitemapIcin(): array
    {
        return Veritabani::hepsi("SELECT slug, guncellenme FROM projeler WHERE durum <> 'arsiv' ORDER BY id DESC LIMIT 5000");
    }

    /** @param array<string,mixed> $v */
    public static function olustur(array $v): int
    {
        Veritabani::calistir(
            'INSERT INTO projeler (baslik, slug, aciklama, icerik, gorsel, proje_url, github_url, one_cikan, durum, sira, tamamlanma_tarihi)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $v['baslik'], $v['slug'], $v['aciklama'], $v['icerik'], $v['gorsel'], $v['proje_url'],
                $v['github_url'], $v['one_cikan'], $v['durum'], $v['sira'], $v['tamamlanma_tarihi'],
            ]
        );
        return Veritabani::sonId();
    }

    /** @param array<string,mixed> $v */
    public static function guncelle(int $id, array $v): void
    {
        Veritabani::calistir(
            'UPDATE projeler SET baslik = ?, slug = ?, aciklama = ?, icerik = ?, gorsel = ?, proje_url = ?,
                    github_url = ?, one_cikan = ?, durum = ?, sira = ?, tamamlanma_tarihi = ?
             WHERE id = ?',
            [
                $v['baslik'], $v['slug'], $v['aciklama'], $v['icerik'], $v['gorsel'], $v['proje_url'],
                $v['github_url'], $v['one_cikan'], $v['durum'], $v['sira'], $v['tamamlanma_tarihi'], $id,
            ]
        );
    }
}
