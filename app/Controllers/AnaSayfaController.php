<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Markdown;
use App\Models\Kategori;
use App\Models\Makale;
use App\Models\Proje;

final class AnaSayfaController extends Denetleyici
{
    public function index(): void
    {
        $this->gorunum('home/index', [
            'sayfaBaslik'   => ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => ayar('site_aciklama'),
            'kanonik'       => mutlak_url('/'),
            'jsonld'        => [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => ayar('site_baslik', 'Portfolyo'),
                'url'      => mutlak_url('/'),
            ],
            'projeler'      => Proje::son(3),
            'makaleler'     => Makale::son(3),
            'kategoriler'   => Kategori::yayindakiler(),
            'hizmetler'     => \App\Models\CvOgesi::yayinda('hizmet'),
            'referanslar'   => \App\Models\CvOgesi::yayinda('referans'),
        ]);
    }

    public function hakkimda(): void
    {
        if (!hakkimda_var()) {
            $this->hata(404);
            return;
        }
        $this->gorunum('home/hakkimda', [
            'sayfaBaslik'   => 'Hakkımda · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => ozet_kes(strip_tags(Markdown::cevir(ayar('hakkimda_icerik'))), 160),
            'kanonik'       => mutlak_url('/hakkimda'),
            'ogGorsel'      => mutlak_gorsel(ayar('hakkimda_foto')),
            'icerikHtml'    => Markdown::cevir(ayar('hakkimda_icerik')),
        ]);
    }

    public function sitemap(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/xml; charset=utf-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $ekle = static function (string $yol, ?string $son = null) use (&$xml): void {
            $xml .= '  <url><loc>' . e(mutlak_url($yol)) . '</loc>';
            if ($son !== null) {
                $xml .= '<lastmod>' . e(gmdate('Y-m-d', (int) strtotime($son . ' UTC'))) . '</lastmod>';
            }
            $xml .= "</url>\n";
        };
        foreach (['/', '/projeler', '/blog', '/iletisim'] as $y) {
            $ekle($y);
        }
        if (hakkimda_var()) {
            $ekle('/hakkimda');
        }
        if (cv_acik()) {
            $ekle('/cv');
        }
        foreach (Makale::sitemapIcin() as $m) {
            $ekle('/makale/' . $m['slug'], (string) $m['guncellenme']);
        }
        foreach (Proje::sitemapIcin() as $p) {
            $ekle('/proje/' . $p['slug'], (string) $p['guncellenme']);
        }
        foreach (Kategori::yayindakiler() as $k) {
            $ekle('/kategori/' . $k['slug']);
        }
        foreach (\App\Models\Sayfa::sitemapIcin() as $sf) {
            $ekle('/sayfa/' . $sf['slug'], (string) $sf['guncellenme']);
        }
        echo $xml . '</urlset>';
    }

    /** RSS 2.0 beslemesi: son 20 yayınlanmış makale (tam içerik + özet). */
    public function rss(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/rss+xml; charset=utf-8');
        $kok = site_kok_url();
        $mutlaklastir = static fn(string $html): string => $kok === '' ? $html
            : (string) preg_replace('/\b(src|href)="\/(?!\/)/', '$1="' . $kok . '/', $html);
        $cdata = static fn(string $s): string => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';

        $makaleler = Makale::rssIcin(20);
        $son = $makaleler !== [] ? (string) $makaleler[0]['yayin_tarihi'] : gmdate('Y-m-d H:i:s');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n<channel>\n"
            . '<title>' . e(ayar('site_baslik', 'Portfolyo')) . "</title>\n"
            . '<link>' . e(mutlak_url('/')) . "</link>\n"
            . '<description>' . e(ayar('site_aciklama', ayar('site_baslik', 'Portfolyo'))) . "</description>\n"
            . "<language>tr</language>\n"
            . '<lastBuildDate>' . gmdate('D, d M Y H:i:s', (int) strtotime($son . ' UTC')) . " +0000</lastBuildDate>\n"
            . '<atom:link href="' . e(mutlak_url('/rss.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($makaleler as $m) {
            $link = mutlak_url('/makale/' . $m['slug']);
            $xml .= "<item>\n"
                . '<title>' . e((string) $m['baslik']) . "</title>\n"
                . '<link>' . e($link) . "</link>\n"
                . '<guid isPermaLink="true">' . e($link) . "</guid>\n"
                . '<pubDate>' . gmdate('D, d M Y H:i:s', (int) strtotime((string) $m['yayin_tarihi'] . ' UTC')) . " +0000</pubDate>\n"
                . '<category>' . e((string) $m['kategori_ad']) . "</category>\n"
                . '<description>' . e(ozet_kes((string) ($m['ozet'] ?? $m['icerik']), 300)) . "</description>\n"
                . '<content:encoded>' . $cdata($mutlaklastir(Markdown::cevir((string) $m['icerik']))) . "</content:encoded>\n"
                . "</item>\n";
        }
        echo $xml . "</channel>\n</rss>\n";
    }

    public function robots(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo "User-agent: *\nDisallow: /admin\nAllow: /\n\nSitemap: " . mutlak_url('/sitemap.xml') . "\n";
    }
}
