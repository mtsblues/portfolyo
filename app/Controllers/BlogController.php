<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Dogrulayici;
use App\Core\Markdown;
use App\Models\Etiket;
use App\Models\Kategori;
use App\Models\Makale;

final class BlogController extends Denetleyici
{
    private const SAYFA_BOYUTU = 9;

    public function liste(): void
    {
        $arama = '';
        if (isset($_GET['q']) && is_string($_GET['q'])) {
            $arama = mb_substr(Dogrulayici::temizle($_GET['q']), 0, 100, 'UTF-8');
        }
        $filtre = $arama !== '' ? ['arama' => $arama] : [];
        $this->listeCiz('Bilgi Kütüphanesi', 'Karşılaştığım sorunlar, bulduğum çözümler ve dökümantasyonlar.', $filtre, '/blog', null, $arama);
    }

    public function kategori(string $slug): void
    {
        $kategori = Kategori::slugIle($slug);
        if ($kategori === null) {
            $this->hata(404);
            return;
        }
        $this->listeCiz(
            (string) $kategori['ad'],
            (string) ($kategori['aciklama'] ?? ''),
            ['kategori_id' => (int) $kategori['id']],
            '/kategori/' . $kategori['slug'],
            $kategori['slug'],
            ''
        );
    }

    public function etiket(string $slug): void
    {
        $etiket = Etiket::slugIle($slug);
        if ($etiket === null) {
            $this->hata(404);
            return;
        }
        $this->listeCiz('#' . $etiket['ad'], 'Bu etikete sahip yazılar.', ['etiket_id' => (int) $etiket['id']], '/etiket/' . $etiket['slug'], null, '');
    }

    /** @param array{kategori_id?:int,etiket_id?:int,arama?:string} $filtre */
    private function listeCiz(string $baslik, string $aciklama, array $filtre, string $yol, ?string $aktifKategori, string $arama): void
    {
        $s = $this->sayfala(Makale::yayindaSay($filtre), self::SAYFA_BOYUTU);
        $this->gorunum('blog/liste', [
            'sayfaBaslik'    => $baslik . ' · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama'  => $aciklama,
            'listeBaslik'    => $baslik,
            'listeAciklama'  => $aciklama,
            'makaleler'      => Makale::yayindaListe($s['limit'], $s['offset'], $filtre),
            'kategoriler'    => Kategori::yayindakiler(),
            'aktifKategori'  => $aktifKategori,
            'arama'          => $arama,
            'sayfalama'      => $s,
            'sayfalamaYol'   => $yol,
            'sayfalamaEk'    => $arama !== '' ? ['q' => $arama] : [],
        ]);
    }

    public function detay(string $slug): void
    {
        $makale = Makale::yayindaSlugIle($slug);
        if ($makale === null) {
            $this->hata(404);
            return;
        }
        $icerikHtml = Markdown::cevir((string) $makale['icerik']);
        $this->gorunum('blog/detay', [
            'sayfaBaslik'   => $makale['baslik'] . ' · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => (string) ($makale['ozet'] ?? ozet_kes((string) $makale['icerik'])),
            'sayfaAnahtar'  => (string) ($makale['anahtar_kelimeler'] ?? ''),
            'kanonik'       => mutlak_url('/makale/' . $makale['slug']),
            'ogTur'         => 'article',
            'ogGorsel'      => !empty($makale['kapak_gorsel']) ? mutlak_gorsel('/yuklemeler/' . $makale['kapak_gorsel']) : '',
            'jsonld'        => [
                '@context'      => 'https://schema.org',
                '@type'         => 'BlogPosting',
                'headline'      => (string) $makale['baslik'],
                'datePublished' => gmdate('c', (int) strtotime((string) $makale['yayin_tarihi'] . ' UTC')),
                'dateModified'  => gmdate('c', (int) strtotime((string) $makale['guncellenme'] . ' UTC')),
                'author'        => ['@type' => 'Person', 'name' => (string) $makale['yazar']],
                'mainEntityOfPage' => mutlak_url('/makale/' . $makale['slug']),
            ],
            'makale'        => $makale,
            'icerikHtml'    => $icerikHtml,
            'icindekiler'   => \App\Core\CvYardimci::icindekiler($icerikHtml),
            'onceki'        => Makale::onceki((string) $makale['yayin_tarihi'], (int) $makale['id']),
            'sonraki'       => Makale::sonraki((string) $makale['yayin_tarihi'], (int) $makale['id']),
            'etiketler'     => Makale::etiketleri((int) $makale['id']),
            'ilgili'        => Makale::ilgili((int) $makale['kategori_id'], (int) $makale['id']),
        ]);
    }
}
