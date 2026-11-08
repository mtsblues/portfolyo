<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Veritabani;

/** Medya kütüphanesi kayıtları (dosyalar App\Core\Yukleme ile diske yazılır). */
final class Medya
{
    /** @param array{ad:?string,tur?:string,mime?:string,boyut?:int,genislik?:?int,yukseklik?:?int} $v */
    public static function ekle(array $v, ?int $kullaniciId = null): void
    {
        Veritabani::calistir(
            'INSERT IGNORE INTO medyalar (dosya, tur, mime, boyut, genislik, yukseklik, kullanici_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$v['ad'], $v['tur'] ?? 'gorsel', $v['mime'] ?? 'application/octet-stream', (int) ($v['boyut'] ?? 0),
             $v['genislik'] ?? null, $v['yukseklik'] ?? null, $kullaniciId]
        );
    }

    public static function say(?string $tur = null): int
    {
        if ($tur !== null) {
            return (int) Veritabani::skaler('SELECT COUNT(*) FROM medyalar WHERE tur = ?', [$tur]);
        }
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM medyalar');
    }

    /** @return list<array<string,mixed>> */
    public static function liste(int $limit, int $offset, ?string $tur = null): array
    {
        $where = $tur !== null ? 'WHERE tur = ?' : '';
        $p = $tur !== null ? [$tur] : [];
        array_push($p, $limit, $offset);
        return Veritabani::hepsi(
            'SELECT id, dosya, tur, mime, boyut, genislik, yukseklik, alt_metin, olusturulma FROM medyalar '
            . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?',
            $p
        );
    }

    /** @return array<string,mixed>|null */
    public static function idIle(int $id): ?array
    {
        return Veritabani::tek('SELECT * FROM medyalar WHERE id = ? LIMIT 1', [$id]);
    }

    public static function altGuncelle(int $id, ?string $alt): void
    {
        Veritabani::calistir('UPDATE medyalar SET alt_metin = ? WHERE id = ?', [$alt, $id]);
    }

    public static function dosyaSil(string $dosya): void
    {
        Veritabani::calistir('DELETE FROM medyalar WHERE dosya = ?', [$dosya]);
    }

    /**
     * Dosyanın kullanıldığı yerler (kapak görseli, makale/proje içeriği, site ayarları).
     * @return list<string> okunabilir yer açıklamaları
     */
    public static function kullanimlar(string $dosya): array
    {
        $kac = '%' . addcslashes($dosya, '%_\\') . '%';
        $yerler = [];
        foreach (Veritabani::hepsi('SELECT baslik FROM makaleler WHERE kapak_gorsel = ? OR icerik LIKE ? LIMIT 5', [$dosya, $kac]) as $s) {
            $yerler[] = 'Makale: ' . $s['baslik'];
        }
        foreach (Veritabani::hepsi('SELECT baslik FROM projeler WHERE gorsel = ? OR icerik LIKE ? LIMIT 5', [$dosya, $kac]) as $s) {
            $yerler[] = 'Proje: ' . $s['baslik'];
        }
        foreach (Veritabani::hepsi('SELECT anahtar FROM ayarlar WHERE deger LIKE ? LIMIT 5', [$kac]) as $s) {
            $yerler[] = 'Ayar: ' . $s['anahtar'];
        }
        return $yerler;
    }

    /** Markdown'a eklenecek kod parçası. */
    public static function parca(array $m): string
    {
        $yol = '/yuklemeler/' . $m['dosya'];
        if (($m['tur'] ?? 'gorsel') === 'video') {
            return '@[video](' . $yol . ')';
        }
        $alt = trim((string) ($m['alt_metin'] ?? ''));
        return '![' . str_replace(['[', ']', "\n"], ' ', $alt) . '](' . $yol . ')';
    }
}
