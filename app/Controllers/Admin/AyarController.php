<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Models\Ayar;
use App\Models\Sayfa;

final class AyarController extends AdminTemel
{
    public function liste(): void
    {
        $this->yalnizcaAdmin();
        $this->admin('ayarlar', ['sayfaBaslik' => 'Ayarlar', 'ayarlar' => Ayar::hepsi()]);
    }

    public function guncelle(): void
    {
        $this->yalnizcaAdmin();
        $alanlar = Ayar::alanlar();

        $kurallar = [];
        foreach ($alanlar as $anahtar => [, $tip, $maks, $zorunlu]) {
            $on = $zorunlu ? 'gerekli|' : 'bos_olabilir|';
            $kurallar[$anahtar] = match ($tip) {
                'email'  => $on . 'eposta|max:' . $maks,
                'number' => $on . 'sayi',
                'url'    => $on . 'url|max:' . $maks,
                'metin'  => $on . 'max:' . $maks . '|cok_satir',
                'yol', 'yol_dis' => $on . 'max:' . $maks,
                'onay'   => 'bos_olabilir|max:1',
                'secim'  => $on . 'max:' . $maks,
                default  => $on . ($zorunlu ? 'min:2|' : '') . 'max:' . $maks,
            };
        }
        $d = Dogrulayici::yap($_POST, $kurallar);
        $hatalar = $d->hatalar();

        foreach ($alanlar as $anahtar => $tanim) {
            $tip = $tanim[1];
            if (isset($hatalar[$anahtar])) {
                continue;
            }
            $deger = (string) $d->deger($anahtar);
            if ($tip === 'secim' && !array_key_exists($deger, (array) ($tanim[5] ?? []))) {
                $hatalar[$anahtar] = 'Listeden bir seçenek seçin.';
            }
            if ($tip === 'number' && isset($tanim[5]) && ($deger === '' || (int) $deger < $tanim[5][0] || (int) $deger > $tanim[5][1])) {
                $hatalar[$anahtar] = $tanim[5][0] . ' ile ' . $tanim[5][1] . ' arasında olmalıdır.';
            }
            if ($anahtar === 'kvkk_sayfa' && $deger !== '') {
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $deger) !== 1 || !Sayfa::yayindaMi($deger)) {
                    $hatalar[$anahtar] = 'Bu adreste YAYINDA bir sayfa yok. Önce Sayfalar bölümünde oluşturup durumunu “Yayında” yapın.';
                }
            }
            if ($tip === 'yol' && $deger !== '' && !self::gecerliYerelYol($deger)) {
                $hatalar[$anahtar] = 'Önce Medya sayfasına yükleyin; ardından /yuklemeler/dosya-adi.png biçimindeki yolu yazın (harici adresler güvenlik politikası nedeniyle gösterilemez).';
            }
            if ($tip === 'yol_dis' && $deger !== '' && !self::gecerliYerelYol($deger) && preg_match('#^https://[^\s/]+/\S*$#i', $deger) !== 1) {
                $hatalar[$anahtar] = '/yuklemeler/dosya-adi.jpg biçiminde bir yol veya https:// ile başlayan bir adres girin.';
            }
            if ($tip === 'url' && $deger !== '' && !str_starts_with(strtolower($deger), 'https://') && !str_starts_with(strtolower($deger), 'http://')) {
                $hatalar[$anahtar] = 'Adres http:// veya https:// ile başlamalıdır.';
            }
        }
        if ($hatalar !== []) {
            $this->formHatasi('/admin/ayarlar', $hatalar, $_POST);
        }

        // 2FA zorunluluğu açılıyorsa, kilitlenmeyi önlemek için önce kendi 2FA'nız açık olmalı
        if (isset($_POST['zorunlu_2fa']) && !\App\Models\Kullanici::ikiAdimAktifMi($this->kullanici['id'])) {
            $this->formHatasi('/admin/ayarlar', ['zorunlu_2fa' => 'Önce kendi hesabınızda 2FA’yı etkinleştirin (Profil sayfası); aksi halde kendinizi kilitlersiniz.'], $_POST);
        }

        foreach ($alanlar as $anahtar => [, $tip]) {
            $deger = $tip === 'onay' ? (isset($_POST[$anahtar]) ? '1' : '0') : (string) $d->deger($anahtar);
            Ayar::kaydet($anahtar, $deger);
        }
        $this->geriDon('/admin/ayarlar', 'basari', 'Ayarlar kaydedildi.');
    }

    private static function gecerliYerelYol(string $deger): bool
    {
        return preg_match('#^/yuklemeler/[a-f0-9]{32}\.(?:jpg|png|webp|gif)$#', $deger) === 1;
    }
}
