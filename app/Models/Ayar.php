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
     * @var array<string,array{baslik:string,aciklama:string,alanlar:array<string,array{0:string,1:string,2:int,3:bool,4:string}>}>
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
                'iletisim_mesaj_ucret_siniri' => ['Aynı IP için saatlik mesaj limiti', 'number', 4, true, '1 ile 1000 arası.'],
                'altbilgi_metin'              => ['Altbilgi metni', 'text', 200, false, 'Boşsa "© yıl site başlığı" gösterilir.'],
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
