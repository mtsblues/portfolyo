# Değişiklik günlüğü

Biçim [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/) ilkelerine, sürümleme [SemVer](https://semver.org/lang/tr/) ilkelerine yakındır.

## [1.1.0] — 2026-11-08

> **Güncelleme:** yedek alın (`php bin/yedekle.php --yuklemeler`), dosyaları güncelleyin, ardından `php database/install.php` çalıştırın.
> Mevcut veriniz korunur; yeni tablo/sütunlar eklenir. `config/.env.example` içindeki yeni anahtarlara (MAIL_*, YUKLEME_VIDEO_MAX_MB, YEDEK_SAKLA) göz atın.

### Eklendi
- **Medya kütüphanesi** (`/admin/medya`): çoklu yükleme, alt metin, kopyalanabilir Markdown kodu, kullanımdaki dosyayı silme koruması; mevcut yüklemeler otomatik kütüphaneye alınır.
- **Video** (MP4, WebM) yükleme ve sunma — HTTP Range desteğiyle ileri-geri sarma; **GIF** görsel desteği.
- **Markdown medya sözdizimi:** altyazılı görsel (`<figure>`), `:::galeri`, `@[video](...)`, `@[youtube](...)`, `@[vimeo](...)`; tıklayınca büyüyen görseller (lightbox).
- **Editör araçları:** dosya yükle, kütüphaneden seç, YouTube/Vimeo/galeri/kod şablonları, canlı **önizleme**.
- **Görsel gizliliği:** JPEG/PNG/WebP'lerden EXIF/GPS/IPTC/XMP/yorum bilgisi **GD gerekmeden, kayıpsız** silinir (JPEG yönü korunur); dosya sonuna eklenmiş artıklar atılır.
- **Site ayarları (beyaz etiket):** site adı/açıklaması, logo, paylaşım görseli, ana sayfa karşılama metni, **Hakkımda** sayfası, sosyal bağlantılar, altbilgi — hepsi panelden.
- **SEO:** RSS (`/rss.xml`), dinamik `robots.txt`, Open Graph / Twitter kartı, `og:image`, JSON-LD (WebSite, BlogPosting).
- **İki adımlı doğrulama (TOTP, RFC 6238)** + yedek kodlar; **Profil** sayfası (parola değiştirme); CLI ile sıfırlama (`--2fa-sifirla`).
- **E-posta bildirimi** (bağımlılıksız SMTP istemcisi: STARTTLS/SSL, AUTH LOGIN) ve `bin/eposta-test.php`.
- **Yedekleme** (`bin/yedekle.php`): mysqldump gerektirmez, gzip, rotasyon, isteğe bağlı medya arşivi.
- **Veri kaybı olmadan güncelleme:** `database/migrasyon.php` (idempotent), `install.php` artık güncelleme de yapar.
- PowerShell, Dockerfile, Nginx ve INI/.env için sözdizimi renklendirme eklentisi (`public/assets/js/dil-ek.js`).
- GitHub hazırlığı: MIT lisans, README (TR/EN), katkı/güvenlik/davranış belgeleri, CI iş akışı, issue/PR şablonları, `composer.json`.
- Testler 26 → 45+ (Markdown medya, TOTP RFC vektörleri, görsel üstveri temizliği, rota/sınıf bütünlüğü…).

### Değişti
- Varsayılan görsel sınırı 2 MB → **5 MB**; video sınırı 50 MB (`YUKLEME_*`). `public/.htaccess`, `nginx.conf.example` ve Docker ayarları buna uyarlandı.
- Yükleme dizini artık görsel + video tutar; kapak görselleri de kütüphanede görünür.
- Docker imajına `exif`, `zip`, WebP desteği eklendi.
- Varsayılan site ayarları nötr hâle getirildi (örnek kişi adı kaldırıldı); mevcut ayarlar **korunur**.

### Düzeltildi
- XAMPP alt klasöründe (`http://localhost/portfolyo/`) tüm sayfaların 404 vermesi (kök `.htaccess` + taban yol tespiti).
- Eksik/kaybolmuş CSS: kartlar, sayfalama, etiketler, menü düğmesi, hata sayfası vb. 34 sınıf yeniden tanımlandı.
- `seed.php` örnek makalelerinde kod bloklarının bozulması (değişken enterpolasyonu) ve kod bloklarının blok olarak tanınmaması.
- Font ön yüklemesinin (preload) iki kez indirmeye yol açması.
- Ana sayfa tanıtım metninde çift nokta.
- CLI'dan çalışırken `temel_yol()`'un betik klasörünü döndürmesi.

### Güvenlik
- Uygulama anahtarıyla AES-256-GCM şifreli 2FA gizli anahtarı; TOTP tekrar kullanım engeli ve 2FA için ayrı brute-force kilidi.
- SMTP başlık enjeksiyonu (CRLF) koruması; yüklemelerde polyglot artık temizliği; `bin/`, `.github/` dizinlerinin web'den erişimi kapatıldı.

## [1.0.0]

İlk sürüm: saf PHP MVC, PDO, CSRF, CSP, oturum sertleştirme, blog/proje/iletişim, yönetim paneli, koyu/açık tema, Docker ve Nginx örnekleri.
