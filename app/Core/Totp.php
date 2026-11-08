<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Zaman tabanlı tek kullanımlık parola (TOTP, RFC 6238 / HOTP RFC 4226) — bağımlılıksız.
 * Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden vb. ile uyumludur.
 * Gizli anahtar veritabanında uygulama anahtarıyla AES-256-GCM şifreli saklanır.
 */
final class Totp
{
    private const ALFABE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const YEDEK_ALFABE = 'abcdefghjkmnpqrstuvwxyz23456789';
    public const PERIYOT = 30;

    public static function gizliUret(): string
    {
        return self::base32Kodla(random_bytes(20));
    }

    public static function base32Kodla(string $ikili): string
    {
        $bit = '';
        foreach (str_split($ikili) as $b) {
            $bit .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $cikti = '';
        foreach (str_split($bit, 5) as $parca) {
            $cikti .= self::ALFABE[bindec(str_pad($parca, 5, '0', STR_PAD_RIGHT))];
        }
        return $cikti;
    }

    public static function base32Coz(string $metin): ?string
    {
        $metin = strtoupper(str_replace([' ', '-', '='], '', $metin));
        if ($metin === '' || preg_match('/^[A-Z2-7]+$/', $metin) !== 1) {
            return null;
        }
        $bit = '';
        foreach (str_split($metin) as $h) {
            $bit .= str_pad(decbin((int) strpos(self::ALFABE, $h)), 5, '0', STR_PAD_LEFT);
        }
        $cikti = '';
        foreach (str_split($bit, 8) as $parca) {
            if (strlen($parca) === 8) {
                $cikti .= chr((int) bindec($parca));
            }
        }
        return $cikti;
    }

    /** HOTP (RFC 4226). */
    public static function hotp(string $anahtarIkili, int $sayac, int $hane = 6): string
    {
        $ozet = hash_hmac('sha1', pack('J', $sayac), $anahtarIkili, true);
        $o = ord($ozet[19]) & 0x0F;
        $kod = ((ord($ozet[$o]) & 0x7F) << 24) | (ord($ozet[$o + 1]) << 16) | (ord($ozet[$o + 2]) << 8) | ord($ozet[$o + 3]);
        return str_pad((string) ($kod % (10 ** $hane)), $hane, '0', STR_PAD_LEFT);
    }

    public static function adim(?int $zaman = null): int
    {
        return intdiv($zaman ?? time(), self::PERIYOT);
    }

    /**
     * Kodu doğrular (saat sapmasına karşı ±$pencere adım).
     * @return int|null eşleşen zaman adımı (tekrar kullanımı engellemek için saklanmalı), eşleşmezse null
     */
    public static function dogrula(string $gizliBase32, string $kod, ?int $zaman = null, int $pencere = 1): ?int
    {
        $kod = preg_replace('/\s+/', '', $kod) ?? '';
        $anahtar = self::base32Coz($gizliBase32);
        if ($anahtar === null || preg_match('/^\d{6}$/', $kod) !== 1) {
            return null;
        }
        $simdi = self::adim($zaman);
        $bulunan = null;
        for ($i = -$pencere; $i <= $pencere; $i++) {
            // Zamanlama farkını azaltmak için tüm pencereler taranır.
            if (hash_equals(self::hotp($anahtar, $simdi + $i), $kod) && $bulunan === null) {
                $bulunan = $simdi + $i;
            }
        }
        return $bulunan;
    }

    /** Doğrulayıcı uygulamalara girilecek otpauth adresi. */
    public static function uri(string $gizli, string $hesap, string $yayinci): string
    {
        $y = rawurlencode($yayinci);
        return 'otpauth://totp/' . $y . ':' . rawurlencode($hesap) . '?secret=' . $gizli . '&issuer=' . $y . '&algorithm=SHA1&digits=6&period=' . self::PERIYOT;
    }

    // ---- Gizli anahtarın veritabanında korunması ----

    private static function sifrelemeAnahtari(): ?string
    {
        $a = (string) config('uygulama.anahtar', '');
        return ($a !== '' && function_exists('openssl_encrypt')) ? hash('sha256', 'totp-gizli|' . $a, true) : null;
    }

    public static function sifrele(string $gizli): string
    {
        $anahtar = self::sifrelemeAnahtari();
        if ($anahtar === null) {
            return 'p:' . $gizli;
        }
        $iv = random_bytes(12);
        $sifreli = openssl_encrypt($gizli, 'aes-256-gcm', $anahtar, OPENSSL_RAW_DATA, $iv, $etiket);
        return $sifreli === false ? 'p:' . $gizli : 'e1:' . base64_encode($iv . $etiket . $sifreli);
    }

    public static function coz(string $depolanan): ?string
    {
        if (str_starts_with($depolanan, 'p:')) {
            return substr($depolanan, 2);
        }
        $anahtar = self::sifrelemeAnahtari();
        if (!str_starts_with($depolanan, 'e1:') || $anahtar === null) {
            return null;
        }
        $ham = base64_decode(substr($depolanan, 3), true);
        if ($ham === false || strlen($ham) < 29) {
            return null;
        }
        $acik = openssl_decrypt(substr($ham, 28), 'aes-256-gcm', $anahtar, OPENSSL_RAW_DATA, substr($ham, 0, 12), substr($ham, 12, 16));
        return $acik === false ? null : $acik;
    }

    // ---- Yedek kodlar (telefon kaybında tek kullanımlık giriş) ----

    /** @return list<string> "xxxxx-xxxxx" biçiminde 8 kod */
    public static function yedekKodlariUret(int $adet = 8): array
    {
        $kodlar = [];
        for ($i = 0; $i < $adet; $i++) {
            $k = '';
            for ($j = 0; $j < 10; $j++) {
                $k .= self::YEDEK_ALFABE[random_int(0, strlen(self::YEDEK_ALFABE) - 1)];
            }
            $kodlar[] = substr($k, 0, 5) . '-' . substr($k, 5);
        }
        return $kodlar;
    }

    public static function yedekKodOzeti(string $kod): string
    {
        $norm = strtolower(str_replace(['-', ' '], '', $kod));
        return hash_hmac('sha256', $norm, 'yedek-kod|' . (string) config('uygulama.anahtar', ''));
    }

    public static function yedekKodBicimiMi(string $kod): bool
    {
        return preg_match('/^[a-z2-9]{5}-?[a-z2-9]{5}$/i', trim($kod)) === 1;
    }
}
