<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Denetleyici;
use App\Core\Oturum;
use App\Core\Totp;
use App\Models\GirisDenemesi;
use App\Models\Kullanici;

final class GirisController extends Denetleyici
{
    /**
     * Kullanıcı bulunamadığında da password_verify çalıştırmak için geçerli biçimli bcrypt (cost 12) özeti.
     * Böylece yanıt süresi kullanıcı varlığını ele vermez.
     */
    private const SAHTE_HASH = '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';

    public function index(): void
    {
        if (Oturum::kullanici() !== null) {
            $this->yonlendir('/admin');
        }
        $this->gorunum('admin/giris', ['sayfaBaslik' => 'Yönetici Girişi'], 'bos');
    }

    public function giris(): void
    {
        $eposta = mb_strtolower(trim($this->girdi('eposta')), 'UTF-8');
        $parola = $this->girdi('parola');
        $ip = istemci_ip();
        $ikili = ip_ikili($ip);
        $kimlik = mb_substr($eposta, 0, 180, 'UTF-8');

        $c = (array) config('giris', []);
        $kalan = GirisDenemesi::kalanKilitSaniye($kimlik, $ikili, (int) $c['maks_deneme'], (int) $c['ip_maks'], (int) $c['pencere_dk']);
        if ($kalan > 0) {
            header('Retry-After: ' . $kalan);
            $this->gorunum('admin/giris', [
                'sayfaBaslik' => 'Yönetici Girişi',
                'kilit'       => 'Çok fazla başarısız deneme. Lütfen ' . (int) ceil($kalan / 60) . ' dakika sonra tekrar deneyin.',
            ], 'bos', 429);
            return;
        }

        $kullanici = ($eposta !== '' && strlen($parola) <= 1024) ? Kullanici::epostaIle($eposta) : null;
        $hash = $kullanici !== null ? (string) $kullanici['parola_hash'] : self::SAHTE_HASH;
        $dogru = password_verify($parola, $hash);

        if ($kullanici === null || !$dogru || (int) $kullanici['aktif'] !== 1) {
            GirisDenemesi::kaydet($kimlik, $ikili, false);
            Oturum::flash('hata', 'E-posta veya parola hatalı.');
            Oturum::flash('eski', ['eposta' => $eposta]);
            $this->yonlendir('/admin/giris');
        }

        if (password_needs_rehash((string) $kullanici['parola_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            Kullanici::hashYenile((int) $kullanici['id'], Kullanici::hashle($parola));
        }

        // İki adımlı doğrulama açıksa: parola doğru, şimdi doğrulama kodu istenir (oturum henüz açılmaz).
        if ((int) ($kullanici['totp_aktif'] ?? 0) === 1) {
            Oturum::ikinciAdimBaslat((int) $kullanici['id']);
            $this->yonlendir('/admin/giris/dogrula');
        }

        $this->girisTamamla($kullanici, $kimlik, $ikili);
    }

    public function dogrulaForm(): void
    {
        if (Oturum::ikinciAdimBekleyen() === null) {
            $this->yonlendir('/admin/giris');
        }
        $this->gorunum('admin/giris_dogrula', ['sayfaBaslik' => 'Doğrulama kodu'], 'bos');
    }

    public function dogrula(): void
    {
        $id = Oturum::ikinciAdimBekleyen();
        if ($id === null) {
            Oturum::flash('hata', 'Oturum süresi doldu. Lütfen yeniden giriş yapın.');
            $this->yonlendir('/admin/giris');
        }
        $ikili = ip_ikili(istemci_ip());
        $kimlik2fa = '2fa:' . $id;
        $c = (array) config('giris', []);
        $kalan = GirisDenemesi::kalanKilitSaniye($kimlik2fa, $ikili, (int) $c['maks_deneme'], (int) $c['ip_maks'], (int) $c['pencere_dk']);
        if ($kalan > 0) {
            header('Retry-After: ' . $kalan);
            $this->gorunum('admin/giris_dogrula', [
                'sayfaBaslik' => 'Doğrulama kodu',
                'kilit'       => 'Çok fazla başarısız deneme. Lütfen ' . (int) ceil($kalan / 60) . ' dakika sonra tekrar deneyin.',
            ], 'bos', 429);
            return;
        }

        $kullanici = Kullanici::idIle($id);
        $kod = trim($this->girdi('kod'));
        $gecerli = false;
        if ($kullanici !== null && (int) $kullanici['aktif'] === 1 && $kod !== '' && strlen($kod) <= 32) {
            $bilgi = Kullanici::totpBilgi($id);
            $gizli = $bilgi !== null && $bilgi['totp_gizli'] !== null ? Totp::coz((string) $bilgi['totp_gizli']) : null;
            if (preg_match('/^\d{3}\s?\d{3}$/', $kod) === 1) {
                $adim = $gizli !== null ? Totp::dogrula($gizli, $kod) : null;
                $gecerli = $adim !== null && Kullanici::totpAdimKaydet($id, $adim);
            } elseif (Totp::yedekKodBicimiMi($kod)) {
                $gecerli = Kullanici::yedekKodTuket($id, Totp::yedekKodOzeti($kod));
            }
        }

        if (!$gecerli) {
            GirisDenemesi::kaydet($kimlik2fa, $ikili, false);
            Oturum::flash('hata', 'Doğrulama kodu hatalı veya süresi dolmuş.');
            $this->yonlendir('/admin/giris/dogrula');
        }

        GirisDenemesi::basariliGirisSonrasiTemizle($kimlik2fa, $ikili);
        Oturum::ikinciAdimTemizle();
        $this->girisTamamla($kullanici, mb_substr((string) $kullanici['eposta'], 0, 180, 'UTF-8'), $ikili);
    }

    /** @param array<string,mixed> $kullanici */
    private function girisTamamla(array $kullanici, string $kimlik, string $ikili): never
    {
        GirisDenemesi::kaydet($kimlik, $ikili, true);
        GirisDenemesi::basariliGirisSonrasiTemizle($kimlik, $ikili);
        Kullanici::sonGirisGuncelle((int) $kullanici['id']);
        Oturum::girisYap($kullanici);
        $this->yonlendir('/admin');
    }

    /** Çıkış yalnızca POST + CSRF ile yapılır (GET ile zorla çıkış / CSRF-logout engellenir). */
    public function cikis(): void
    {
        Oturum::cikis();
        Oturum::flash('basari', 'Güvenli şekilde çıkış yaptınız.');
        $this->yonlendir('/admin/giris');
    }
}
