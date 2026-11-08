<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Markdown;
use App\Models\Sayfa;

/** Yayındaki serbest sayfalar (/sayfa/{slug}). Taslaklar 404 verir. */
final class SayfaController extends Denetleyici
{
    public function detay(string $slug): void
    {
        $s = Sayfa::yayindaSlugIle($slug);
        if ($s === null) {
            $this->hata(404);
            return;
        }
        $html = Markdown::cevir((string) $s['icerik']);
        $this->gorunum('sayfa/detay', [
            'sayfaBaslik'   => $s['baslik'] . ' · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => (string) ($s['meta_aciklama'] ?? '') !== '' ? (string) $s['meta_aciklama'] : ozet_kes(strip_tags($html), 160),
            'kanonik'       => mutlak_url('/sayfa/' . $s['slug']),
            'sayfa'         => $s,
            'icerikHtml'    => $html,
        ]);
    }
}
