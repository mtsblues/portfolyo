<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\CvYardimci;
use App\Core\Denetleyici;
use App\Core\Markdown;
use App\Models\CvOgesi;
use App\Models\Proje;

/** Herkese açık özgeçmiş (/cv, yazdırılabilir) ve kartvizit indirme (/cv/kartvizit). Ayar kapalıyken 404. */
final class CvController extends Denetleyici
{
    public function goster(): void
    {
        if (!cv_acik()) {
            $this->hata(404);
            return;
        }
        $ad = trim(ayar('cv_ad')) !== '' ? trim(ayar('cv_ad')) : ayar('site_baslik', 'Portfolyo');
        $this->gorunum('cv/goster', [
            'sayfaBaslik'   => 'Özgeçmiş · ' . $ad,
            'sayfaAciklama' => trim(ayar('cv_unvan')) !== '' ? $ad . ' — ' . trim(ayar('cv_unvan')) : 'Özgeçmiş: ' . $ad,
            'kanonik'       => mutlak_url('/cv'),
            'cvAd'          => $ad,
            'ozetHtml'      => trim(ayar('cv_ozet')) !== '' ? Markdown::cevir(ayar('cv_ozet')) : '',
            'deneyimler'    => CvOgesi::yayinda('deneyim'),
            'egitimler'     => CvOgesi::yayinda('egitim'),
            'yetenekGrup'   => CvOgesi::yetenekGruplari(),
            'sertifikalar'  => CvOgesi::yayinda('sertifika'),
            'projeler'      => array_slice(Proje::oneCikanlar(5), 0, 5),
        ]);
    }

    public function vcard(): void
    {
        if (!cv_acik() || ayar('cv_vcard') !== '1') {
            $this->hata(404);
            return;
        }
        $ad = trim(ayar('cv_ad')) !== '' ? trim(ayar('cv_ad')) : ayar('site_baslik', 'Portfolyo');
        $icerik = CvYardimci::vcard([
            'ad'      => $ad,
            'unvan'   => trim(ayar('cv_unvan')),
            'eposta'  => trim(ayar('cv_eposta')),
            'telefon' => trim(ayar('cv_telefon')),
            'url'     => mutlak_url('/'),
            'konum'   => trim(ayar('cv_konum')),
        ]);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $dosya = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Core\Dogrulayici::slugOlustur($ad))), '-');
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ($dosya !== '' ? $dosya : 'kartvizit') . '.vcf"');
        // nosniff ve önbellek başlıkları bootstrap'ta ayarlıdır; oturum çerezi olan yanıt "public" önbelleğe alınmaz.
        echo $icerik;
    }
}
