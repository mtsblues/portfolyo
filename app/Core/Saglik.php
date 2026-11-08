<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Canlı ortam kontrolü: yaygın yapılandırma/güvenlik hatalarını yönetim panelinde gösterir.
 * degerlendir() saf bir fonksiyondur (olgular parametre), bu yüzden veritabanı/sunucu olmadan test edilir.
 */
final class Saglik
{
    public const KRITIK = 'kritik';
    public const UYARI = 'uyari';
    public const BILGI = 'bilgi';
    public const OK = 'ok';

    /** @return list<array{seviye:string,baslik:string,not:string}> kritik → uyarı → bilgi → tamam sırasında */
    public static function kontrolEt(?int $kullaniciId = null): array
    {
        return self::degerlendir(self::olgular($kullaniciId));
    }

    /** @return array<string,mixed> */
    public static function olgular(?int $kullaniciId): array
    {
        $url = (string) config('uygulama.url', '');
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $yedekler = glob(KOK . '/storage/yedekler/portfolyo-*.sql*') ?: [];
        $sonYedek = 0;
        foreach ($yedekler as $y) {
            $sonYedek = max($sonYedek, (int) filemtime($y));
        }
        $totp = false;
        if ($kullaniciId !== null) {
            try {
                $totp = \App\Models\Kullanici::ikiAdimAktifMi($kullaniciId);
            } catch (\Throwable) {
                $totp = false;
            }
        }
        $yaz = true;
        foreach (['yuklemeler', 'oturum', 'logs'] as $d) {
            $yol = KOK . '/storage/' . $d;
            $yaz = $yaz && is_dir($yol) && is_writable($yol);
        }
        return [
            'ortam'          => (string) config('uygulama.ortam', 'production'),
            'debug'          => (bool) config('uygulama.debug', false),
            'url'            => $url,
            'istek_host'     => $host,
            'https'          => Guvenlik::https(),
            'proxy_basligi'  => isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']),
            'guvenilir_proxy' => (bool) config('guvenlik.guvenilir_proxy', false),
            'oturum_secure'  => (bool) config('oturum.secure', true),
            'hsts'           => (bool) config('guvenlik.hsts', false),
            'install_var'    => is_file(KOK . '/database/install.php'),
            'seed_var'       => is_file(KOK . '/database/seed.php'),
            'kurulum_token'  => trim((string) config('kurulum.token', '')) !== '',
            'totp_aktif'     => $totp,
            'depolama_yazilabilir' => $yaz,
            'php_yukleme_bayt' => min(self::bayt((string) ini_get('upload_max_filesize')), self::bayt((string) ini_get('post_max_size'))),
            'video_siniri_bayt' => (int) config('yukleme.video_maks_bayt', 52428800),
            'gd'             => extension_loaded('gd'),
            'zip'            => extension_loaded('zip'),
            'smtp'           => trim((string) config('posta.host', '')) !== '',
            'son_yedek'      => $sonYedek,
            'oto_yedek'      => (int) \App\Models\Ayar::al('otomatik_yedek_gun', '0') > 0,
            'dis_hedef'      => Yedek::hedefler() !== [],
            'kvkk'           => trim(\App\Models\Ayar::al('kvkk_sayfa')) !== '',
            'simdi'          => time(),
            'php'            => PHP_VERSION,
        ];
    }

    /**
     * @param array<string,mixed> $o olgular
     * @return list<array{seviye:string,baslik:string,not:string}>
     */
    public static function degerlendir(array $o): array
    {
        $liste = [];
        $ekle = static function (string $seviye, string $baslik, string $not = '') use (&$liste): void {
            $liste[] = ['seviye' => $seviye, 'baslik' => $baslik, 'not' => $not];
        };

        $urlHost = strtolower((string) (parse_url((string) $o['url'], PHP_URL_HOST) ?? ''));
        $yerel = static fn(string $h): bool => $h === '' || $h === 'localhost' || str_starts_with($h, '127.') || $h === '[::1]'
            || str_ends_with($h, '.local') || str_ends_with($h, '.test') || str_ends_with($h, '.localhost');
        $istekHost = preg_replace('/:\d+$/', '', (string) $o['istek_host']) ?? '';
        $canli = !$yerel($istekHost) || (!$yerel($urlHost) && $urlHost !== '');
        $https = (bool) $o['https'] || (bool) ($o['guvenilir_proxy'] && $o['proxy_basligi']);

        // Hata gösterimi
        if ($o['debug']) {
            $canli
                ? $ekle(self::KRITIK, 'Hata ayıklama modu AÇIK (APP_DEBUG=true)', 'Canlı sitede hata ayrıntıları ziyaretçilere görünür. .env içinde APP_DEBUG=false ve APP_ENV=production yapın.')
                : $ekle(self::BILGI, 'Geliştirme modu (APP_DEBUG=true)', 'Canlıya çıkarken APP_DEBUG=false yapmayı unutmayın.');
        } else {
            $ekle(self::OK, 'Hata ayrıntıları gizli (APP_DEBUG=false)');
        }

        // HTTPS
        if ($canli && !$https) {
            $ekle(self::KRITIK, 'Site HTTPS ile çalışmıyor', 'Parolalar ve oturum çerezi şifresiz iletilir. Alan adınıza SSL sertifikası kurun (docs/YAYINLAMA.md) ve HTTP→HTTPS yönlendirmesini açın.');
        } elseif ($https) {
            $ekle(self::OK, 'HTTPS etkin');
            $o['oturum_secure'] ? $ekle(self::OK, 'Oturum çerezi yalnızca HTTPS ile gider (SESSION_SECURE=true)')
                : $ekle(self::UYARI, 'SESSION_SECURE kapalı', 'HTTPS kullanıyorsunuz; .env içinde SESSION_SECURE=true yapın.');
            $o['hsts'] ? $ekle(self::OK, 'HSTS etkin') : $ekle(self::BILGI, 'HSTS kapalı', 'HTTPS sorunsuz çalıştığından emin olduktan sonra .env içinde HSTS=true yapın.');
        }
        if (str_starts_with(strtolower((string) $o['url']), 'https://') && !$https) {
            $ekle(self::UYARI, 'APP_URL https ama bu istek HTTP geliyor', 'Proxy/Cloudflare arkasındaysanız GUVENILIR_PROXY=true yapın; değilse HTTPS yönlendirmesini açın.');
        }

        // Adres uyumu
        if ($canli && trim((string) $o['url']) === '') {
            $ekle(self::UYARI, 'APP_URL boş', 'RSS, site haritası ve paylaşım görseli adresleri için .env içine https://alanadiniz.com yazın.');
        } elseif ($canli && $urlHost !== '' && $istekHost !== '' && $urlHost !== $istekHost && $urlHost !== 'www.' . $istekHost && 'www.' . $urlHost !== $istekHost) {
            $ekle(self::UYARI, 'APP_URL gerçek alan adıyla uyuşmuyor', 'APP_URL: ' . $urlHost . ' · şu an: ' . $istekHost . '. RSS, canonical ve paylaşım adresleri yanlış üretilir.');
        }

        // Proxy
        if ($o['proxy_basligi'] && !$o['guvenilir_proxy'] && $canli) {
            $ekle(self::UYARI, 'Proxy/CDN (örn. Cloudflare) arkasında görünüyorsunuz', 'GUVENILIR_PROXY=true yapmazsanız tüm ziyaretçiler tek IP gibi görünür: giriş/iletişim hız sınırı herkesi birlikte kilitler ve oturumlar düşer.');
        } elseif ($o['guvenilir_proxy'] && !$o['proxy_basligi']) {
            $ekle(self::UYARI, 'GUVENILIR_PROXY açık ama proxy başlığı gelmiyor', 'Proxy kullanmıyorsanız kapatın; aksi halde ziyaretçiler IP adreslerini taklit edebilir.');
        }

        // Kurulum artıkları
        if ($o['kurulum_token']) {
            $ekle(self::UYARI, 'KURULUM_TOKEN hâlâ .env içinde', 'Kurulum bittiyse bu satırı silin.');
        }
        if ($canli && ($o['install_var'] || $o['seed_var'])) {
            $ekle(self::UYARI, 'Kurulum betikleri sunucuda duruyor', 'database/install.php ve database/seed.php dosyalarını silin (güncelleme yaparken geçici olarak geri koyabilirsiniz).');
        }

        // Hesap güvenliği
        $o['totp_aktif'] ? $ekle(self::OK, 'İki adımlı doğrulama (2FA) açık')
            : $ekle($canli ? self::UYARI : self::BILGI, 'İki adımlı doğrulama (2FA) kapalı', 'Kullanıcı adınıza tıklayıp Profil sayfasından açın.');

        // Dosya sistemi / PHP
        $o['depolama_yazilabilir'] ? $ekle(self::OK, 'storage klasörleri yazılabilir')
            : $ekle(self::KRITIK, 'storage klasörleri yazılamıyor', 'storage/yuklemeler, storage/oturum ve storage/logs klasörleri web sunucusu tarafından yazılabilir olmalı (izin 775).');
        if ((int) $o['php_yukleme_bayt'] < (int) $o['video_siniri_bayt']) {
            $ekle(self::UYARI, 'PHP yükleme sınırı video sınırından küçük', 'PHP: ' . (int) ((int) $o['php_yukleme_bayt'] / 1048576) . ' MB, video sınırı: ' . (int) ((int) $o['video_siniri_bayt'] / 1048576) . ' MB. upload_max_filesize/post_max_size değerlerini yükseltin ya da YUKLEME_VIDEO_MAX_MB değerini düşürün.');
        }
        $o['gd'] ?: $ekle(self::BILGI, 'PHP gd eklentisi yok', 'Çok büyük görseller küçültülmez (konum bilgisi yine de silinir).');
        (extension_loaded('phar') && extension_loaded('zlib')) ?: $ekle(self::BILGI, 'PHP phar/zlib eklentisi yok', 'Medya (görsel/video) yedeği alınamaz; veritabanı yedeği etkilenmez. storage/yuklemeler klasörünü elle kopyalayın.');
        $o['smtp'] ? $ekle(self::OK, 'E-posta bildirimi (SMTP) ayarlı') : $ekle(self::BILGI, 'E-posta bildirimi ayarlı değil', 'Mesajlar yalnızca panelde görünür. İsterseniz .env içindeki MAIL_* alanlarını doldurun.');

        // Yedek
        $yas = (int) $o['simdi'] - (int) $o['son_yedek'];
        if ((int) $o['son_yedek'] === 0) {
            $ekle($canli ? self::UYARI : self::BILGI, 'Henüz yedek alınmamış', 'php bin/yedekle.php komutunu çalıştırın ve zamanlayın (docs/KURULUM.md → Yedekleme).');
        } elseif ($yas > 7 * 86400) {
            $ekle(self::UYARI, 'Son yedek ' . (int) ($yas / 86400) . ' gün önce alınmış', 'Yedeklemeyi zamanlayın (cron / Görev Zamanlayıcı).');
        } else {
            $ekle(self::OK, 'Yedek güncel');
        }

        if ($canli && isset($o['oto_yedek']) && !$o['oto_yedek']) {
            $ekle(self::BILGI, 'Otomatik yedek kapalı', 'Ayarlar → Otomatik yedekleme bölümünden günlük/haftalık yedeği açın.');
        }
        if ($canli && isset($o['dis_hedef']) && !$o['dis_hedef']) {
            $ekle(self::UYARI, 'Yedekler yalnızca bu sunucuda duruyor', 'Sunucu bozulursa yedekler de gider. E-posta, FTP veya WebDAV hedefi tanımlayın (.env; docs/KULLANIM.md → Yedekleme).');
        }
        if ($canli && isset($o['kvkk']) && !$o['kvkk']) {
            $ekle(self::BILGI, 'KVKK aydınlatma metni bağlı değil', 'İletişim formu kişisel veri topluyor. Sayfalar → Hazır şablonlar’dan oluşturup yayınlayın, sonra Ayarlar → Gizlilik’te adresini yazın.');
        }

        $sira = [self::KRITIK => 0, self::UYARI => 1, self::BILGI => 2, self::OK => 3];
        usort($liste, static fn(array $a, array $b): int => $sira[$a['seviye']] <=> $sira[$b['seviye']]);
        return $liste;
    }

    /** "64M", "2G", "512K" → bayt. */
    public static function bayt(string $deger): int
    {
        $deger = trim($deger);
        if ($deger === '' || $deger === '-1') {
            return PHP_INT_MAX;
        }
        $n = (int) $deger;
        return match (strtoupper(substr($deger, -1))) {
            'G' => $n * 1073741824,
            'M' => $n * 1048576,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
