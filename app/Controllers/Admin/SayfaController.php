<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Core\SayfaSablonlari;
use App\Core\Veritabani;
use App\Models\Ayar;
use App\Models\Sayfa;
use PDOException;

/** Serbest sayfalar: gizlilik, KVKK aydınlatma metni, "Bu site hakkında" vb. (Markdown). */
final class SayfaController extends AdminTemel
{
    public function __construct()
    {
        parent::__construct();
        $this->yalnizcaAdmin(); // yasal metinler yalnızca yöneticide
    }

    public function liste(): void
    {
        $mevcutSluglar = array_column(Sayfa::adminListe(), 'slug');
        $this->admin('sayfalar', [
            'sayfaBaslik' => 'Sayfalar',
            'sayfalar'    => Sayfa::adminListe(),
            'sablonlar'   => array_filter(
                SayfaSablonlari::liste(),
                static fn(array $s): bool => !in_array($s['slug'], $mevcutSluglar, true)
            ),
            'kvkkSlug'    => Ayar::al('kvkk_sayfa'),
        ]);
    }

    public function yeni(): void
    {
        $this->admin('sayfa_form', ['sayfaBaslik' => 'Yeni sayfa', 'sayfa' => null]);
    }

    public function duzenle(int $id): void
    {
        $s = Sayfa::idIle($id);
        if ($s === null) {
            $this->hata(404);
            return;
        }
        $this->admin('sayfa_form', ['sayfaBaslik' => 'Sayfayı düzenle', 'sayfa' => $s]);
    }

    public function kaydet(): void
    {
        $this->isle(null);
    }

    public function guncelle(int $id): void
    {
        if (Sayfa::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id);
    }

    private function isle(?int $id): void
    {
        $geri = $id === null ? '/admin/sayfa/yeni' : '/admin/sayfa/' . $id . '/duzenle';
        $d = Dogrulayici::yap($_POST, [
            'baslik'        => 'gerekli|min:2|max:200',
            'slug'          => 'bos_olabilir|slug|max:100',
            'icerik'        => 'gerekli|min:3|max:100000|cok_satir',
            'durum'         => 'gerekli|listede:taslak,yayinda',
            'sira'          => 'bos_olabilir|sayi',
            'meta_aciklama' => 'bos_olabilir|max:300',
        ]);
        $hatalar = $d->hatalar();
        $slug = (string) $d->deger('slug');
        if ($slug === '' && !isset($hatalar['baslik'])) {
            $slug = Dogrulayici::slugOlustur((string) $d->deger('baslik'));
        }
        if ($slug === '') {
            $hatalar['slug'] = 'Geçerli bir slug üretilemedi; elle girin.';
        } elseif (!isset($hatalar['slug']) && self::slugVar($slug, $id)) {
            $hatalar['slug'] = 'Bu slug başka bir sayfada kullanılıyor.';
        }
        $sira = (int) $d->deger('sira', '0');
        if ($sira < 0 || $sira > 1000) {
            $hatalar['sira'] = '0 ile 1000 arasında olmalıdır.';
        }
        if ($hatalar !== []) {
            $this->formHatasi($geri, $hatalar, $_POST);
        }
        $veri = [
            'baslik'        => (string) $d->deger('baslik'),
            'slug'          => $slug,
            'icerik'        => (string) $d->deger('icerik'),
            'durum'         => (string) $d->deger('durum'),
            'menude'        => isset($_POST['menude']) ? 1 : 0,
            'altbilgide'    => isset($_POST['altbilgide']) ? 1 : 0,
            'sira'          => $sira,
            'meta_aciklama' => (string) $d->deger('meta_aciklama') !== '' ? (string) $d->deger('meta_aciklama') : null,
        ];
        try {
            $id === null ? Sayfa::olustur($veri) : Sayfa::guncelle($id, $veri);
        } catch (PDOException $e) {
            gunluk('Sayfa kayıt hatası: ' . $e->getMessage());
            $this->formHatasi($geri, ['slug' => 'Kayıt sırasında bir çakışma oluştu.'], $_POST);
        }
        $this->geriDon('/admin/sayfalar', 'basari', $id === null ? 'Sayfa oluşturuldu.' : 'Sayfa güncellendi.');
    }

    /** Hazır şablondan (KVKK aydınlatma vb.) taslak sayfa oluşturur. */
    public function sablondan(): void
    {
        $anahtar = $this->girdi('sablon');
        $liste = SayfaSablonlari::liste();
        if (!isset($liste[$anahtar])) {
            $this->geriDon('/admin/sayfalar', 'hata', 'Bilinmeyen şablon.');
        }
        $s = $liste[$anahtar];
        if (self::slugVar($s['slug'], null)) {
            $this->geriDon('/admin/sayfalar', 'hata', 'Bu şablondan zaten bir sayfa var.');
        }
        $id = Sayfa::olustur([
            'baslik' => $s['baslik'], 'slug' => $s['slug'], 'icerik' => SayfaSablonlari::icerik($anahtar, Ayar::hepsi()),
            'durum' => 'taslak', 'menude' => 0, 'altbilgide' => 1, 'sira' => 10, 'meta_aciklama' => null,
        ]);
        $this->geriDon('/admin/sayfa/' . $id . '/duzenle', 'basari', '“' . $s['baslik'] . '” taslak olarak oluşturuldu. Metni kendi durumunuza göre gözden geçirip yayınlayın.');
    }

    public function sil(int $id): void
    {
        $s = Sayfa::idIle($id);
        if ($s === null) {
            $this->hata(404);
            return;
        }
        Sayfa::sil($id);
        if (Ayar::al('kvkk_sayfa') === (string) $s['slug']) {
            Ayar::kaydet('kvkk_sayfa', '');
        }
        $this->geriDon('/admin/sayfalar', 'basari', 'Sayfa silindi.');
    }

    private static function slugVar(string $slug, ?int $haric): bool
    {
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM sayfalar WHERE slug = ? AND id <> ?', [$slug, $haric ?? 0]) > 0;
    }
}
