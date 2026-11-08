<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Temel model. TABLO sabiti kod içinde sabit tanımlıdır (kullanıcı girdisi ASLA tablo adına girmez).
 */
abstract class Model
{
    protected const TABLO = '';

    /** @return array<string,mixed>|null */
    public static function idIle(int $id): ?array
    {
        return Veritabani::tek('SELECT * FROM `' . static::TABLO . '` WHERE id = ? LIMIT 1', [$id]);
    }

    public static function say(): int
    {
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM `' . static::TABLO . '`');
    }

    public static function sil(int $id): bool
    {
        return Veritabani::calistir('DELETE FROM `' . static::TABLO . '` WHERE id = ?', [$id]) > 0;
    }

    public static function slugVarMi(string $slug, ?int $haricId = null): bool
    {
        $sql = 'SELECT 1 FROM `' . static::TABLO . '` WHERE slug = ?';
        $p = [$slug];
        if ($haricId !== null) {
            $sql .= ' AND id <> ?';
            $p[] = $haricId;
        }
        return Veritabani::skaler($sql . ' LIMIT 1', $p) !== null;
    }

    /** Çakışma varsa -2, -3 ... ekleyerek benzersiz slug üretir. */
    public static function benzersizSlug(string $slug, ?int $haricId = null): string
    {
        $slug = $slug !== '' ? $slug : 'icerik';
        $aday = $slug;
        for ($i = 2; static::slugVarMi($aday, $haricId); $i++) {
            $aday = substr($slug, 0, 200) . '-' . $i;
        }
        return $aday;
    }
}
