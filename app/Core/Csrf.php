<?php
declare(strict_types=1);

namespace App\Core;

/** Oturum başına CSRF token. Form alanı (csrf_token) veya X-CSRF-Token başlığı ile doğrulanır. */
final class Csrf
{
    private const ANAHTAR = '_csrf';

    public static function token(): string
    {
        if (!isset($_SESSION[self::ANAHTAR]) || !is_string($_SESSION[self::ANAHTAR]) || strlen($_SESSION[self::ANAHTAR]) !== 64) {
            $_SESSION[self::ANAHTAR] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::ANAHTAR];
    }

    public static function yenile(): void
    {
        $_SESSION[self::ANAHTAR] = bin2hex(random_bytes(32));
    }

    public static function dogrula(?string $gelen): bool
    {
        if ($gelen === null || $gelen === '') {
            return false;
        }
        $beklenen = $_SESSION[self::ANAHTAR] ?? null;
        if (!is_string($beklenen) || $beklenen === '') {
            return false;
        }
        return hash_equals($beklenen, $gelen);
    }

    /** İstekten (POST alanı ya da başlık) token'ı çıkarıp doğrular. */
    public static function istegiDogrula(): bool
    {
        $gelen = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        return is_string($gelen) && self::dogrula($gelen);
    }
}
