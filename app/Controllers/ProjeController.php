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
        $ham = $_GET['teknoloji'] ?? '';
        $teknoloji = is_string($ham) ? trim($ham) : '';
        if ($teknoloji === '' || mb_strlen($teknoloji) > 30 || preg_match('/[\x00-\x1f<>]/u', $teknoloji) === 1) {
            $teknoloji = '';
        }
        $s = $this->sayfala(Proje::yayindaSay($teknoloji), self::SAYFA_BOYUTU);
        $this->gorunum('proje/liste', [
            'sayfaBaslik'   => ($teknoloji !== '' ? $teknoloji . ' projeleri · ' : 'Projelerim · ') . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => 'Geliştirdiğim sistemler, yazılımlar ve altyapı çalışmaları.',
            'kanonik'       => mutlak_url('/projeler'),
            'projeler'      => Proje::yayindaListe($s['limit'], $s['offset'], $teknoloji),
            'teknolojiler'  => Proje::teknolojiler(),
            'seciliTeknoloji' => $teknoloji,
            'sayfalama'     => $s,
            'sayfalamaYol'  => '/projeler',
            'sayfalamaEk'   => $teknoloji !== '' ? ['teknoloji' => $teknoloji] : [],
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
