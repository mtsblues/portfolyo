<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Dogrulayici;
use App\Core\HizSiniri;
use App\Core\Kurulum;
use App\Models\Kullanici;
use Throwable;

/** Tarayıcıdan kurulum sihirbazı (SSH olmayan hostingler). Varsayılan KAPALI: bkz. App\Core\Kurulum. */
final class KurulumController extends Denetleyici
{
    public function index(): void
    {
        $this->kapi();
        $this->sihirbaz();
    }

    public function kur(): void
    {
        $this->kapi();
        $ip = istemci_ip();
        if (!HizSiniri::izinVer('kurulum:' . $ip, 8, 600)) {
            header('Retry-After: 600');
            $this->sihirbaz(['hata' => 'Çok fazla deneme. Lütfen 10 dakika sonra tekrar deneyin.'], 429);
            return;
        }

        $token = Kurulum::token();
        if (!hash_equals($token, $this->girdi('kurulum_anahtari'))) {
            $this->sihirbaz(['hata' => 'Kurulum anahtarı hatalı. .env dosyanızdaki KURULUM_TOKEN değerini girin.']);
            return;
        }

        $d = Dogrulayici::yap($_POST, [
            'ad_soyad' => 'gerekli|min:2|max:120',
            'eposta'   => 'gerekli|eposta|max:180',
            'parola'   => 'gerekli|parola_alani',
        ]);
        $hatalar = $d->hatalar();
        $parola = $this->girdi('parola');
        $p = Dogrulayici::parolaHatalari($parola);
        if ($p !== []) {
            $hatalar['parola'] = implode(' ', $p);
        } elseif (!hash_equals($parola, $this->girdi('parola_tekrar'))) {
            $hatalar['parola_tekrar'] = 'Parolalar eşleşmiyor.';
        }
        if ($hatalar !== []) {
            $this->sihirbaz(['hatalar' => $hatalar, 'eski' => ['ad_soyad' => (string) $d->deger('ad_soyad'), 'eposta' => (string) $d->deger('eposta')]]);
            return;
        }

        try {
            $sonuc = Kurulum::semaUygula();
            // Bağlantı yeni oluşturulduysa Veritabani sarmalayıcısı da şimdi bağlanır.
            if (Kurulum::kullaniciSayisi() !== 0) {
                $this->hata(404);
                return;
            }
            Kullanici::olustur(
                (string) $d->deger('ad_soyad'),
                mb_strtolower((string) $d->deger('eposta'), 'UTF-8'),
                $parola,
                'admin'
            );
            $anahtarTamam = Kurulum::anahtarHazirla();
        } catch (Throwable $t) {
            gunluk('Web kurulumu başarısız: ' . $t->getMessage());
            $this->sihirbaz(['hata' => 'Kurulum tamamlanamadı. Veritabanı bilgilerinizi ve klasör izinlerini kontrol edin (ayrıntı: storage/logs/uygulama.log).']);
            return;
        }
        gunluk('Web kurulumu tamamlandı (' . $sonuc['ifade'] . ' ifade).');
        $this->gorunum('kurulum/bitti', ['sayfaBaslik' => 'Kurulum tamamlandı', 'anahtarTamam' => $anahtarTamam, 'gecis' => $sonuc['gecis']], 'bos');
    }

    /** Sihirbaz kapalıysa (token yok/kısa ya da kullanıcı zaten var) sayfa hiç yokmuş gibi 404 döner. */
    private function kapi(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        if (!Kurulum::etkinMi(Kurulum::token(), Kurulum::kullaniciSayisi())) {
            $this->hata(404);
            exit;
        }
    }

    /** @param array<string,mixed> $ek */
    private function sihirbaz(array $ek = [], int $kod = 200): void
    {
        $this->gorunum('kurulum/index', $ek + [
            'sayfaBaslik' => 'Kurulum',
            'kontrol'     => Kurulum::onKontrol(),
        ], 'bos', $kod);
    }
}
