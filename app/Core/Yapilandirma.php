<?php
declare(strict_types=1);

namespace App\Core;

/**
 * .env okuyucu ve yapılandırma deposu.
 * Gerçek ortam değişkenleri (getenv) .env dosyasındaki değerlerin önüne geçer.
 */
final class Yapilandirma
{
    /** @var array<string,string> */
    private static array $env = [];

    /** @var array<string,mixed> */
    private static array $config = [];

    public static function yukle(string $dosya): void
    {
        if (!is_file($dosya) || !is_readable($dosya)) {
            return;
        }
        $satirlar = file($dosya, FILE_IGNORE_NEW_LINES);
        if ($satirlar === false) {
            return;
        }
        foreach ($satirlar as $satir) {
            $satir = trim($satir);
            if ($satir === '' || $satir[0] === '#') {
                continue;
            }
            $poz = strpos($satir, '=');
            if ($poz === false) {
                continue;
            }
            $anahtar = trim(substr($satir, 0, $poz));
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $anahtar) !== 1) {
                continue;
            }
            self::$env[$anahtar] = self::degerCoz(trim(substr($satir, $poz + 1)));
        }
    }

    private static function degerCoz(string $deger): string
    {
        if ($deger === '') {
            return '';
        }
        $tirnak = $deger[0];
        if ($tirnak === '"' || $tirnak === "'") {
            $son = strrpos($deger, $tirnak);
            if ($son !== false && $son > 0) {
                return substr($deger, 1, $son - 1);
            }
        }
        $temiz = preg_replace('/\s+#.*$/', '', $deger);
        return trim((string) $temiz);
    }

    public static function env(string $anahtar, mixed $varsayilan = null): mixed
    {
        $gercek = getenv($anahtar);
        if ($gercek !== false) {
            return $gercek;
        }
        return array_key_exists($anahtar, self::$env) ? self::$env[$anahtar] : $varsayilan;
    }

    public static function envBool(string $anahtar, bool $varsayilan): bool
    {
        $deger = self::env($anahtar);
        if ($deger === null || $deger === '') {
            return $varsayilan;
        }
        $sonuc = filter_var($deger, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $sonuc ?? $varsayilan;
    }

    /** @param array<string,mixed> $config */
    public static function configAyarla(array $config): void
    {
        self::$config = $config;
    }

    /** Noktalı anahtar: al('db.host') */
    public static function al(string $anahtar, mixed $varsayilan = null): mixed
    {
        $parcalar = explode('.', $anahtar);
        $dugum = self::$config;
        foreach ($parcalar as $parca) {
            if (!is_array($dugum) || !array_key_exists($parca, $dugum)) {
                return $varsayilan;
            }
            $dugum = $dugum[$parca];
        }
        return $dugum;
    }
}
