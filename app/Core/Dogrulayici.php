<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sunucu tarafı girdi doğrulama.
 * Kurallar: gerekli, bos_olabilir, min:N, max:N, eposta, slug, url, tamsayi, parola, listede:a,b,c, mesaj_metin
 * Örnek: Dogrulayici::yap($_POST, ['eposta' => 'gerekli|eposta|max:180'])
 */
final class Dogrulayici
{
    /** @var array<string,string> */
    private array $hatalar = [];

    /** @var array<string,mixed> */
    private array $temiz = [];

    /** @param array<string,string> $kurallar */
    public static function yap(array $veri, array $kurallar): self
    {
        $d = new self();
        foreach ($kurallar as $alan => $kuralSatiri) {
            $d->alanDogrula($alan, $veri[$alan] ?? null, explode('|', $kuralSatiri));
        }
        return $d;
    }

    /** @param list<string> $kurallar */
    private function alanDogrula(string $alan, mixed $ham, array $kurallar): void
    {
        $cokSatir = in_array('cok_satir', $kurallar, true);
        $parola = in_array('parola_alani', $kurallar, true);
        if (is_array($ham)) {
            $this->hatalar[$alan] = 'Geçersiz değer.';
            return;
        }
        $deger = $ham === null ? '' : (string) $ham;
        if (!mb_check_encoding($deger, 'UTF-8')) {
            $this->hatalar[$alan] = 'Geçersiz karakter kodlaması.';
            return;
        }
        if (!$parola) {
            $deger = self::temizle($deger, $cokSatir);
        }
        $this->temiz[$alan] = $deger;

        $bosOlabilir = in_array('bos_olabilir', $kurallar, true);
        $gerekli = in_array('gerekli', $kurallar, true);
        if ($deger === '') {
            if ($gerekli && !$bosOlabilir) {
                $this->hatalar[$alan] = 'Bu alan zorunludur.';
            }
            return;
        }

        foreach ($kurallar as $kural) {
            $arg = null;
            if (str_contains($kural, ':')) {
                [$kural, $arg] = explode(':', $kural, 2);
            }
            $hata = match ($kural) {
                'min'      => mb_strlen($deger, 'UTF-8') < (int) $arg ? "En az {$arg} karakter olmalıdır." : null,
                'max'      => mb_strlen($deger, 'UTF-8') > (int) $arg ? "En fazla {$arg} karakter olabilir." : null,
                'eposta'   => self::epostaGecerli($deger) ? null : 'Geçerli bir e-posta adresi girin.',
                'slug'     => self::slugGecerli($deger) ? null : 'Yalnızca küçük harf, rakam ve tire kullanın (örn. ornek-slug).',
                'url'      => self::urlGecerli($deger) ? null : 'Geçerli bir http/https adresi girin.',
                'tamsayi'  => preg_match('/^[1-9][0-9]{0,17}$/', $deger) === 1 ? null : 'Geçerli bir sayı girin.',
                'sayi'     => preg_match('/^-?[0-9]{1,9}$/', $deger) === 1 ? null : 'Geçerli bir tam sayı girin.',
                'tarih'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $deger) === 1 && self::tarihGecerli($deger) ? null : 'Geçerli bir tarih girin (YYYY-AA-GG).',
                'parola'   => ($p = self::parolaHatalari($deger)) === [] ? null : implode(' ', $p),
                'listede'  => in_array($deger, explode(',', (string) $arg), true) ? null : 'Geçersiz seçim.',
                default    => null,
            };
            if ($hata !== null) {
                $this->hatalar[$alan] = $hata;
                return;
            }
        }
    }

    /** Kontrol karakterlerini temizler, kenar boşluklarını alır. */
    public static function temizle(string $deger, bool $cokSatir = false): string
    {
        $deger = str_replace("\r\n", "\n", $deger);
        $deger = str_replace("\r", "\n", $deger);
        $desen = $cokSatir ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        $sonuc = preg_replace($desen, '', $deger);
        return trim($sonuc ?? '');
    }

    public static function epostaGecerli(string $e): bool
    {
        return mb_strlen($e, 'UTF-8') <= 180 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function slugGecerli(string $s): bool
    {
        return strlen($s) >= 1 && strlen($s) <= 220 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) === 1;
    }

    public static function urlGecerli(string $u): bool
    {
        if (strlen($u) > 255 || filter_var($u, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $sema = strtolower((string) parse_url($u, PHP_URL_SCHEME));
        return $sema === 'http' || $sema === 'https';
    }

    private static function tarihGecerli(string $t): bool
    {
        [$y, $a, $g] = array_map('intval', explode('-', $t));
        return checkdate($a, $g, $y);
    }

    /** @return list<string> */
    public static function parolaHatalari(string $p): array
    {
        $h = [];
        if (mb_strlen($p, 'UTF-8') < 12) {
            $h[] = 'Parola en az 12 karakter olmalıdır.';
        }
        if (strlen($p) > 72) {
            $h[] = 'Parola en fazla 72 bayt olabilir (bcrypt sınırı).';
        }
        if (preg_match('/\p{Lu}/u', $p) !== 1) {
            $h[] = 'En az bir büyük harf gerekir.';
        }
        if (preg_match('/\p{Ll}/u', $p) !== 1) {
            $h[] = 'En az bir küçük harf gerekir.';
        }
        if (preg_match('/\d/', $p) !== 1) {
            $h[] = 'En az bir rakam gerekir.';
        }
        if (preg_match('/[^\p{L}\p{N}]/u', $p) !== 1) {
            $h[] = 'En az bir özel karakter gerekir.';
        }
        return $h;
    }

    /** Türkçe karakterleri dönüştürerek SEO uyumlu slug üretir. */
    public static function slugOlustur(string $metin): string
    {
        $metin = strtr($metin, [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'I' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ]);
        $metin = mb_strtolower($metin, 'UTF-8');
        $metin = (string) preg_replace('/[^a-z0-9]+/', '-', $metin);
        $metin = trim($metin, '-');
        $metin = substr($metin, 0, 200);
        return trim($metin, '-');
    }

    public function basarisiz(): bool
    {
        return $this->hatalar !== [];
    }

    /** @return array<string,string> */
    public function hatalar(): array
    {
        return $this->hatalar;
    }

    /** @return array<string,mixed> */
    public function temiz(): array
    {
        return $this->temiz;
    }

    public function deger(string $alan, mixed $varsayilan = ''): mixed
    {
        return $this->temiz[$alan] ?? $varsayilan;
    }
}
