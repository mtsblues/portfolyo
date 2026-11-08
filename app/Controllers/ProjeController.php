<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Markdown;
use App\Models\Proje;

final class ProjeController extends Denetleyici
{
    private const SAYFA_BOYUTU = 9;

    public function liste(): void
    {
        $s = $this->sayfala(Proje::yayindaSay(), self::SAYFA_BOYUTU);
        $this->gorunum('proje/liste', [
            'sayfaBaslik'   => 'Projelerim · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => 'Geliştirdiğim sistemler, yazılımlar ve altyapı çalışmaları.',
            'projeler'      => Proje::yayindaListe($s['limit'], $s['offset']),
            'sayfalama'     => $s,
            'sayfalamaYol'  => '/projeler',
        ]);
    }

    public function detay(string $slug): void
    {
        $proje = Proje::yayindaSlugIle($slug);
        if ($proje === null) {
            $this->hata(404);
            return;
        }
        $this->gorunum('proje/detay', [
            'sayfaBaslik'   => $proje['baslik'] . ' · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => $proje['aciklama'],
            'kanonik'       => mutlak_url('/proje/' . $proje['slug']),
            'ogGorsel'      => !empty($proje['gorsel']) ? mutlak_gorsel('/yuklemeler/' . $proje['gorsel']) : '',
            'proje'         => $proje,
            'icerikHtml'    => Markdown::cevir((string) $proje['icerik']),
        ]);
    }
}
