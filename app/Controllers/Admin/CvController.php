<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\CvYardimci;
use App\Core\Dogrulayici;
use App\Models\CvOgesi;
use PDOException;

/** Özgeçmiş öğeleri: deneyim, eğitim, yetenek, sertifika, hizmet, referans. Yalnızca yönetici. */
final class CvController extends AdminTemel
{
    public function __construct()
    {
        parent::__construct();
        $this->yalnizcaAdmin();
    }

    public function liste(): void
    {
        $tur = $this->turSec(is_string($_GET['tur'] ?? null) ? $_GET['tur'] : 'deneyim');
        $this->admin('cv', [
            'sayfaBaslik' => 'Özgeçmiş',
            'tur'         => $tur,
            'ogeler'      => CvOgesi::adminListe($tur),
            'sayilar'     => CvOgesi::sayilar(),
        ]);
    }

    public function yeni(): void
    {
        $tur = $this->turSec(is_string($_GET['tur'] ?? null) ? $_GET['tur'] : 'deneyim');
        $this->admin('cv_form', ['sayfaBaslik' => 'Yeni ' . CvOgesi::TURLER[$tur]['tekil'], 'oge' => null, 'tur' => $tur]);
    }

    public function duzenle(int $id): void
    {
        $o = CvOgesi::idIle($id);
        if ($o === null) {
            $this->hata(404);
            return;
        }
        $this->admin('cv_form', ['sayfaBaslik' => 'Düzenle: ' . $o['baslik'], 'oge' => $o, 'tur' => (string) $o['tur']]);
    }

    public function kaydet(): void
    {
        $this->isle(null, $this->turSec(is_string($_POST['tur'] ?? null) ? $_POST['tur'] : ''));
    }

    public function guncelle(int $id): void
    {
        $o = CvOgesi::idIle($id);
        if ($o === null) {
            $this->hata(404);
            return;
        }
        $this->isle($id, (string) $o['tur']);
    }

    public function sil(int $id): void
    {
        $o = CvOgesi::idIle($id);
        if ($o === null) {
            $this->hata(404);
            return;
        }
        CvOgesi::sil($id);
        $this->geriDon('/admin/cv?tur=' . $o['tur'], 'basari', 'Silindi.');
    }

    /** Görünürlüğü tek tıkla değiştirir. */
    public function gorunurDegistir(int $id): void
    {
        $o = CvOgesi::idIle($id);
        if ($o === null) {
            $this->hata(404);
            return;
        }
        \App\Core\Veritabani::calistir('UPDATE cv_ogeleri SET gorunur = ? WHERE id = ?', [(int) $o['gorunur'] === 1 ? 0 : 1, $id]);
        $this->geriDon('/admin/cv?tur=' . $o['tur'], 'basari', (int) $o['gorunur'] === 1 ? 'Gizlendi.' : 'Yayında.');
    }

    private function turSec(string $tur): string
    {
        return CvOgesi::turGecerli($tur) ? $tur : 'deneyim';
    }

    private function isle(?int $id, string $tur): void
    {
        $geri = $id === null ? '/admin/cv/yeni?tur=' . $tur : '/admin/cv/' . $id . '/duzenle';
        $alanlar = CvOgesi::TURLER[$tur]['alanlar'];
        $kural = [
            'baslik' => 'gerekli|min:2|max:200',
            'sira'   => 'bos_olabilir|sayi',
        ];
        $kural['kurum'] = 'bos_olabilir|max:200';
        $kural['konum'] = 'bos_olabilir|max:120';
        $kural['url'] = 'bos_olabilir|url|max:255';
        $kural['aciklama'] = ($tur === 'referans' || $tur === 'hizmet' ? 'gerekli|min:5|' : 'bos_olabilir|') . 'max:3000|cok_satir';
        $d = Dogrulayici::yap($_POST, $kural);
        $hatalar = $d->hatalar();

        $ay = static function (string $k) use (&$hatalar): ?string {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v === '') {
                return null;
            }
            if (!CvYardimci::ayGecerli($v)) {
                $hatalar[$k] = 'Geçerli bir ay seçin.';
                return null;
            }
            return $v;
        };
        $baslangic = isset($alanlar['tarih']) || isset($alanlar['baslangic']) ? $ay('baslangic') : null;
        $devam = isset($alanlar['tarih']) && isset($_POST['devam']) ? 1 : 0;
        $bitis = (isset($alanlar['tarih']) || isset($alanlar['bitis'])) && $devam === 0 ? $ay('bitis') : null;
        if ($baslangic !== null && $bitis !== null && $bitis < $baslangic) {
            $hatalar['bitis'] = 'Bitiş tarihi başlangıçtan önce olamaz.';
        }

        $seviye = null;
        if (isset($alanlar['seviye'])) {
            $s = trim((string) ($_POST['seviye'] ?? ''));
            if ($s !== '') {
                if (preg_match('/^\d{1,3}$/', $s) !== 1 || (int) $s > 100) {
                    $hatalar['seviye'] = '0 ile 100 arasında bir sayı girin.';
                } else {
                    $seviye = (int) $s;
                }
            }
        }
        $sira = (int) $d->deger('sira', '0');
        if ($sira < 0 || $sira > 100000) {
            $hatalar['sira'] = '0 ile 100000 arasında olmalıdır.';
        }
        if ($hatalar !== []) {
            $this->formHatasi($geri, $hatalar, $_POST);
        }

        $bos = static fn(string $v): ?string => $v !== '' ? $v : null;
        $veri = [
            'tur'       => $tur,
            'baslik'    => (string) $d->deger('baslik'),
            'kurum'     => isset($alanlar['kurum']) ? $bos((string) $d->deger('kurum')) : null,
            'konum'     => isset($alanlar['konum']) ? $bos((string) $d->deger('konum')) : null,
            'baslangic' => $baslangic,
            'bitis'     => $bitis,
            'devam'     => $devam,
            'seviye'    => $seviye,
            'aciklama'  => isset($alanlar['aciklama']) ? $bos((string) $d->deger('aciklama')) : null,
            'url'       => isset($alanlar['url']) ? $bos((string) $d->deger('url')) : null,
            'sira'      => $sira,
            'gorunur'   => isset($_POST['gorunur']) ? 1 : 0,
        ];
        try {
            $id === null ? CvOgesi::olustur($veri) : CvOgesi::guncelle($id, $veri);
        } catch (PDOException $e) {
            gunluk('Özgeçmiş öğesi kayıt hatası: ' . $e->getMessage());
            $this->formHatasi($geri, ['baslik' => 'Kayıt sırasında bir hata oluştu.'], $_POST);
        }
        $this->geriDon('/admin/cv?tur=' . $tur, 'basari', $id === null ? 'Eklendi.' : 'Güncellendi.');
    }
}
