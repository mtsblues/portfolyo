<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Denetim;
use App\Models\OturumKaydi;

/** Güvenlik merkezi: açık oturumlar (uzaktan sonlandırma), giriş geçmişi ve denetim günlüğü. */
final class GuvenlikController extends AdminTemel
{
    private const FILTRELER = [
        ''       => 'Tümü',
        'giris'  => 'Girişler',
        'parola' => 'Parola',
        'yedek'  => 'Yedekleme',
        'admin'  => 'Yönetici işlemleri',
        'bakim'  => 'Bakım',
    ];

    public function index(): void
    {
        $id = $this->kullanici['id'];
        $yonetici = $this->kullanici['rol'] === 'admin';
        $filtre = (string) ($_GET['filtre'] ?? '');
        if (!array_key_exists($filtre, self::FILTRELER)) {
            $filtre = '';
        }
        // Yönetici tüm kullanıcıların günlüğünü görür; editör yalnızca kendi kayıtlarını.
        $kapsam = $yonetici ? null : $id;
        $s = $this->sayfala(Denetim::say($filtre, $kapsam), 25);

        $this->admin('guvenlik', [
            'sayfaBaslik'  => 'Güvenlik merkezi',
            'oturumlar'    => OturumKaydi::liste($id),
            'buOturum'     => OturumKaydi::mevcutOzet(),
            'kayitlar'     => Denetim::liste($s['limit'], $s['offset'], $filtre, $kapsam),
            'filtre'       => $filtre,
            'filtreler'    => self::FILTRELER,
            'yonetici'     => $yonetici,
            'sayfalama'    => $s,
            'sayfalamaYol' => '/admin/guvenlik',
            'sayfalamaEk'  => $filtre !== '' ? ['filtre' => $filtre] : [],
        ]);
    }

    public function oturumKapat(string $anahtar): void
    {
        if (OturumKaydi::mevcutOzet() === $anahtar) {
            $this->geriDon('/admin/guvenlik', 'hata', 'Bu, şu an kullandığınız oturum. Kapatmak için “Çıkış”ı kullanın.');
        }
        if (!OturumKaydi::kapat($anahtar, $this->kullanici['id'])) {
            $this->geriDon('/admin/guvenlik', 'hata', 'Oturum bulunamadı veya zaten kapalı.');
        }
        Denetim::kaydet('oturum.kapatildi', substr($anahtar, 0, 8), null, $this->kullanici['id']);
        $this->geriDon('/admin/guvenlik', 'basari', 'Oturum sonlandırıldı.');
    }

    public function digerleriniKapat(): void
    {
        $n = OturumKaydi::digerleriniKapat($this->kullanici['id']);
        Denetim::kaydet('oturum.kapatildi', 'diğer oturumlar', (string) $n, $this->kullanici['id']);
        $this->geriDon('/admin/guvenlik', 'basari', $n . ' oturum sonlandırıldı.');
    }
}
