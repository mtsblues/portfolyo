<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Güvenli oturum yönetimi:
 *  - strict mode, yalnızca çerez, HttpOnly + Secure + SameSite=Lax
 *  - girişte session_regenerate_id(true) (fixation)
 *  - User-Agent / IP öneki bağlama (hijacking), boşta ve mutlak zaman aşımı
 *  - flash mesajları
 */
final class Oturum
{
    private const YENILEME_SN = 900;

    /** @var array<string,mixed> */
    private static array $flashGelen = [];

    public static function baslat(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $c = (array) Yapilandirma::al('oturum', []);
        $secure = (bool) ($c['secure'] ?? true);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) (((int) ($c['mutlak_dk'] ?? 480)) * 60));

        // PHP'nin otomatik "no-store" başlıklarını kapat; önbellek politikasını uygulama belirler.
        session_cache_limiter('');
        session_name((string) ($c['ad'] ?? 'PORTFOLYO_SESSION'));

        $dizin = KOK . '/storage/oturum';
        if (!is_dir($dizin)) {
            @mkdir($dizin, 0700, true);
        }
        if (is_dir($dizin) && is_writable($dizin)) {
            session_save_path($dizin);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::denetle($c);

        self::$flashGelen = is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
        $_SESSION['_flash'] = [];
    }

    /** @param array<string,mixed> $c */
    private static function denetle(array $c): void
    {
        $simdi = time();
        if (!isset($_SESSION['_olusturma'])) {
            self::ilkle($simdi);
            return;
        }

        $gecersiz = false;
        $mutlak = ((int) ($c['mutlak_dk'] ?? 480)) * 60;
        $bosta = ((int) ($c['bosta_dk'] ?? 30)) * 60;

        if ($simdi - (int) $_SESSION['_olusturma'] > $mutlak) {
            $gecersiz = true;
        } elseif ($simdi - (int) ($_SESSION['_son'] ?? 0) > $bosta) {
            $gecersiz = true;
        } elseif (!empty($c['ua_bagla']) && !hash_equals((string) ($_SESSION['_ua'] ?? ''), self::uaOzeti())) {
            $gecersiz = true;
        } elseif (!empty($c['ip_bagla']) && !hash_equals((string) ($_SESSION['_ip'] ?? ''), self::ipOnEki())) {
            $gecersiz = true;
        }

        if ($gecersiz) {
            self::yokEt();
            session_start();
            self::ilkle($simdi);
            return;
        }

        $_SESSION['_son'] = $simdi;
        if ($simdi - (int) ($_SESSION['_yenileme'] ?? 0) > self::YENILEME_SN) {
            session_regenerate_id(true);
            $_SESSION['_yenileme'] = $simdi;
        }
    }

    private static function ilkle(int $simdi): void
    {
        $_SESSION = [];
        $_SESSION['_olusturma'] = $simdi;
        $_SESSION['_son'] = $simdi;
        $_SESSION['_yenileme'] = $simdi;
        $_SESSION['_ua'] = self::uaOzeti();
        $_SESSION['_ip'] = self::ipOnEki();
    }

    private static function uaOzeti(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    /** IPv4: ilk 3 oktet, IPv6: ilk /64 (NAT / mobil IP değişimine tolerans). */
    private static function ipOnEki(): string
    {
        $ikili = ip_ikili();
        $uzunluk = strlen($ikili) === 4 ? 3 : 8;
        return bin2hex(substr($ikili, 0, $uzunluk));
    }

    public static function girisYap(array $kullanici): void
    {
        session_regenerate_id(true);
        $simdi = time();
        $_SESSION['kullanici'] = [
            'id'       => (int) $kullanici['id'],
            'ad_soyad' => (string) $kullanici['ad_soyad'],
            'eposta'   => (string) $kullanici['eposta'],
            'rol'      => (string) $kullanici['rol'],
        ];
        $_SESSION['_olusturma'] = $simdi;
        $_SESSION['_son'] = $simdi;
        $_SESSION['_yenileme'] = $simdi;
        Csrf::yenile();
    }

    /** Parola doğrulandı, iki adımlı kod bekleniyor (5 dakika geçerli). */
    public static function ikinciAdimBaslat(int $kullaniciId): void
    {
        $_SESSION['_ikinci_adim'] = ['id' => $kullaniciId, 'ts' => time()];
    }

    public static function ikinciAdimBekleyen(): ?int
    {
        $d = $_SESSION['_ikinci_adim'] ?? null;
        if (!is_array($d) || !isset($d['id'], $d['ts']) || time() - (int) $d['ts'] > 300) {
            unset($_SESSION['_ikinci_adim']);
            return null;
        }
        return (int) $d['id'];
    }

    public static function ikinciAdimTemizle(): void
    {
        unset($_SESSION['_ikinci_adim']);
    }

    /** @return array{id:int,ad_soyad:string,eposta:string,rol:string}|null */
    public static function kullanici(): ?array
    {
        $k = $_SESSION['kullanici'] ?? null;
        return is_array($k) && isset($k['id']) ? $k : null;
    }

    public static function cikis(): void
    {
        self::yokEt();
        session_start();
        session_regenerate_id(true);
        self::ilkle(time());
        Csrf::yenile();
    }

    private static function yokEt(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies') === '1') {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => (bool) $p['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Bir sonraki istekte okunacak flash verisi. */
    public static function flash(string $anahtar, mixed $deger): void
    {
        $_SESSION['_flash'][$anahtar] = $deger;
    }

    /** Önceki istekte bırakılan flash verisi (bu istek boyunca okunabilir). */
    public static function flashGelen(string $anahtar, mixed $varsayilan = null): mixed
    {
        return self::$flashGelen[$anahtar] ?? $varsayilan;
    }
}
