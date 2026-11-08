<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Mesaj;

final class MesajController extends AdminTemel
{
    public function liste(): void
    {
        $s = $this->sayfala(Mesaj::say(), $this->sayfaBoyutu());
        $this->admin('mesajlar', [
            'sayfaBaslik'  => 'Mesajlar',
            'mesajlar'     => Mesaj::liste($s['limit'], $s['offset']),
            'sayfalama'    => $s,
            'sayfalamaYol' => '/admin/mesajlar',
        ]);
    }

    public function okundu(int $id): void
    {
        if (Mesaj::idIle($id) === null) {
            $this->hata(404);
            return;
        }
        Mesaj::okunduIsaretle($id);
        $this->geriDon('/admin/mesajlar', 'basari', 'Mesaj okundu olarak işaretlendi.');
    }

    public function sil(int $id): void
    {
        if (!Mesaj::sil($id)) {
            $this->hata(404);
            return;
        }
        $this->geriDon('/admin/mesajlar', 'basari', 'Mesaj silindi.');
    }
}
