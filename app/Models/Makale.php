<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

final class Makale extends Model
{
    protected const TABLO = 'makaleler';

    private const YAYIN = "m.durum = 'yayinda' AND m.yayin_tarihi <= UTC_TIMESTAMP()";

    /**
     * @param array{kategori_id?:int,etiket_id?:int,arama?:string} $filtre
     * @return array{0:string,1:list<mixed>}
     */
    private static function kosul(array $filtre): array
    {
        $where = ' WHERE ' . self::YAYIN;
        $p = [];
        if (isset($filtre['kategori_id'])) {
            $where .= ' AND m.kategori_id = ?';
            $p[] = (int) $filtre['kategori_id'];
        }
        if (isset($filtre['etiket_id'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM makale_etiket me WHERE me.makale_id = m.id AND me.etiket_id = ?)';
            $p[] = (int) $filtre['etiket_id'];
        }
        if (isset($filtre['arama']) && $filtre['arama'] !== '') {
            $kac = '%' . addcslashes($filtre['arama'], '%_\\') . '%';
            $where .= ' AND (m.baslik LIKE ? OR m.ozet LIKE ? OR m.icerik LIKE ?)';
            array_push($p, $kac, $kac, $kac);
        }
        return [$where, $p];
    }

    /** @param array{kategori_id?:int,etiket_id?:int,arama?:string} $filtre */
    public static function yayindaSay(array $filtre = []): int
    {
        [$where, $p] = self::kosul($filtre);
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM makaleler m' . $where, $p);
    }

    /**
     * @param array{kategori_id?:int,etiket_id?:int,arama?:string} $filtre
     * @return list<array<string,mixed>>
     */
    public static function yayindaListe(int $limit, int $offset, array $filtre = []): array
    {
        [$where, $p] = self::kosul($filtre);
        $p[] = $limit;
        $p[] = $offset;
        return Veritabani::hepsi(
            'SELECT m.id, m.baslik, m.slug, m.ozet, m.okunma_suresi, m.kapak_gorsel, m.yayin_tarihi, m.one_cikan,
                    k.ad AS kategori_ad, k.slug AS kategori_slug
             FROM makaleler m JOIN kategoriler k ON k.id = m.kategori_id'
            . $where . ' ORDER BY m.yayin_tarihi DESC, m.id DESC LIMIT ? OFFSET ?',
            $p
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
        return Veritabani::tek(
            'SELECT m.*, k.ad AS kategori_ad, k.slug AS kategori_slug, u.ad_soyad AS yazar
             FROM makaleler m
             JOIN kategoriler k ON k.id = m.kategori_id
             JOIN kullanicilar u ON u.id = m.kullanici_id
             WHERE m.slug = ? AND ' . self::YAYIN . ' LIMIT 1',
            [$slug]
        );
    }

    /** Aynı kategoriden ilgili yazılar. @return list<array<string,mixed>> */
    public static function ilgili(int $kategoriId, int $haricId, int $limit = 3): array
    {
        return Veritabani::hepsi(
            'SELECT m.id, m.baslik, m.slug, m.ozet, m.okunma_suresi, m.kapak_gorsel, m.yayin_tarihi, m.one_cikan,
                    k.ad AS kategori_ad, k.slug AS kategori_slug
             FROM makaleler m JOIN kategoriler k ON k.id = m.kategori_id
             WHERE ' . self::YAYIN . ' AND m.kategori_id = ? AND m.id <> ?
             ORDER BY m.yayin_tarihi DESC LIMIT ?',
            [$kategoriId, $haricId, $limit]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function etiketleri(int $makaleId): array
    {
        return Veritabani::hepsi(
            'SELECT e.id, e.ad, e.slug FROM etiketler e JOIN makale_etiket me ON me.etiket_id = e.id
             WHERE me.makale_id = ? ORDER BY e.ad ASC',
            [$makaleId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function adminListe(int $limit, int $offset): array
    {
        return Veritabani::hepsi(
            'SELECT m.id, m.baslik, m.slug, m.durum, m.one_cikan, m.yayin_tarihi, m.guncellenme, k.ad AS kategori_ad
             FROM makaleler m JOIN kategoriler k ON k.id = m.kategori_id
             ORDER BY m.id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /** RSS beslemesi için son makaleler (içerik dahil). @return list<array<string,mixed>> */
    public static function rssIcin(int $limit): array
    {
        return Veritabani::hepsi(
            'SELECT m.slug, m.baslik, m.ozet, m.icerik, m.yayin_tarihi, k.ad AS kategori_ad
             FROM makaleler m JOIN kategoriler k ON k.id = m.kategori_id
             WHERE ' . self::YAYIN . ' ORDER BY m.yayin_tarihi DESC, m.id DESC LIMIT ?',
            [$limit]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function sitemapIcin(): array
    {
        return Veritabani::hepsi(
            'SELECT m.slug, m.guncellenme FROM makaleler m WHERE ' . self::YAYIN . ' ORDER BY m.id DESC LIMIT 5000'
        );
    }

    public static function durumSayisi(string $durum): int
    {
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM makaleler WHERE durum = ?', [$durum]);
    }

    public static function kategoridekiSayi(int $kategoriId): int
    {
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM makaleler WHERE kategori_id = ?', [$kategoriId]);
    }

    /** @param array<string,mixed> $v */
    public static function olustur(array $v): int
    {
        Veritabani::calistir(
            'INSERT INTO makaleler (kategori_id, kullanici_id, baslik, slug, ozet, icerik, durum, one_cikan,
                                    okunma_suresi, kapak_gorsel, anahtar_kelimeler, yayin_tarihi)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $v['kategori_id'], $v['kullanici_id'], $v['baslik'], $v['slug'], $v['ozet'], $v['icerik'],
                $v['durum'], $v['one_cikan'], $v['okunma_suresi'], $v['kapak_gorsel'], $v['anahtar_kelimeler'],
                $v['yayin_tarihi'],
            ]
        );
        return Veritabani::sonId();
    }

    /** @param array<string,mixed> $v */
    public static function guncelle(int $id, array $v): void
    {
        Veritabani::calistir(
            'UPDATE makaleler SET kategori_id = ?, baslik = ?, slug = ?, ozet = ?, icerik = ?, durum = ?,
                    one_cikan = ?, okunma_suresi = ?, kapak_gorsel = ?, anahtar_kelimeler = ?, yayin_tarihi = ?
             WHERE id = ?',
            [
                $v['kategori_id'], $v['baslik'], $v['slug'], $v['ozet'], $v['icerik'], $v['durum'],
                $v['one_cikan'], $v['okunma_suresi'], $v['kapak_gorsel'], $v['anahtar_kelimeler'],
                $v['yayin_tarihi'], $id,
            ]
        );
    }

    /** @param list<int> $etiketKimlikleri */
    public static function etiketleriSenkronla(int $makaleId, array $etiketKimlikleri): void
    {
        Veritabani::calistir('DELETE FROM makale_etiket WHERE makale_id = ?', [$makaleId]);
        foreach (array_unique($etiketKimlikleri) as $eid) {
            Veritabani::calistir('INSERT INTO makale_etiket (makale_id, etiket_id) VALUES (?, ?)', [$makaleId, (int) $eid]);
        }
    }

    public static function okunmaSuresi(string $icerik): int
    {
        $kelime = preg_match_all('/\S+/u', $icerik);
        return max(1, (int) ceil(($kelime === false ? 0 : $kelime) / 200));
    }
}
