# Yol haritası

Bu belge projenin nereye gittiğini gösterir. Her sürüm kendi içinde test edilir ve CHANGELOG'a işlenir.
Hedef: **hem kişisel tanıtım/CV/portfolyo, hem de günlük hayatta işe yarayan araçlar için tek, güvenli, kendi sunucunuzda çalışan site.**
Katkı ve öncelik önerileri için bir issue açın.

## Tasarım ilkeleri
1. **Güvenlik varsayılan:** her yeni özellik girdi doğrulama, CSRF, yetki denetimi, denetim günlüğü ve test ile gelir.
2. **Bağımlılıksız:** Composer/NPM/CDN yok; PHP 8.2 + MySQL yeterli. Her özellik paylaşımlı hostingde (SSH'siz) çalışır.
3. **Herkes için özelleştirilebilir:** içerik, tema ve bölüm düzeni panelden değişir; kod değiştirmek gerekmez.
4. **Veri sizin:** her şeyi yedekleyip tek tıkla geri yükleyebilir, dışa aktarabilirsiniz.

## Sürümler

### v1.3 — Güvence (güvenlik, yedek, uyum) ✅ yayınlandı
- E-posta ile **parola sıfırlama** (tek kullanımlık, süreli bağlantı; kullanıcı numaralandırmaya karşı korumalı)
- **Yedekler sayfası:** tek tıkla yedek, indirme, **geri yükleme sihirbazı** (doğrulama + geri yükleme öncesi otomatik yedek), otomatik zamanlama, **e-posta / FTP(S) / WebDAV ile dış yedek**
- **Güvenlik Merkezi:** denetim günlüğü, giriş geçmişi, aktif oturumlar ve "diğer oturumları kapat", zorunlu 2FA, yeni cihaz bildirimi
- **Sayfalar** (gizlilik, aydınlatma metni, "Bu site hakkında"… serbest sayfalar) + **KVKK şablonları**, iletişim formunda açık onay, mesaj saklama süresi, IP kısaltma
- **Bakım görevleri:** günlük döndürme, eski kayıt temizliği (cron'suz hostingler için web-cron adresi dahil)

### v1.4 — Kişisel tanıtım ve CV
- Deneyim ve eğitim zaman çizgisi, yetenekler (seviye) ve sertifikalar, hizmetler ve referanslar
- **Yazdırılabilir / PDF CV** sayfası (aynı verilerden otomatik), vCard (.vcf) indirme
- "Yeni işlere açığım" durum rozeti, öne çıkan vaka çalışmaları (problem → çözüm → sonuç), projelere teknoloji etiketleri ve filtre
- Makale: içindekiler, önceki/sonraki, paylaşım bağlantıları (3. taraf script olmadan)

### v1.5 — Günlük hayatta işe yarayanlar
- **Link sayfası** (link-in-bio) + **kısa linkler** (tıklama sayacı, kampanya etiketi) + **QR kod**
- **İndirilebilir dosyalar** (CV, şablonlar, kılavuzlar; indirme sayacı, gizli/listelenmeyen bağlantı)
- **Randevu / teklif talep formu** (konu, bütçe, tarih; durum takibi; takvime ekle .ics)
- **Kişisel notlar ve yapılacaklar** (yalnızca yönetici; arama, etiket, öncelik, son tarih)
- Çerezsiz **ziyaretçi istatistikleri**, 404 kayıtları, yönlendirme (301) yöneticisi, duyuru şeridi, bakım modu, "Şimdi" sayfası

### v1.6 — Tasarım ve çarpıcı manşet
- **Hazır temalar** (açık/koyu çiftli), vurgu rengi, yazı tipi (sistem + gömülü), köşe/yoğunluk/genişlik ayarları, **özel CSS**, favicon ve logo yükleme
- **Ana sayfa bölüm düzeni:** bölümleri aç/kapat/sırala (karşılama, projeler, yazılar, hizmetler, referanslar, bağlantılar…)
- **Çarpıcı karşılama (hero):** dönen kelimeli başlık ("Ben bir [geliştirici | yazar | tasarımcı]"), degrade vurgu, sayaçlar, birincil/ikincil eylem düğmeleri; hareket azaltma tercihine saygılı
- Görsel küçük sürümleri ve WebP (GD varsa)

### v1.7 — Gelişmiş yönetim paneli
- Genel arama (Ctrl+K), listelerde arama/filtre/sayfalama/toplu işlem, çoğaltma, hızlı yayınla/geri çek
- Taslak otomatik kaydı, makale sürüm geçmişi (geri alma), taslak önizleme bağlantısı
- Gösterge paneli bileşenleri (istatistik, yapılacaklar, son etkinlik, bakım durumu)
- İçerik dışa/içe aktarma (Markdown + JSON), çok dillilik altyapısı (TR/EN)

## Bilinçli olarak kapsam dışı
Yorum sistemi (spam yükü), genel üyelik, e-ticaret/ödeme, 3. taraf izleme betikleri.
