<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Denetleyici;
use App\Core\Dogrulayici;
use App\Core\HizSiniri;
use App\Core\Posta;
use App\Models\Ayar;
use App\Models\Mesaj;

final class IletisimController extends Denetleyici
{
    public function index(): void
    {
        $this->gorunum('iletisim/index', [
            'sayfaBaslik'   => 'İletişim · ' . ayar('site_baslik', 'Portfolyo'),
            'sayfaAciklama' => 'Bana güvenli bir şekilde mesaj gönderin.',
        ]);
    }

    /** AJAX JSON uç noktası. CSRF doğrulaması Yonlendirici ara katmanında yapılır. */
    public function gonder(): void
    {
        $ip = istemci_ip();

        // Hız sınırı: IP başına 10 dakikada 5 istek (geçerli/geçersiz fark etmez)
        if (!HizSiniri::izinVer('iletisim:' . $ip, 5, 600)) {
            header('Retry-After: 600');
            $this->json(['basarili' => false, 'hata' => 'Çok fazla istek gönderdiniz. Lütfen biraz sonra tekrar deneyin.'], 429);
        }

        // Honeypot: botlar doldurur, gerçek kullanıcı görmez. Sessizce başarılı gibi davran.
        if (trim($this->girdi('web_sitesi')) !== '') {
            $this->json(['basarili' => true, 'mesaj' => 'Mesajınız alındı. Teşekkürler!']);
        }

        $d = Dogrulayici::yap($_POST, [
            'ad_soyad' => 'gerekli|min:2|max:120',
            'eposta'   => 'gerekli|eposta|max:180',
            'konu'     => 'gerekli|min:3|max:180',
            'mesaj'    => 'gerekli|min:10|max:5000|cok_satir',
        ]);
        if ($d->basarisiz()) {
            $this->json(['basarili' => false, 'hata' => 'Lütfen form alanlarını kontrol edin.', 'alanlar' => $d->hatalar()], 422);
        }

        $limit = max(1, (int) Ayar::al('iletisim_mesaj_ucret_siniri', '20'));
        $ikili = ip_ikili($ip);
        if (Mesaj::sonSaatteIpSayisi($ikili) >= $limit) {
            $this->json(['basarili' => false, 'hata' => 'Saatlik mesaj sınırına ulaştınız. Lütfen daha sonra tekrar deneyin.'], 429);
        }

        $ad = (string) $d->deger('ad_soyad');
        $eposta = mb_strtolower((string) $d->deger('eposta'), 'UTF-8');
        $konu = (string) $d->deger('konu');
        $mesaj = (string) $d->deger('mesaj');
        Mesaj::olustur($ad, $eposta, $konu, $mesaj, $ikili);

        // Mesaj kaydedildi. E-posta bildirimi yanıt gönderildikten SONRA yapılır; SMTP yavaş/hatalı olsa bile
        // ziyaretçi beklemez ve mesaj kaybolmaz (yine admin panelinde görünür).
        $bildir = Ayar::al('iletisim_bildirim', '1') === '1' && Posta::yapilandirildi();
        $this->jsonSonra(
            ['basarili' => true, 'mesaj' => 'Mesajınız başarıyla iletildi. En kısa sürede dönüş yapacağım.'],
            static function () use ($bildir, $ad, $eposta, $konu, $mesaj): void {
                if (!$bildir) {
                    return;
                }
                $alici = trim((string) config('posta.alici', '')) !== '' ? (string) config('posta.alici') : Ayar::al('site_eposta');
                $govde = "Sitenizden yeni bir mesaj geldi.\n\n"
                    . "Ad Soyad : {$ad}\nE-posta   : {$eposta}\nKonu      : {$konu}\n\n{$mesaj}\n\n"
                    . "---\nTüm mesajlar: " . mutlak_url('/admin/mesajlar') . "\n";
                $s = Posta::gonder($alici, '[' . Ayar::al('site_baslik', 'Portfolyo') . '] Yeni mesaj: ' . $konu, $govde, $eposta, $ad);
                if (!$s['ok']) {
                    gunluk('Mesaj bildirimi gönderilemedi: ' . (string) $s['hata']);
                }
            }
        );
    }

    /**
     * JSON yanıtını Content-Length ile gönderip bağlantıyı kapatır, ardından $sonra işini yürütür.
     * @param array<string,mixed> $veri
     */
    private function jsonSonra(array $veri, callable $sonra): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ignore_user_abort(true);
        $govde = (string) json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($govde));
        header('Connection: close');
        echo $govde;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }
        try {
            $sonra();
        } catch (\Throwable $t) {
            gunluk('Bildirim işlemi hatası: ' . $t->getMessage());
        }
        exit;
    }
}
