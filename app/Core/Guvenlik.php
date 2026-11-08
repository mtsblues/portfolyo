<?php
declare(strict_types=1);

namespace App\Core;

/** Güvenlik başlıkları ve CSP nonce üretimi. */
final class Guvenlik
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    public static function https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return (bool) config('guvenlik.guvenilir_proxy', false)
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function basliklariGonder(bool $admin = false): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: ' . ($admin ? 'SAMEORIGIN' : 'DENY'));
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors " . ($admin ? "'self'" : "'none'"),
            "object-src 'none'",
            "img-src 'self' data:",
            "media-src 'self'",
            // Yalnızca gizlilik dostu video gömme adresleri (Markdown'daki @[youtube]/@[vimeo] bloğu için)
            "frame-src https://www.youtube-nocookie.com https://player.vimeo.com",
            "font-src 'self'",
            "style-src 'self'",
            "script-src 'self' 'nonce-" . self::nonce() . "'",
            "connect-src 'self'",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        if (config('guvenlik.hsts', false) && self::https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
