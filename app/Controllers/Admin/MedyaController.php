<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Dogrulayici;
use App\Core\Markdown;
use App\Core\Yukleme;
use App\Models\Medya;

/** Medya kütüphanesi (görsel + video), editör için JSON uçları ve Markdown önizlemesi. */
final class MedyaController extends AdminTemel
{
    private const SAYFA = 24;

    public function liste(): void
    {
        $tur = $this->turFiltresi();
        $s = $this->sayfala(Medya::say($tur), self::SAYFA);
        $this->admin('medya', [
            'sayfaBaslik'  => 'Medya kütüphanesi',
            'ogeler'       => Medya::liste($s['limit'], $s['offset'], $tur),
            'sayfalama'    => $s,
            'sayfalamaYol' => '/admin/medya',
            'sayfalamaEk'  => $tur !== null ? ['tur' => $tur] : [],
            'tur'          => $tur,
            'gorselSinir'  => (int) (config('yukleme.maks_bayt', 5242880) / 1048576),
            'videoSinir'   => (int) (config('yukleme.video_maks_bayt', 52428800) / 1048576),
        ]);
    }

    /** Editördeki "Kütüphane" penceresi için son medya. */
    public function kutuphaneJson(): void
    {
        $tur = $this->turFiltresi();
        $ogeler = [];
        foreach (Medya::liste(60, 0, $tur) as $m) {
            $ogeler[] = [
                'id'    => (int) $m['id'],
                'tur'   => $m['tur'],
                'url'   => url('/yuklemeler/' . $m['dosya']),
                'alt'   => (string) ($m['alt_metin'] ?? ''),
                'parca' => Medya::parca($m),
            ];
        }
        $this->json(['basarili' => true, 'ogeler' => $ogeler]);
    }

    public function yukle(): void
    {
        $dosyalar = Yukleme::dosyaListesi(isset($_FILES['dosyalar']) && is_array($_FILES['dosyalar']) ? $_FILES['dosyalar'] : null);
        $yuklenen = [];
        $hatalar = [];
        foreach (array_slice($dosyalar, 0, 20) as $d) {
            $ad = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($d['name'] ?? '')) ?? '', 0, 80, 'UTF-8');
            $s = Yukleme::kaydet($d, true, $this->kullanici['id']);
            if ($s['hata'] !== null) {
                $hatalar[] = ($ad !== '' ? $ad . ': ' : '') . $s['hata'];
            } elseif ($s['ad'] !== null) {
                $m = ['dosya' => $s['ad'], 'tur' => $s['tur'] ?? 'gorsel', 'alt_metin' => null];
                $yuklenen[] = ['tur' => $m['tur'], 'url' => url('/yuklemeler/' . $s['ad']), 'parca' => Medya::parca($m)];
            }
        }

        if ($this->istekJson()) {
            $this->json(['basarili' => $hatalar === [] && $yuklenen !== [], 'yuklenen' => $yuklenen, 'hatalar' => $hatalar],
                $yuklenen === [] && $hatalar !== [] ? 422 : 200);
        }
        if ($yuklenen === [] && $hatalar === []) {
            $this->geriDon('/admin/medya', 'hata', 'Dosya seçilmedi (veya dosya php.ini yükleme sınırını aşıyor).');
        }
        if ($hatalar !== []) {
            \App\Core\Oturum::flash('hata', implode(' | ', $hatalar));
        }
        if ($yuklenen !== []) {
            \App\Core\Oturum::flash('basari', count($yuklenen) . ' dosya yüklendi.');
        }
        $this->yonlendir('/admin/medya');
    }

    public function guncelle(int $id): void
    {
        if (Medya::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        $d = Dogrulayici::yap($_POST, ['alt_metin' => 'bos_olabilir|max:255']);
        if ($d->basarisiz()) {
            $this->geriDon('/admin/medya', 'hata', 'Alt metin en çok 255 karakter olabilir.');
        }
        $alt = (string) $d->deger('alt_metin');
        Medya::altGuncelle($id, $alt !== '' ? $alt : null);
        $this->geriDon('/admin/medya', 'basari', 'Alt metin kaydedildi.');
    }

    public function sil(int $id): void
    {
        $m = Medya::idIle($id);
        if ($m === null) {
            $this->hata(404);
            return;
        }
        $kullanim = Medya::kullanimlar((string) $m['dosya']);
        if ($kullanim !== [] && !isset($_POST['zorla'])) {
            $this->geriDon('/admin/medya', 'hata',
                'Bu dosya kullanımda (' . implode('; ', array_slice($kullanim, 0, 3)) . '). Yine de silmek için onay kutusunu işaretleyin.');
        }
        Yukleme::sil((string) $m['dosya']);
        $this->geriDon('/admin/medya', 'basari', 'Dosya silindi.');
    }

    /** Editör önizlemesi: sunucudaki güvenli Markdown çeviricisinin çıktısını döner. */
    public function onizle(): void
    {
        $metin = mb_substr($this->girdi('metin'), 0, 300000, 'UTF-8');
        $this->json(['basarili' => true, 'html' => Markdown::cevir($metin)]);
    }

    private function turFiltresi(): ?string
    {
        $t = $_GET['tur'] ?? null;
        return is_string($t) && in_array($t, ['gorsel', 'video'], true) ? $t : null;
    }
}
