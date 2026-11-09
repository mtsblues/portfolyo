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

    /** Teknoloji filtresi: "PHP, MySQL" biçiminde saklanan listede tam eşleşme (büyük/küçük harf duyarsız). */
    private const TEKNOLOJI_KOSULU = "FIND_IN_SET(LOWER(?), REPLACE(LOWER(teknolojiler), ', ', ',')) > 0";

    public static function yayindaSay(?string $teknoloji = null): int
    {
        if ($teknoloji !== null && $teknoloji !== '') {
            return (int) Veritabani::skaler(
                "SELECT COUNT(*) FROM projeler WHERE durum <> 'arsiv' AND " . self::TEKNOLOJI_KOSULU,
                [$teknoloji]
            );
        }
        return (int) Veritabani::skaler("SELECT COUNT(*) FROM projeler WHERE durum <> 'arsiv'");
    }

    /**
     * Yayındaki projelerde kullanılan tüm teknolojiler (en çok kullanılan önce).
     * @return list<array{ad:string,sayi:int}>
     */
    public static function teknolojiler(): array
    {
        try {
            $satirlar = Veritabani::hepsi("SELECT teknolojiler FROM projeler WHERE durum <> 'arsiv' AND teknolojiler IS NOT NULL AND teknolojiler <> ''");
        } catch (\Throwable) {
            return [];
        }
        $say = [];
        $adlar = [];
        foreach ($satirlar as $r) {
            foreach (\App\Core\CvYardimci::teknolojiler((string) $r['teknolojiler']) as $t) {
                $k = mb_strtolower($t);
                $say[$k] = ($say[$k] ?? 0) + 1;
                $adlar[$k] ??= $t;
            }
        }
        arsort($say);
        $cikti = [];
        foreach ($say as $k => $n) {
            $cikti[] = ['ad' => $adlar[$k], 'sayi' => $n];
        }
        return $cikti;
    }

    /** Öne çıkan projeler; yoksa son projeler. @return list<array<string,mixed>> */
    public static function oneCikanlar(int $limit): array
    {
        try {
            $l = Veritabani::hepsi(
                "SELECT id, baslik, slug, aciklama, teknolojiler, proje_url, github_url FROM projeler
                 WHERE durum <> 'arsiv' AND one_cikan = 1 ORDER BY sira ASC, id DESC LIMIT ?",
                [$limit]
            );
            if ($l !== []) {
                return $l;
            }
            return Veritabani::hepsi(
                "SELECT id, baslik, slug, aciklama, teknolojiler, proje_url, github_url FROM projeler
                 WHERE durum <> 'arsiv' ORDER BY sira ASC, id DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public static function yayindaListe(int $limit, int $offset, ?string $teknoloji = null): array
    {
        $kosul = '';
        $p = [];
        if ($teknoloji !== null && $teknoloji !== '') {
            $kosul = ' AND ' . self::TEKNOLOJI_KOSULU;
            $p[] = $teknoloji;
        }
        return Veritabani::hepsi(
            "SELECT id, baslik, slug, aciklama, gorsel, proje_url, github_url, one_cikan, durum, tamamlanma_tarihi, teknolojiler
             FROM projeler WHERE durum <> 'arsiv'" . $kosul . '
             ORDER BY sira ASC, id DESC LIMIT ? OFFSET ?',
            array_merge($p, [$limit, $offset])
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
            'INSERT INTO projeler (baslik, slug, aciklama, icerik, gorsel, proje_url, github_url, one_cikan, durum, sira, tamamlanma_tarihi, teknolojiler, problem, cozum, sonuc)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $v['baslik'], $v['slug'], $v['aciklama'], $v['icerik'], $v['gorsel'], $v['proje_url'],
                $v['github_url'], $v['one_cikan'], $v['durum'], $v['sira'], $v['tamamlanma_tarihi'],
                $v['teknolojiler'], $v['problem'], $v['cozum'], $v['sonuc'],
            ]
        );
        return Veritabani::sonId();
    }

    /** @param array<string,mixed> $v */
    public static function guncelle(int $id, array $v): void
    {
        Veritabani::calistir(
            'UPDATE projeler SET baslik = ?, slug = ?, aciklama = ?, icerik = ?, gorsel = ?, proje_url = ?,
                    github_url = ?, one_cikan = ?, durum = ?, sira = ?, tamamlanma_tarihi = ?,
                    teknolojiler = ?, problem = ?, cozum = ?, sonuc = ?
             WHERE id = ?',
            [
                $v['baslik'], $v['slug'], $v['aciklama'], $v['icerik'], $v['gorsel'], $v['proje_url'],
                $v['github_url'], $v['one_cikan'], $v['durum'], $v['sira'], $v['tamamlanma_tarihi'],
                $v['teknolojiler'], $v['problem'], $v['cozum'], $v['sonuc'], $id,
            ]
        );
    }
}
