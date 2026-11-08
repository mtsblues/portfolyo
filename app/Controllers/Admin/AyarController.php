<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Models\Ayar;

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
                'number' => $on . 'tamsayi',
                'url'    => $on . 'url|max:' . $maks,
                'metin'  => $on . 'max:' . $maks . '|cok_satir',
                'yol', 'yol_dis' => $on . 'max:' . $maks,
                'onay'   => 'bos_olabilir|max:1',
                default  => $on . ($zorunlu ? 'min:2|' : '') . 'max:' . $maks,
            };
        }
        $d = Dogrulayici::yap($_POST, $kurallar);
        $hatalar = $d->hatalar();

        foreach ($alanlar as $anahtar => [, $tip]) {
            if (isset($hatalar[$anahtar])) {
                continue;
            }
            $deger = (string) $d->deger($anahtar);
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
        $limit = (int) $d->deger('iletisim_mesaj_ucret_siniri', '20');
        if (!isset($hatalar['iletisim_mesaj_ucret_siniri']) && ($limit < 1 || $limit > 1000)) {
            $hatalar['iletisim_mesaj_ucret_siniri'] = '1 ile 1000 arasında olmalıdır.';
        }
        if ($hatalar !== []) {
            $this->formHatasi('/admin/ayarlar', $hatalar, $_POST);
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
