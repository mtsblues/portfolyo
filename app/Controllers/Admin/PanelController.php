<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\GirisDenemesi;
use App\Models\Kategori;
use App\Models\Makale;
use App\Models\Mesaj;
use App\Models\Proje;

final class PanelController extends AdminTemel
{
    public function index(): void
    {
        $this->admin('panel', [
            'sayfaBaslik' => 'Dashboard',
            'istatistik'  => [
                'makale_toplam'  => Makale::say(),
                'makale_yayinda' => Makale::durumSayisi('yayinda'),
                'makale_taslak'  => Makale::durumSayisi('taslak'),
                'proje'          => Proje::say(),
                'kategori'       => Kategori::say(),
                'mesaj_toplam'   => Mesaj::say(),
                'basarisiz_giris' => GirisDenemesi::son24SaatBasarisiz(),
            ],
            'sonMakaleler' => Makale::adminListe(5, 0),
            'sonMesajlar'  => Mesaj::son(5),
        ]);
    }
}
