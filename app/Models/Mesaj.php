<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

final class Mesaj extends Model
{
    protected const TABLO = 'mesajlar';

    public static function olustur(string $adSoyad, string $eposta, string $konu, string $mesaj, string $ipIkili): int
    {
        Veritabani::calistir(
            'INSERT INTO mesajlar (ad_soyad, eposta, konu, mesaj, ip_adresi) VALUES (?, ?, ?, ?, ?)',
            [$adSoyad, $eposta, $konu, $mesaj, $ipIkili]
        );
        return Veritabani::sonId();
    }

    /** @return list<array<string,mixed>> */
    public static function liste(int $limit, int $offset): array
    {
        return Veritabani::hepsi(
            'SELECT id, ad_soyad, eposta, konu, mesaj, ip_adresi, okundu, olusturulma FROM mesajlar
             ORDER BY okundu ASC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function okunmamisSay(): int
    {
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM mesajlar WHERE okundu = 0');
    }

    public static function okunduIsaretle(int $id): void
    {
        Veritabani::calistir('UPDATE mesajlar SET okundu = 1 WHERE id = ?', [$id]);
    }

    public static function sonSaatteIpSayisi(string $ipIkili): int
    {
        return (int) Veritabani::skaler(
            'SELECT COUNT(*) FROM mesajlar WHERE ip_adresi = ? AND olusturulma > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)',
            [$ipIkili]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function son(int $limit): array
    {
        return Veritabani::hepsi(
            'SELECT id, ad_soyad, eposta, konu, okundu, olusturulma FROM mesajlar ORDER BY id DESC LIMIT ?',
            [$limit]
        );
    }
}
