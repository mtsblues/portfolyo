<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Denetleyici;
use App\Core\Oturum;
use App\Models\Kullanici;
use App\Models\Mesaj;

/** Tüm yönetim denetleyicilerinin tabanı: kimlik doğrulama kapısı. */
abstract class AdminTemel extends Denetleyici
{
    /** @var array{id:int,ad_soyad:string,eposta:string,rol:string} */
    protected array $kullanici;

    public function __construct()
    {
        $k = Oturum::kullanici();
        if ($k === null) {
            if ($this->istekJson()) {
                $this->json(['basarili' => false, 'hata' => 'Oturum açmanız gerekiyor.'], 401);
            }
            $this->yonlendir('/admin/giris');
        }
        // Devre dışı bırakılan / silinen kullanıcının açık oturumunu düşür
        $db = Kullanici::idIle((int) $k['id']);
        if ($db === null || (int) $db['aktif'] !== 1) {
            Oturum::cikis();
            $this->yonlendir('/admin/giris');
        }
        $this->kullanici = [
            'id'       => (int) $db['id'],
            'ad_soyad' => (string) $db['ad_soyad'],
            'eposta'   => (string) $db['eposta'],
            'rol'      => (string) $db['rol'],
        ];
    }

    /** @param array<string,mixed> $veri */
    protected function admin(string $sablon, array $veri = []): void
    {
        $this->gorunum('admin/' . $sablon, $veri + [
            'kullanici' => $this->kullanici,
            'okunmamis' => Mesaj::okunmamisSay(),
        ], 'admin');
    }

    protected function yalnizcaAdmin(): void
    {
        if ($this->kullanici['rol'] !== 'admin') {
            $this->hata(403, 'Bu işlem için yönetici yetkisi gerekir.');
            exit;
        }
    }

    protected function geriDon(string $yol, string $tur, string $mesaj): never
    {
        Oturum::flash($tur, $mesaj);
        $this->yonlendir($yol);
    }

    /**
     * Doğrulama hatasında formu hata + eski girdi ile yeniden gösterir (PRG).
     * @param array<string,string> $hatalar
     * @param array<string,mixed> $girdi
     */
    protected function formHatasi(string $geriYol, array $hatalar, array $girdi): never
    {
        foreach (['parola', 'parola_tekrar', 'csrf_token'] as $gizli) {
            unset($girdi[$gizli]);
        }
        Oturum::flash('hatalar', $hatalar);
        Oturum::flash('eski', array_map(static fn($v) => is_scalar($v) ? (string) $v : '', $girdi));
        $this->geriDon($geriYol, 'hata', 'Lütfen işaretli alanları düzeltin.');
    }

    protected function sayfaBoyutu(): int
    {
        return 20;
    }
}
