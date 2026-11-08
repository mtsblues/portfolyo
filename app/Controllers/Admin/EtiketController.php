<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Models\Etiket;
use PDOException;

final class EtiketController extends AdminTemel
{
    public function liste(): void
    {
        $this->admin('etiketler', ['sayfaBaslik' => 'Etiketler', 'etiketler' => Etiket::tumu()]);
    }

    public function kaydet(): void
    {
        $this->isle(null);
    }

    public function guncelle(int $id): void
    {
        if (Etiket::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id);
    }

    private function isle(?int $id): void
    {
        $d = Dogrulayici::yap($_POST, ['ad' => 'gerekli|min:2|max:80']);
        if ($d->basarisiz()) {
            $hatalar = $d->hatalar();
            $this->geriDon('/admin/etiketler', 'hata', 'Etiket: ' . reset($hatalar));
        }
        $slug = Dogrulayici::slugOlustur((string) $d->deger('ad'));
        if ($slug === '' || Etiket::slugVarMi($slug, $id)) {
            $this->geriDon('/admin/etiketler', 'hata', 'Bu etiket zaten var veya geçersiz.');
        }
        try {
            if ($id === null) {
                Etiket::olustur((string) $d->deger('ad'), $slug);
            } else {
                Etiket::guncelle($id, (string) $d->deger('ad'), $slug);
            }
        } catch (PDOException $e) {
            gunluk('Etiket kayıt hatası: ' . $e->getMessage());
            $this->geriDon('/admin/etiketler', 'hata', 'Etiket kaydedilemedi.');
        }
        $this->geriDon('/admin/etiketler', 'basari', $id === null ? 'Etiket oluşturuldu.' : 'Etiket güncellendi.');
    }

    public function sil(int $id): void
    {
        if (Etiket::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        Etiket::sil($id);
        $this->geriDon('/admin/etiketler', 'basari', 'Etiket silindi.');
    }
}
