<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Core\Oturum;
use App\Core\Totp;
use App\Models\Ayar;
use App\Models\Kullanici;

/** Oturumdaki kullanıcının kendi profili: parola değiştirme ve iki adımlı doğrulama (TOTP). */
final class ProfilController extends AdminTemel
{
    private const KURULUM_SURESI = 900;

    public function index(): void
    {
        $kurulum = $_SESSION['_totp_kurulum'] ?? null;
        if (is_array($kurulum) && time() - (int) ($kurulum['ts'] ?? 0) > self::KURULUM_SURESI) {
            unset($_SESSION['_totp_kurulum']);
            $kurulum = null;
        }
        $aktif = Kullanici::ikiAdimAktifMi($this->kullanici['id']);
        $gizli = is_array($kurulum) ? (string) $kurulum['gizli'] : null;
        $this->admin('profil', [
            'sayfaBaslik' => 'Profil ve güvenlik',
            'aktif'       => $aktif,
            'kurulumGizli' => $gizli,
            'kurulumUri'  => $gizli !== null
                ? Totp::uri($gizli, $this->kullanici['eposta'], (string) Ayar::al('site_baslik', 'Portfolyo'))
                : null,
            'yedekKodlar' => Oturum::flashGelen('yedek_kodlar'),
            'kalanYedek'  => $aktif ? Kullanici::yedekKodSayisi($this->kullanici['id']) : 0,
        ]);
    }

    public function parolaDegistir(): void
    {
        $mevcut = $this->girdi('mevcut_parola');
        $yeni = $this->girdi('parola');
        $tekrar = $this->girdi('parola_tekrar');
        $k = Kullanici::idIle($this->kullanici['id']);

        $hatalar = [];
        if ($k === null || strlen($mevcut) > 1024 || !password_verify($mevcut, (string) $k['parola_hash'])) {
            $hatalar['mevcut_parola'] = 'Mevcut parola hatalı.';
        }
        $p = Dogrulayici::parolaHatalari($yeni);
        if ($p !== []) {
            $hatalar['parola'] = implode(' ', $p);
        } elseif (!hash_equals($yeni, $tekrar)) {
            $hatalar['parola_tekrar'] = 'Parolalar eşleşmiyor.';
        } elseif ($yeni === $mevcut) {
            $hatalar['parola'] = 'Yeni parola eskisiyle aynı olamaz.';
        }
        if ($hatalar !== []) {
            $this->formHatasi('/admin/profil', $hatalar, []);
        }
        Kullanici::parolaGuncelle($this->kullanici['id'], $yeni);
        session_regenerate_id(true);
        $kapanan = \App\Models\OturumKaydi::digerleriniKapat($this->kullanici['id']);
        \App\Core\Denetim::kaydet('parola.degisti', $this->kullanici['eposta'], null, $this->kullanici['id']);
        $this->geriDon('/admin/profil', 'basari', 'Parolanız güncellendi.' . ($kapanan > 0 ? ' Diğer ' . $kapanan . ' oturum kapatıldı.' : ''));
    }

    public function ikiAdimBaslat(): void
    {
        if (Kullanici::ikiAdimAktifMi($this->kullanici['id'])) {
            $this->geriDon('/admin/profil', 'hata', 'İki adımlı doğrulama zaten açık.');
        }
        $_SESSION['_totp_kurulum'] = ['gizli' => Totp::gizliUret(), 'ts' => time()];
        $this->yonlendir('/admin/profil');
    }

    public function ikiAdimEtkinlestir(): void
    {
        $k = $_SESSION['_totp_kurulum'] ?? null;
        if (!is_array($k) || time() - (int) ($k['ts'] ?? 0) > self::KURULUM_SURESI) {
            unset($_SESSION['_totp_kurulum']);
            $this->geriDon('/admin/profil', 'hata', 'Kurulum süresi doldu. Lütfen yeniden başlatın.');
        }
        $adim = Totp::dogrula((string) $k['gizli'], $this->girdi('kod'));
        if ($adim === null) {
            $this->geriDon('/admin/profil', 'hata', 'Kod hatalı. Uygulamadaki güncel 6 haneli kodu girin.');
        }
        $kodlar = Totp::yedekKodlariUret(8);
        Kullanici::totpEtkinlestir(
            $this->kullanici['id'],
            Totp::sifrele((string) $k['gizli']),
            array_map([Totp::class, 'yedekKodOzeti'], $kodlar),
            $adim
        );
        unset($_SESSION['_totp_kurulum']);
        \App\Core\Denetim::kaydet('2fa.acildi', $this->kullanici['eposta'], null, $this->kullanici['id']);
        Oturum::flash('yedek_kodlar', $kodlar);
        $this->geriDon('/admin/profil', 'basari', 'İki adımlı doğrulama etkinleştirildi. Yedek kodlarınızı güvenli bir yere kaydedin.');
    }

    public function ikiAdimKapat(): void
    {
        $k = Kullanici::idIle($this->kullanici['id']);
        $mevcut = $this->girdi('mevcut_parola');
        if ($k === null || strlen($mevcut) > 1024 || !password_verify($mevcut, (string) $k['parola_hash'])) {
            $this->geriDon('/admin/profil', 'hata', 'Parola hatalı; doğrulama kapatılmadı.');
        }
        Kullanici::totpKapat($this->kullanici['id']);
        \App\Core\Denetim::kaydet('2fa.kapandi', $this->kullanici['eposta'], null, $this->kullanici['id']);
        $this->geriDon('/admin/profil', 'basari', 'İki adımlı doğrulama kapatıldı.');
    }
}
