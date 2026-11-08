<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Models\Kategori;
use App\Models\Makale;
use PDOException;

final class KategoriController extends AdminTemel
{
    public function liste(): void
    {
        $this->admin('kategoriler', [
            'sayfaBaslik' => 'Kategoriler',
            'kategoriler' => Kategori::tumu(),
        ]);
    }

    public function kaydet(): void
    {
        $this->isle(null);
    }

    public function guncelle(int $id): void
    {
        if (Kategori::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id);
    }

    private function isle(?int $id): void
    {
        $d = Dogrulayici::yap($_POST, [
            'ad'       => 'gerekli|min:2|max:120',
            'slug'     => 'bos_olabilir|slug|max:180',
            'aciklama' => 'bos_olabilir|max:500',
        ]);
        $hatalar = $d->hatalar();
        $slug = (string) $d->deger('slug');
        if (!isset($hatalar['slug']) && $slug !== '' && Kategori::slugVarMi($slug, $id)) {
            $hatalar['slug'] = 'Bu slug başka bir kategoride kullanılıyor.';
        }
        if ($hatalar !== []) {
            $this->geriDon('/admin/kategoriler', 'hata', 'Kategori: ' . reset($hatalar));
        }
        if ($slug === '') {
            $slug = Kategori::benzersizSlug(Dogrulayici::slugOlustur((string) $d->deger('ad')), $id);
        }
        $aciklama = (string) $d->deger('aciklama');
        try {
            if ($id === null) {
                Kategori::olustur((string) $d->deger('ad'), $slug, $aciklama !== '' ? $aciklama : null);
            } else {
                Kategori::guncelle($id, (string) $d->deger('ad'), $slug, $aciklama !== '' ? $aciklama : null);
            }
        } catch (PDOException $e) {
            gunluk('Kategori kayıt hatası: ' . $e->getMessage());
            $this->geriDon('/admin/kategoriler', 'hata', 'Kategori kaydedilemedi (slug benzersiz olmalı).');
        }
        $this->geriDon('/admin/kategoriler', 'basari', $id === null ? 'Kategori oluşturuldu.' : 'Kategori güncellendi.');
    }

    public function sil(int $id): void
    {
        if (Kategori::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        if (Makale::kategoridekiSayi($id) > 0) {
            $this->geriDon('/admin/kategoriler', 'hata', 'İçinde makale bulunan kategori silinemez. Önce makaleleri başka kategoriye taşıyın.');
        }
        Kategori::sil($id);
        $this->geriDon('/admin/kategoriler', 'basari', 'Kategori silindi.');
    }
}
