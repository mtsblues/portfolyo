<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Core\Yukleme;
use App\Models\Proje;
use PDOException;

final class ProjeController extends AdminTemel
{
    public function liste(): void
    {
        $s = $this->sayfala(Proje::say(), $this->sayfaBoyutu());
        $this->admin('projeler', [
            'sayfaBaslik'  => 'Projeler',
            'projeler'     => Proje::adminListe($s['limit'], $s['offset']),
            'sayfalama'    => $s,
            'sayfalamaYol' => '/admin/projeler',
        ]);
    }

    public function yeni(): void
    {
        $this->admin('proje_form', ['sayfaBaslik' => 'Yeni proje', 'proje' => null]);
    }

    public function duzenle(int $id): void
    {
        $proje = Proje::idIle($id);
        if ($proje === null) {
            $this->hata(404);
            return;
        }
        $this->admin('proje_form', ['sayfaBaslik' => 'Projeyi düzenle', 'proje' => $proje]);
    }

    public function kaydet(): void
    {
        $this->isle(null);
    }

    public function guncelle(int $id): void
    {
        if (Proje::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id);
    }

    private function isle(?int $id): void
    {
        $geri = $id === null ? '/admin/proje/yeni' : '/admin/proje/' . $id . '/duzenle';
        $mevcut = $id !== null ? Proje::idIle($id) : null;

        $d = Dogrulayici::yap($_POST, [
            'baslik'            => 'gerekli|min:3|max:180',
            'slug'              => 'bos_olabilir|slug|max:180',
            'aciklama'          => 'gerekli|min:10|max:500',
            'icerik'            => 'gerekli|min:10|max:300000|cok_satir',
            'proje_url'         => 'bos_olabilir|url',
            'github_url'        => 'bos_olabilir|url',
            'durum'             => 'gerekli|listede:' . implode(',', array_keys(Proje::DURUMLAR)),
            'sira'              => 'bos_olabilir|sayi',
            'tamamlanma_tarihi' => 'bos_olabilir|tarih',
        ]);
        $hatalar = $d->hatalar();
        $slug = (string) $d->deger('slug');
        if (!isset($hatalar['slug']) && $slug !== '' && Proje::slugVarMi($slug, $id)) {
            $hatalar['slug'] = 'Bu slug başka bir projede kullanılıyor.';
        }
        if ($hatalar !== []) {
            $this->formHatasi($geri, $hatalar, $_POST);
        }

        $yuk = Yukleme::kaydet(isset($_FILES['gorsel']) && is_array($_FILES['gorsel']) ? $_FILES['gorsel'] : null);
        if ($yuk['hata'] !== null) {
            $this->formHatasi($geri, ['gorsel' => $yuk['hata']], $_POST);
        }

        if ($slug === '') {
            $slug = Proje::benzersizSlug(Dogrulayici::slugOlustur((string) $d->deger('baslik')), $id);
        }
        $eskiGorsel = $mevcut['gorsel'] ?? null;
        $gorsel = $eskiGorsel;
        if ($yuk['ad'] !== null) {
            $gorsel = $yuk['ad'];
        } elseif (isset($_POST['gorsel_sil'])) {
            $gorsel = null;
        }

        $bos = static fn(string $v): ?string => $v !== '' ? $v : null;
        $veri = [
            'baslik'            => (string) $d->deger('baslik'),
            'slug'              => $slug,
            'aciklama'          => (string) $d->deger('aciklama'),
            'icerik'            => (string) $d->deger('icerik'),
            'gorsel'            => $gorsel,
            'proje_url'         => $bos((string) $d->deger('proje_url')),
            'github_url'        => $bos((string) $d->deger('github_url')),
            'one_cikan'         => isset($_POST['one_cikan']) ? 1 : 0,
            'durum'             => (string) $d->deger('durum'),
            'sira'              => (int) $d->deger('sira', '0'),
            'tamamlanma_tarihi' => $bos((string) $d->deger('tamamlanma_tarihi')),
        ];

        try {
            if ($id === null) {
                Proje::olustur($veri);
            } else {
                Proje::guncelle($id, $veri);
            }
        } catch (PDOException $e) {
            Yukleme::sil($yuk['ad']);
            gunluk('Proje kayıt hatası: ' . $e->getMessage());
            $this->formHatasi($geri, ['slug' => 'Kayıt sırasında bir çakışma oluştu (slug benzersiz olmalı).'], $_POST);
        }

        if ($eskiGorsel !== null && $eskiGorsel !== $gorsel) {
            Yukleme::sil((string) $eskiGorsel);
        }
        $this->geriDon('/admin/projeler', 'basari', $id === null ? 'Proje oluşturuldu.' : 'Proje güncellendi.');
    }

    public function sil(int $id): void
    {
        $proje = Proje::idIle($id);
        if ($proje === null) {
            $this->hata(404);
            return;
        }
        Proje::sil($id);
        Yukleme::sil($proje['gorsel'] !== null ? (string) $proje['gorsel'] : null);
        $this->geriDon('/admin/projeler', 'basari', 'Proje silindi.');
    }
}
