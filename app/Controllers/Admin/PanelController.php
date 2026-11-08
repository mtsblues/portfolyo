<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Kurulum;
use App\Core\Saglik;
use App\Models\GirisDenemesi;
use App\Models\Kategori;
use App\Models\Makale;
use App\Models\Mesaj;
use App\Models\Proje;

final class PanelController extends AdminTemel
{
    public function index(): void
    {
        // Dosyalar güncellendiyse (SSH olmadan) şema geçişlerini ilk yönetici girişinde otomatik uygula
        Kurulum::otomatikGuncelle();
        // Cron yoksa bakım görevleri (temizlik, otomatik yedek) panel açılışında ~20 saatte bir çalışır
        \App\Core\Bakim::gerekirse();

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
            // Canlı ortam kontrolü yalnızca yöneticiye gösterilir
            'saglik'       => $this->kullanici['rol'] === 'admin' ? Saglik::kontrolEt($this->kullanici['id']) : [],
            'sonMakaleler' => Makale::adminListe(5, 0),
            'sonMesajlar'  => Mesaj::son(5),
        ]);
    }
}
