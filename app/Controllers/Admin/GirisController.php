<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Denetim;
use App\Core\Denetleyici;
use App\Core\Dogrulayici;
use App\Core\HizSiniri;
use App\Core\Oturum;
use App\Core\Posta;
use App\Core\SifreSifirlama;
use App\Core\Totp;
use App\Core\Veritabani;
use App\Models\GirisDenemesi;
use App\Models\Kullanici;
use App\Models\OturumKaydi;

final class GirisController extends Denetleyici
{
    /**
     * Kullanıcı bulunamadığında da password_verify çalıştırmak için geçerli biçimli bcrypt (cost 12) özeti.
     * Böylece yanıt süresi kullanıcı varlığını ele vermez.
     */
    private const SAHTE_HASH = '$2y$12$XrTFJS/NifU1o0TwU2iS.uVx9dgc4yu5OcB1WCzOVcx.8kxvhkRE.';

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
            Denetim::kaydet('giris.basarisiz', $kimlik, null, $kullanici !== null ? (int) $kullanici['id'] : null);
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
            Denetim::kaydet('giris.2fa_hatali', $kullanici !== null ? (string) $kullanici['eposta'] : null, null, $id);
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
        $kid = (int) $kullanici['id'];
        $uaOzeti = substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 16);
        // Yeni cihaz mı? (bu kullanıcı daha önce başarılı giriş yapmış ama bu tarayıcıdan hiç girmemiş)
        $oncekiGiris = (int) Veritabani::skaler("SELECT COUNT(*) FROM denetim_gunlugu WHERE kullanici_id = ? AND eylem = 'giris.basarili'", [$kid]) > 0;
        $buCihaz = (int) Veritabani::skaler("SELECT COUNT(*) FROM denetim_gunlugu WHERE kullanici_id = ? AND eylem = 'giris.basarili' AND ayrinti = ?", [$kid, 'ua:' . $uaOzeti]) > 0;
        $yeniCihaz = $oncekiGiris && !$buCihaz;

        GirisDenemesi::kaydet($kimlik, $ikili, true);
        GirisDenemesi::basariliGirisSonrasiTemizle($kimlik, $ikili);
        Kullanici::sonGirisGuncelle($kid);
        Oturum::girisYap($kullanici);
        Denetim::kaydet('giris.basarili', $kimlik, 'ua:' . $uaOzeti, $kid);

        if ($yeniCihaz && ayar('yeni_cihaz_bildirimi', '1') === '1' && Posta::yapilandirildi()) {
            $eposta = (string) $kullanici['eposta'];
            $ua = OturumKaydi::tarayiciEtiketi((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $ip = istemci_ip();
            $this->yonlendirSonra('/admin', static function () use ($eposta, $ua, $ip): void {
                Posta::gonder(
                    $eposta,
                    '[' . ayar('site_baslik', 'Portfolyo') . '] Yeni cihazdan giriş',
                    "Hesabınıza yeni bir cihazdan giriş yapıldı.\n\nCihaz : {$ua}\nIP    : {$ip}\nZaman : " . gmdate('Y-m-d H:i:s') . " UTC\n\n"
                    . "Bu siz değilseniz hemen parolanızı değiştirin ve Güvenlik Merkezi'nden diğer oturumları kapatın:\n" . mutlak_url('/admin/guvenlik') . "\n"
                );
            });
        }
        $this->yonlendir('/admin');
    }

    /** Çıkış yalnızca POST + CSRF ile yapılır (GET ile zorla çıkış / CSRF-logout engellenir). */
    public function cikis(): void
    {
        $k = Oturum::kullanici();
        if ($k !== null) {
            Denetim::kaydet('cikis', (string) $k['eposta'], null, (int) $k['id']);
        }
        Oturum::cikis();
        Oturum::flash('basari', 'Güvenli şekilde çıkış yaptınız.');
        $this->yonlendir('/admin/giris');
    }

    // ------------------------------------------------------------------ parola sıfırlama

    /** Sıfırlama bağlantısı Host başlığına güvenmez: e-posta ayarı VE sabit APP_URL gerekir. */
    private static function sifirlamaKullanilabilir(): bool
    {
        return Posta::yapilandirildi() && (string) config('uygulama.url', '') !== '';
    }

    public function unuttum(): void
    {
        if (Oturum::kullanici() !== null) {
            $this->yonlendir('/admin/profil');
        }
        header('Referrer-Policy: no-referrer');
        $this->gorunum('admin/sifre_unuttum', ['sayfaBaslik' => 'Parolamı unuttum', 'epostaVar' => self::sifirlamaKullanilabilir()], 'bos');
    }

    /**
     * Kullanıcının var olup olmadığı hiçbir şekilde (mesaj, süre) belli olmaz: her zaman aynı yanıt hemen döner,
     * jeton üretme ve e-posta gönderme yanıttan SONRA yapılır.
     */
    public function unuttumGonder(): void
    {
        $eposta = mb_strtolower(trim($this->girdi('eposta')), 'UTF-8');
        $ip = istemci_ip();
        $sinirOk = self::sifirlamaKullanilabilir()
            && HizSiniri::izinVer('sifirlama:' . $ip, 5, 900)
            && HizSiniri::izinVer('sifirlama-e:' . hash('sha256', $eposta), 3, 3600);
        Oturum::flash('basari', 'Bu adres kayıtlıysa ve e-posta ayarları yapılmışsa, parola sıfırlama bağlantısı gönderildi. Gelen kutunuzu (ve spam klasörünü) kontrol edin; bağlantı ' . SifreSifirlama::OMUR_DK . ' dakika geçerlidir.');

        $this->yonlendirSonra('/admin/sifremi-unuttum', static function () use ($eposta, $sinirOk): void {
            if (!$sinirOk || $eposta === '' || strlen($eposta) > 180 || !Dogrulayici::epostaGecerli($eposta) || !Posta::yapilandirildi()) {
                return;
            }
            $k = Kullanici::epostaIle($eposta);
            if ($k === null || (int) $k['aktif'] !== 1) {
                return;
            }
            $jeton = SifreSifirlama::olustur((int) $k['id']);
            Denetim::kaydet('parola.sifirlama_istendi', $eposta, null, (int) $k['id']);
            $s = Posta::gonder(
                (string) $k['eposta'],
                '[' . ayar('site_baslik', 'Portfolyo') . '] Parola sıfırlama',
                "Merhaba,\n\nHesabınız için parola sıfırlama istendi. Yeni parola belirlemek için bağlantıya tıklayın (" . SifreSifirlama::OMUR_DK . " dakika geçerli, tek kullanımlık):\n\n"
                . mutlak_url('/admin/sifre-sifirla/' . $jeton) . "\n\nBu isteği siz yapmadıysanız bu iletiyi yok sayın; parolanız değişmez.\n"
            );
            if (!$s['ok']) {
                gunluk('Parola sıfırlama e-postası gönderilemedi: ' . (string) $s['hata']);
            }
        });
    }

    public function sifirlaForm(string $jeton): void
    {
        header('Referrer-Policy: no-referrer');
        $d = SifreSifirlama::dogrula($jeton);
        if ($d === null) {
            $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => true, 'jeton' => ''], 'bos', 410);
            return;
        }
        $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => false, 'jeton' => $jeton, 'eposta' => $d['eposta']], 'bos');
    }

    public function sifirla(string $jeton): void
    {
        header('Referrer-Policy: no-referrer');
        $ip = istemci_ip();
        if (!HizSiniri::izinVer('sifirla:' . $ip, 10, 900)) {
            $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => true, 'jeton' => '', 'hata' => 'Çok fazla deneme. Lütfen biraz sonra tekrar deneyin.'], 'bos', 429);
            return;
        }
        $d = SifreSifirlama::dogrula($jeton);
        if ($d === null) {
            $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => true, 'jeton' => ''], 'bos', 410);
            return;
        }
        $yeni = $this->girdi('parola');
        $hatalar = Dogrulayici::parolaHatalari($yeni);
        if ($hatalar === [] && !hash_equals($yeni, $this->girdi('parola_tekrar'))) {
            $hatalar[] = 'Parolalar eşleşmiyor.';
        }
        if ($hatalar !== []) {
            $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => false, 'jeton' => $jeton, 'eposta' => $d['eposta'], 'hata' => implode(' ', $hatalar)], 'bos', 422);
            return;
        }
        if (!SifreSifirlama::tuket($d['id'])) { // aynı bağlantıyla eşzamanlı ikinci istek
            $this->gorunum('admin/sifre_sifirla', ['sayfaBaslik' => 'Parola sıfırla', 'gecersiz' => true, 'jeton' => ''], 'bos', 410);
            return;
        }
        Kullanici::parolaGuncelle($d['kullanici_id'], $yeni);
        OturumKaydi::tumunuKapat($d['kullanici_id']);
        Veritabani::calistir('DELETE FROM giris_denemeleri WHERE kimlik IN (?, ?)', [$d['eposta'], '2fa:' . $d['kullanici_id']]);
        Denetim::kaydet('parola.sifirlandi', $d['eposta'], null, $d['kullanici_id']);
        Oturum::flash('basari', 'Parolanız güncellendi. Tüm oturumlar kapatıldı; yeni parolanızla giriş yapın.');
        $this->yonlendir('/admin/giris');
    }
}
