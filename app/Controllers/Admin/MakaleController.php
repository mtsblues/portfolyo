<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Core\Veritabani;
use App\Core\Yukleme;
use App\Models\Etiket;
use App\Models\Kategori;
use App\Models\Makale;
use PDOException;

final class MakaleController extends AdminTemel
{
    public function liste(): void
    {
        $s = $this->sayfala(Makale::say(), $this->sayfaBoyutu());
        $this->admin('makaleler', [
            'sayfaBaslik' => 'Makaleler',
            'makaleler'   => Makale::adminListe($s['limit'], $s['offset']),
            'sayfalama'   => $s,
            'sayfalamaYol' => '/admin/makaleler',
        ]);
    }

    public function yeni(): void
    {
        $this->form(null, 'Yeni makale');
    }

    public function duzenle(int $id): void
    {
        $makale = Makale::idIle($id);
        if ($makale === null) {
            $this->hata(404);
            return;
        }
        $this->form($makale, 'Makaleyi düzenle');
    }

    /** @param array<string,mixed>|null $makale */
    private function form(?array $makale, string $baslik): void
    {
        $etiketMetni = '';
        if ($makale !== null) {
            $etiketMetni = implode(', ', array_map(static fn(array $e): string => (string) $e['ad'], Makale::etiketleri((int) $makale['id'])));
        }
        $this->admin('makale_form', [
            'sayfaBaslik' => $baslik,
            'makale'      => $makale,
            'etiketMetni' => $etiketMetni,
            'kategoriler' => Kategori::tumu(),
        ]);
    }

    public function kaydet(): void
    {
        $this->isle(null);
    }

    public function guncelle(int $id): void
    {
        if (Makale::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id);
    }

    private function isle(?int $id): void
    {
        $geri = $id === null ? '/admin/makale/yeni' : '/admin/makale/' . $id . '/duzenle';
        $mevcut = $id !== null ? Makale::idIle($id) : null;

        $d = Dogrulayici::yap($_POST, [
            'baslik'            => 'gerekli|min:3|max:220',
            'slug'              => 'bos_olabilir|slug|max:220',
            'kategori_id'       => 'gerekli|tamsayi',
            'ozet'              => 'bos_olabilir|max:500',
            'icerik'            => 'gerekli|min:10|max:300000|cok_satir',
            'durum'             => 'gerekli|listede:taslak,yayinda,arsiv',
            'anahtar_kelimeler' => 'bos_olabilir|max:500',
            'etiketler'         => 'bos_olabilir|max:500',
            'yayin_tarihi'      => 'bos_olabilir|max:25',
        ]);
        $hatalar = $d->hatalar();

        if (!isset($hatalar['kategori_id']) && Kategori::idIle((int) $d->deger('kategori_id')) === null) {
            $hatalar['kategori_id'] = 'Geçerli bir kategori seçin.';
        }
        $slug = (string) $d->deger('slug');
        if (!isset($hatalar['slug']) && $slug !== '' && Makale::slugVarMi($slug, $id)) {
            $hatalar['slug'] = 'Bu slug başka bir makalede kullanılıyor.';
        }
        $yayinUtc = yerel_den_utc((string) $d->deger('yayin_tarihi'));
        if (!isset($hatalar['yayin_tarihi']) && (string) $d->deger('yayin_tarihi') !== '' && $yayinUtc === null) {
            $hatalar['yayin_tarihi'] = 'Geçerli bir tarih girin.';
        }
        if ($hatalar !== []) {
            $this->formHatasi($geri, $hatalar, $_POST);
        }

        $yuk = Yukleme::kaydet(isset($_FILES['kapak']) && is_array($_FILES['kapak']) ? $_FILES['kapak'] : null);
        if ($yuk['hata'] !== null) {
            $this->formHatasi($geri, ['kapak' => $yuk['hata']], $_POST);
        }

        if ($slug === '') {
            $slug = Makale::benzersizSlug(Dogrulayici::slugOlustur((string) $d->deger('baslik')), $id);
        }
        $durum = (string) $d->deger('durum');
        if ($durum === 'yayinda' && $yayinUtc === null) {
            $yayinUtc = $mevcut['yayin_tarihi'] ?? gmdate('Y-m-d H:i:s');
        }
        $eskiKapak = $mevcut['kapak_gorsel'] ?? null;
        $kapak = $eskiKapak;
        if ($yuk['ad'] !== null) {
            $kapak = $yuk['ad'];
        } elseif (isset($_POST['kapak_sil'])) {
            $kapak = null;
        }

        $ozet = (string) $d->deger('ozet');
        $anahtar = (string) $d->deger('anahtar_kelimeler');
        $veri = [
            'kategori_id'       => (int) $d->deger('kategori_id'),
            'kullanici_id'      => $this->kullanici['id'],
            'baslik'            => (string) $d->deger('baslik'),
            'slug'              => $slug,
            'ozet'              => $ozet !== '' ? $ozet : null,
            'icerik'            => (string) $d->deger('icerik'),
            'durum'             => $durum,
            'one_cikan'         => isset($_POST['one_cikan']) ? 1 : 0,
            'okunma_suresi'     => Makale::okunmaSuresi((string) $d->deger('icerik')),
            'kapak_gorsel'      => $kapak,
            'anahtar_kelimeler' => $anahtar !== '' ? $anahtar : null,
            'yayin_tarihi'      => $yayinUtc,
        ];
        $etiketMetni = (string) $d->deger('etiketler');

        try {
            Veritabani::islem(function () use ($id, $veri, $etiketMetni): void {
                $mid = $id;
                if ($mid === null) {
                    $mid = Makale::olustur($veri);
                } else {
                    Makale::guncelle($mid, $veri);
                }
                Makale::etiketleriSenkronla($mid, Etiket::adlardanKimlikler($etiketMetni));
            });
        } catch (PDOException $e) {
            if ($yuk['ad'] !== null) {
                Yukleme::sil($yuk['ad']);
            }
            gunluk('Makale kayıt hatası: ' . $e->getMessage());
            $this->formHatasi($geri, ['slug' => 'Kayıt sırasında bir çakışma oluştu (slug benzersiz olmalı).'], $_POST);
        }

        if ($eskiKapak !== null && $eskiKapak !== $kapak) {
            Yukleme::sil((string) $eskiKapak);
        }
        $this->geriDon('/admin/makaleler', 'basari', $id === null ? 'Makale oluşturuldu.' : 'Makale güncellendi.');
    }

    public function sil(int $id): void
    {
        $makale = Makale::idIle($id);
        if ($makale === null) {
            $this->hata(404);
            return;
        }
        Makale::sil($id);
        Yukleme::sil($makale['kapak_gorsel'] !== null ? (string) $makale['kapak_gorsel'] : null);
        $this->geriDon('/admin/makaleler', 'basari', 'Makale silindi.');
    }
}
