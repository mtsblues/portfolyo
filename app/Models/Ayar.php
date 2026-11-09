<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Veritabani;

final class Ayar
{
    /**
     * Admin panelinde düzenlenebilen ayarlar (beyaz liste), gruplara ayrılmıştır.
     * Alan: [etiket, tip, en çok uzunluk, zorunlu mu, ipucu]
     * Tipler: text | email | number | url | yol (yalnızca site içi /yuklemeler/... — sayfada gösterilir, CSP harici görseli engeller) |
     *        yol_dis (site içi yol ya da https adresi — yalnızca meta etiketine yazılır) | metin (çok satır, Markdown) | onay
     *
     * 'secim' tipi: 6. öğe [değer => etiket]. 'number' tipi: 6. öğe [en az, en çok] (isteğe bağlı).
     *
     * @var array<string,array{baslik:string,aciklama:string,alanlar:array<string,array<int,mixed>>}>
     */
    public const GRUPLAR = [
        'genel' => [
            'baslik'   => 'Genel',
            'aciklama' => 'Site adı, açıklaması ve görselleri.',
            'alanlar'  => [
                'site_baslik'       => ['Site başlığı', 'text', 120, true, 'Menüde, sekme başlığında ve altbilgide görünür.'],
                'site_aciklama'     => ['Site açıklaması (SEO)', 'text', 300, false, 'Arama motorları ve sosyal paylaşımlarda görünür.'],
                'anahtar_kelimeler' => ['Anahtar kelimeler', 'text', 300, false, 'Virgülle ayırın.'],
                'site_logo'         => ['Logo', 'yol', 300, false, 'Boşsa renkli işaret kullanılır. Önce Medya sayfasına yükleyin, sonra oradaki /yuklemeler/... yolunu yazın.'],
                'og_gorsel'         => ['Paylaşım görseli (og:image)', 'yol_dis', 300, false, 'Sosyal ağlarda paylaşılan bağlantılarda görünür (önerilen: 1200×630). /yuklemeler/... yolu ya da https:// adresi.'],
            ],
        ],
        'hero' => [
            'baslik'   => 'Anasayfa karşılama alanı',
            'aciklama' => 'Anasayfanın üstündeki büyük başlık: "[baş] [vurgulu] [son]" biçiminde birleşir.',
            'alanlar'  => [
                'hero_rozet'        => ['Üst etiket', 'text', 80, false, 'Başlığın üstündeki küçük rozet.'],
                'hero_baslik_bas'   => ['Başlık — başlangıç', 'text', 100, false, 'Örn: Merhaba, ben'],
                'hero_baslik_vurgu' => ['Başlık — vurgulu kısım', 'text', 100, false, 'Renkli gösterilen kısım. Örn: Ayşe Yılmaz'],
                'hero_baslik_son'   => ['Başlık — son', 'text', 100, false, 'İsteğe bağlı.'],
                'hero_aciklama'     => ['Kısa tanıtım', 'text', 300, false, 'Başlığın altındaki cümle(ler).'],
            ],
        ],
        'hakkimda' => [
            'baslik'   => 'Hakkımda sayfası',
            'aciklama' => 'İçerik girilirse menüde "Hakkımda" bağlantısı görünür. Markdown, görsel ve video desteklenir.',
            'alanlar'  => [
                'hakkimda_foto'   => ['Profil fotoğrafı', 'yol', 300, false, 'Önce Medya sayfasına yükleyin, sonra oradaki /yuklemeler/... yolunu yazın.'],
                'hakkimda_icerik' => ['Hakkımda metni (Markdown)', 'metin', 20000, false, 'Boş bırakırsanız sayfa ve menü bağlantısı gizlenir.'],
            ],
        ],
        'sosyal' => [
            'baslik'   => 'Sosyal bağlantılar',
            'aciklama' => 'Altbilgide ve Hakkımda sayfasında görünür. Boş olanlar gösterilmez (https:// ile başlamalı).',
            'alanlar'  => [
                'sosyal_github'    => ['GitHub', 'url', 300, false, ''],
                'sosyal_linkedin'  => ['LinkedIn', 'url', 300, false, ''],
                'sosyal_x'         => ['X (Twitter)', 'url', 300, false, ''],
                'sosyal_youtube'   => ['YouTube', 'url', 300, false, ''],
                'sosyal_instagram' => ['Instagram', 'url', 300, false, ''],
            ],
        ],
        'iletisim' => [
            'baslik'   => 'İletişim ve altbilgi',
            'aciklama' => 'E-posta gönderimi için sunucu ayarları .env dosyasındadır (MAIL_*). Bkz. docs/KURULUM.md.',
            'alanlar'  => [
                'site_eposta'                 => ['İletişim e-postası', 'email', 180, true, 'Mesaj bildirimleri varsayılan olarak bu adrese gider.'],
                'iletisim_bildirim'           => ['Yeni mesajda e-posta ile bildir', 'onay', 1, false, 'MAIL_HOST .env içinde ayarlıysa çalışır.'],
                'iletisim_mesaj_ucret_siniri' => ['Aynı IP için saatlik mesaj limiti', 'number', 4, true, '1 ile 1000 arası.', [1, 1000]],
                'altbilgi_metin'              => ['Altbilgi metni', 'text', 200, false, 'Boşsa "© yıl site başlığı" gösterilir.'],
            ],
        ],
        'cv' => [
            'baslik'   => 'Özgeçmiş ve müsaitlik',
            'aciklama' => 'Özgeçmiş öğelerini (deneyim, eğitim, yetenek…) Yönetim → Özgeçmiş bölümünden girersiniz. Burada sayfanın genel bilgileri vardır. Özgeçmiş sayfası herkese açıktır: yalnızca paylaşmak istediğiniz bilgileri girin.',
            'alanlar'  => [
                'cv_yayin'     => ['Özgeçmiş sayfasını yayınla (/cv)', 'onay', 1, false, 'Kapalıyken /cv ve vCard 404 verir, menüde görünmez.'],
                'cv_ad'        => ['Ad soyad', 'text', 120, false, 'Özgeçmişin ve kartvizitin başlığı. Boşsa site başlığı kullanılır.'],
                'cv_unvan'     => ['Unvan', 'text', 120, false, 'Örn: Yazılım Geliştirici'],
                'cv_ozet'      => ['Kısa özet (Markdown)', 'metin', 3000, false, 'Özgeçmişin en üstündeki tanıtım paragrafı.'],
                'cv_eposta'    => ['Herkese açık e-posta', 'email', 180, false, 'Doluysa özgeçmişte ve kartvizitte görünür. Boşsa e-posta gösterilmez (iletişim formu kullanılır).'],
                'cv_konum'     => ['Konum', 'text', 120, false, 'Örn: İstanbul, Türkiye'],
                'cv_telefon'   => ['Telefon', 'text', 40, false, 'Doluysa özgeçmişte ve kartvizitte HERKESE AÇIK görünür. İstemiyorsanız boş bırakın.'],
                'cv_vcard'     => ['Kartvizit (.vcf) indirmeyi aç', 'onay', 1, false, 'Ziyaretçi adınızı, e-postanızı, (varsa) telefonunuzu ve sitenizi rehberine ekleyebilir.'],
                'musait'       => ['“Yeni işlere açığım” rozetini göster', 'onay', 1, false, 'Anasayfada ve özgeçmişte yeşil rozet olarak görünür.'],
                'musait_metin' => ['Rozet metni', 'text', 60, false, 'Örn: Yeni projelere açığım'],
            ],
        ],
        'gizlilik' => [
            'baslik'   => 'Gizlilik ve KVKK',
            'aciklama' => 'İletişim formundan kişisel veri topladığınız için aydınlatma metni önerilir. Hazır şablonlar Sayfalar bölümündedir (hukuki danışmanlık değildir).',
            'alanlar'  => [
                'kvkk_sayfa'        => ['Aydınlatma metni sayfası (adres)', 'text', 100, false, 'Sayfalar bölümünde YAYINLADIĞINIZ sayfanın adresi (örn. aydinlatma-metni). Doluysa iletişim formunda onay kutusu zorunlu olur.'],
                'mesaj_saklama_gun' => ['Mesajları saklama süresi (gün)', 'number', 4, true, '0 = silme. Bu süreden eski mesajlar bakım görevinde otomatik silinir (en çok 3650).', [0, 3650]],
                'ip_saklama'        => ['IP adresi saklama', 'secim', 10, true, 'Mesaj ve günlüklerde IP’nin nasıl saklanacağı. Kısaltılmış: son bölüm silinir.', ['tam' => 'Tam adres', 'kisa' => 'Kısaltılmış (önerilen)', 'yok' => 'Hiç saklama']],
            ],
        ],
        'guvenlik' => [
            'baslik'   => 'Güvenlik',
            'aciklama' => 'Hesap güvenliği politikaları. Oturumlar ve denetim günlüğü için Güvenlik sayfasına bakın.',
            'alanlar'  => [
                'zorunlu_2fa'          => ['Tüm yöneticiler için 2FA zorunlu', 'onay', 1, false, 'Açıkken 2FA’sı olmayan kullanıcı, kurulum yapana kadar yalnızca profil sayfasını görür. Önce kendi 2FA’nızı etkinleştirin!'],
                'yeni_cihaz_bildirimi' => ['Yeni cihazdan girişte e-posta gönder', 'onay', 1, false, 'SMTP ayarlıysa çalışır (.env MAIL_*).'],
            ],
        ],
        'yedekleme' => [
            'baslik'   => 'Otomatik yedekleme',
            'aciklama' => 'Yedekler storage/yedekler klasörüne yazılır; bakım görevi (cron ya da panel açılışı) zamanı gelince alır. Dış hedef bilgileri .env dosyasındadır; burada yalnızca açıp kapatırsınız.',
            'alanlar'  => [
                'otomatik_yedek_gun' => ['Otomatik yedek aralığı (gün)', 'number', 3, true, '0 = kapalı. Önerilen: 1 (günlük) veya 7 (haftalık). En çok 90.', [0, 90]],
                'yedek_medya_dahil'  => ['Yüklenen medyayı da yedekle', 'onay', 1, false, 'Ayrı bir medya-*.tar.gz arşivi oluşturur.'],
                'yedek_eposta'       => ['Yedeği e-postayla gönder', 'onay', 1, false, 'SMTP gerekir; dosya 15 MB’tan büyükse gönderilmez. Yedek parola özetleri içerir — yalnızca güvendiğiniz bir adrese.'],
                'yedek_ftp'          => ['Yedeği FTP/FTPS sunucusuna gönder', 'onay', 1, false, '.env: YEDEK_FTP_HOST, YEDEK_FTP_KULLANICI, YEDEK_FTP_SIFRE …'],
                'yedek_webdav'       => ['Yedeği WebDAV’a gönder (Nextcloud vb.)', 'onay', 1, false, '.env: YEDEK_WEBDAV_URL, YEDEK_WEBDAV_KULLANICI, YEDEK_WEBDAV_SIFRE'],
            ],
        ],
    ];

    /** @return array<string,array{0:string,1:string,2:int,3:bool,4:string}> tüm alanlar, düz liste */
    public static function alanlar(): array
    {
        $hepsi = [];
        foreach (self::GRUPLAR as $g) {
            $hepsi += $g['alanlar'];
        }
        return $hepsi;
    }

    /** @var array<string,string>|null */
    private static ?array $onbellek = null;

    /** @return array<string,string> */
    public static function hepsi(): array
    {
        if (self::$onbellek === null) {
            self::$onbellek = [];
            try {
                foreach (Veritabani::hepsi('SELECT anahtar, deger FROM ayarlar') as $s) {
                    self::$onbellek[(string) $s['anahtar']] = (string) ($s['deger'] ?? '');
                }
            } catch (\Throwable $t) {
                gunluk('Ayarlar okunamadı: ' . $t->getMessage());
            }
        }
        return self::$onbellek;
    }

    public static function al(string $anahtar, string $varsayilan = ''): string
    {
        return self::hepsi()[$anahtar] ?? $varsayilan;
    }

    public static function kaydet(string $anahtar, string $deger): void
    {
        Veritabani::calistir(
            'INSERT INTO ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)',
            [$anahtar, $deger]
        );
        self::$onbellek = null;
    }
}
