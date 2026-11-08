<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hazır sayfa şablonları (Markdown). Alanlar site ayarlarından doldurulur.
 * ÖNEMLİ: Bunlar genel bir başlangıç metnidir, hukuki danışmanlık DEĞİLDİR. Yayımlamadan önce kendi durumunuza göre gözden geçirin.
 */
final class SayfaSablonlari
{
    /** @return array<string,array{baslik:string,slug:string,aciklama:string}> */
    public static function liste(): array
    {
        return [
            'aydinlatma' => ['baslik' => 'KVKK Aydınlatma Metni', 'slug' => 'aydinlatma-metni', 'aciklama' => 'İletişim/talep formundan toplanan veriler için (KVKK m.10).'],
            'gizlilik'   => ['baslik' => 'Gizlilik ve Çerez Bildirimi', 'slug' => 'gizlilik', 'aciklama' => 'Hangi verilerin ve çerezlerin kullanıldığını açıklar.'],
            'hakkinda'   => ['baslik' => 'Bu Site Hakkında', 'slug' => 'bu-site-hakkinda', 'aciklama' => 'Sitenin amacı, kullanılan teknoloji ve iletişim.'],
        ];
    }

    /** @param array<string,string> $a ayarlar */
    public static function icerik(string $anahtar, array $a): string
    {
        $site = trim($a['site_baslik'] ?? '') !== '' ? trim($a['site_baslik']) : 'Bu site';
        $eposta = trim($a['site_eposta'] ?? '') !== '' ? trim($a['site_eposta']) : 'iletisim@ornek.com';
        $gun = (int) ($a['mesaj_saklama_gun'] ?? 0);
        $saklama = $gun > 0
            ? "Talebiniz sonuçlandıktan sonra en geç **{$gun} gün** boyunca saklanır ve sonrasında silinir."
            : 'Talebiniz sonuçlanana kadar ve yasal yükümlülüklerin gerektirdiği süre boyunca saklanır; ardından silinir veya anonim hâle getirilir.';
        $ip = match ($a['ip_saklama'] ?? 'tam') {
            'kisa' => 'IP adresiniz kısaltılarak (son bölümü silinerek) saklanır.',
            'yok'  => 'IP adresiniz saklanmaz.',
            default => 'IP adresiniz güvenlik (spam/kötüye kullanım önleme) amacıyla saklanır.',
        };

        return match ($anahtar) {
            'aydinlatma' => <<<MD
## Aydınlatma Metni

6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") m.10 uyarınca, **{$site}** internet sitesindeki iletişim/talep formu aracılığıyla işlenen kişisel verileriniz hakkında sizi bilgilendirmek isteriz.

### Veri sorumlusu
**{$site}** — iletişim: {$eposta}

### İşlenen veriler
Ad soyad, e-posta adresi, mesaj/talep içeriği, gönderim tarihi ve saati. {$ip}

### İşleme amacı ve hukuki sebep
Verileriniz yalnızca talebinizi değerlendirmek ve size yanıt vermek amacıyla işlenir. Hukuki sebep, bir hakkın tesisi/kullanılması için işlemenin zorunlu olması ile (KVKK m.5/2-e) ve formda verdiğiniz **açık rızadır**.

### Aktarım
Verileriniz üçüncü kişilerle paylaşılmaz. Yalnızca sitenin barındırıldığı hosting sağlayıcısı ve (etkinse) e-posta bildirimlerini ileten e-posta sağlayıcısı teknik olarak verilere erişebilir.

### Saklama süresi
{$saklama}

### Haklarınız (KVKK m.11)
Verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, işlenme amacına uygun kullanılıp kullanılmadığını öğrenme, düzeltilmesini veya silinmesini isteme, itiraz etme ve zarara uğramanız hâlinde tazminat talep etme haklarına sahipsiniz.

### Başvuru
Haklarınızı kullanmak için **{$eposta}** adresine yazabilirsiniz. Başvurunuz en geç 30 gün içinde yanıtlanır.

*Bu metin genel bir başlangıç şablonudur; yayımlamadan önce kendi durumunuza göre gözden geçirmeniz önerilir.*
MD,
            'gizlilik' => <<<MD
## Gizlilik ve Çerez Bildirimi

**{$site}** ziyaretçilerini izlemez: reklam, üçüncü taraf analiz veya sosyal medya takip betiği kullanmaz; harici sunuculardan yazı tipi/betik yüklemez.

### Çerezler ve yerel depolama
- **Oturum çerezi** (yalnızca yönetim paneline giriş yapanlar için): oturumunuzu güvenli tutmak için zorunludur.
- **Tema tercihi** (tarayıcınızın yerel deposunda): koyu/açık görünüm seçiminizi hatırlar; sunucuya gönderilmez.

Bu nedenle çerez onay bildirimi gerektiren izleme çerezleri bulunmamaktadır.

### İletişim formu
Formu doldurursanız verileriniz [Aydınlatma Metni](/sayfa/aydinlatma-metni)'nde açıklandığı gibi işlenir.

### Sunucu kayıtları
Güvenlik ve hata ayıklama amacıyla sunucu günlüklerinde teknik bilgiler (tarih, istenen adres, hata kodu) tutulabilir. {$ip}

### İletişim
Sorularınız için: **{$eposta}**
MD,
            'hakkinda' => <<<MD
## Bu Site Hakkında

**{$site}**, kendi sunucusunda çalışan, açık kaynaklı [Portfolyo](https://github.com/mtsblues/portfolyo) yazılımı ile yayınlanmaktadır. Yazılım PHP ile yazılmıştır, harici bağımlılık kullanmaz ve verilerinizi üçüncü taraflara göndermez.

İletişim için: **{$eposta}**
MD,
            default => '',
        };
    }
}
