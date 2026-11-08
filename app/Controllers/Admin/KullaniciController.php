<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Models\Kullanici;
use PDOException;

final class KullaniciController extends AdminTemel
{
    public function liste(): void
    {
        $this->yalnizcaAdmin();
        $this->admin('kullanicilar', ['sayfaBaslik' => 'Kullanıcılar', 'kullanicilar' => Kullanici::tumu()]);
    }

    public function kaydet(): void
    {
        $this->yalnizcaAdmin();
        $d = Dogrulayici::yap($_POST, [
            'ad_soyad'      => 'gerekli|min:2|max:120',
            'eposta'        => 'gerekli|eposta',
            'rol'           => 'gerekli|listede:admin,editor',
            'parola'        => 'gerekli|parola|parola_alani',
        ]);
        $hatalar = $d->hatalar();
        if (!isset($hatalar['eposta'])) {
            $eposta = mb_strtolower((string) $d->deger('eposta'), 'UTF-8');
            if (Kullanici::epostaVarMi($eposta)) {
                $hatalar['eposta'] = 'Bu e-posta zaten kayıtlı.';
            }
        }
        if (($hatalar === []) && $this->girdi('parola') !== $this->girdi('parola_tekrar')) {
            $hatalar['parola'] = 'Parolalar eşleşmiyor.';
        }
        if ($hatalar !== []) {
            $this->geriDon('/admin/kullanicilar', 'hata', 'Kullanıcı: ' . reset($hatalar));
        }
        try {
            Kullanici::olustur(
                (string) $d->deger('ad_soyad'),
                mb_strtolower((string) $d->deger('eposta'), 'UTF-8'),
                $this->girdi('parola'),
                (string) $d->deger('rol')
            );
        } catch (PDOException $e) {
            gunluk('Kullanıcı kayıt hatası: ' . $e->getMessage());
            $this->geriDon('/admin/kullanicilar', 'hata', 'Kullanıcı oluşturulamadı.');
        }
        $this->geriDon('/admin/kullanicilar', 'basari', 'Kullanıcı oluşturuldu.');
    }

    public function guncelle(int $id): void
    {
        $this->yalnizcaAdmin();
        $hedef = Kullanici::idIle($id);
        if ($hedef === null) {
            $this->hata(404);
            return;
        }
        $d = Dogrulayici::yap($_POST, [
            'ad_soyad' => 'gerekli|min:2|max:120',
            'rol'      => 'gerekli|listede:admin,editor',
            'parola'   => 'bos_olabilir|parola|parola_alani',
        ]);
        $hatalar = $d->hatalar();
        $aktif = isset($_POST['aktif']);
        $rol = (string) $d->deger('rol');
        $kendisi = $id === $this->kullanici['id'];

        if ($kendisi && (!$aktif || $rol !== 'admin')) {
            $hatalar['rol'] = 'Kendi hesabınızı devre dışı bırakamaz veya yetkisini düşüremezsiniz.';
        }
        $yeniParola = $this->girdi('parola');
        if ($hatalar === [] && $yeniParola !== '' && $yeniParola !== $this->girdi('parola_tekrar')) {
            $hatalar['parola'] = 'Parolalar eşleşmiyor.';
        }
        if ($hatalar === [] && $hedef['rol'] === 'admin' && (int) $hedef['aktif'] === 1 && ($rol !== 'admin' || !$aktif)
            && Kullanici::aktifAdminSayisi() <= 1) {
            $hatalar['rol'] = 'Sistemde en az bir aktif yönetici kalmalıdır.';
        }
        if ($hatalar !== []) {
            $this->geriDon('/admin/kullanicilar', 'hata', 'Kullanıcı: ' . reset($hatalar));
        }

        Kullanici::profilGuncelle($id, (string) $d->deger('ad_soyad'), $rol, $aktif);
        if ($yeniParola !== '') {
            Kullanici::parolaGuncelle($id, $yeniParola);
        }
        $this->geriDon('/admin/kullanicilar', 'basari', 'Kullanıcı güncellendi.');
    }

    public function sil(int $id): void
    {
        $this->yalnizcaAdmin();
        $hedef = Kullanici::idIle($id);
        if ($hedef === null) {
            $this->hata(404);
            return;
        }
        if ($id === $this->kullanici['id']) {
            $this->geriDon('/admin/kullanicilar', 'hata', 'Kendi hesabınızı silemezsiniz.');
        }
        try {
            Kullanici::sil($id);
        } catch (PDOException $e) {
            // makaleler.kullanici_id ON DELETE RESTRICT
            $this->geriDon('/admin/kullanicilar', 'hata', 'Makalesi bulunan kullanıcı silinemez; devre dışı bırakın.');
        }
        $this->geriDon('/admin/kullanicilar', 'basari', 'Kullanıcı silindi.');
    }
}
