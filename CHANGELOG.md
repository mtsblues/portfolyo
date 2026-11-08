# Değişiklik günlüğü

Biçim [Keep a Changelog](https://keepachangelog.com/tr/1.1.0/) ilkelerine, sürümleme [SemVer](https://semver.org/lang/tr/) ilkelerine yakındır.

## [1.3.0] — 2026-11-09 — Güvence

> **Güncelleme:** yedek alın, dosyaları üzerine yazın (`.env` ve `storage/` korunur) ve yönetim paneline girin; şema geçişleri (4 yeni tablo, `mesajlar.kvkk_onay`, yeni ayarlar) otomatik uygulanır. `bin/yedekle.php --yuklemeler` artık `--medya` (eski ad çalışmaya devam eder); medya yedeği `.zip` yerine `.tar.gz` olur.

### Eklendi
- **Yedekleme ve geri yükleme** (Yönetim → Yedekleme, `bin/yedekle.php`, `bin/geri-yukle.php`): tek tıkla veritabanı (+ medya) yedeği, indirme, silme, dışarıdan yedek yükleme, **geri yükleme sihirbazı** (kuru çalıştırma özeti → parola + 2FA + yazılı onay → otomatik "geri yükleme öncesi" yedek → uygula, hata olursa otomatik geri alma). Yedek dosyaları katı beyaz listeyle doğrulanır; daha yeni şemadan yedek reddedilir, eskisi geri yüklenince tamamlanır. Geri yükleme sürerken site kısa süre 503 "bakımda" döner.
- **Otomatik yedek + dış hedefler:** Ayarlar → Otomatik yedekleme (N günde bir) ve **e-posta (ek, ≤15 MB) / FTP-FTPS / WebDAV**; hedef parolaları yalnızca `.env`'de (`YEDEK_FTP_*`, `YEDEK_WEBDAV_*`).
- **Parola sıfırlama** (e-posta): tek kullanımlık, 60 dk, yalnızca özeti saklanan jeton; kullanıcı numaralandırmaya karşı korumalı; `bin/parola-sifirla.php` ile sunucudan kurtarma.
- **Güvenlik merkezi** (Yönetim → Güvenlik): açık oturumlar ve **uzaktan kapatma**, giriş geçmişi, **denetim günlüğü** (filtre + sayfalama); parola değişince diğer oturumlar kapanır; **yeni cihaz girişinde e-posta**; Ayarlar'dan **2FA zorunluluğu** (kilitlenmeye karşı korumalı).
- **Sayfalar** (Yönetim → Sayfalar, `/sayfa/{adres}`): Markdown serbest sayfalar, menü/altbilgi, taslak/yayında, site haritası; **hazır şablonlar** (KVKK aydınlatma metni, gizlilik ve çerez bildirimi, bu site hakkında — genel başlangıç metni, hukuki danışmanlık değildir).
- **Gizlilik ayarları:** iletişim formunda aydınlatma onay kutusu (kayda işlenir), mesaj saklama süresi, **IP saklama** (tam / kısaltılmış / hiç).
- **Bakım görevleri** (`bin/bakim.php`, panel açılışında otomatik, ya da `/bakim/{BAKIM_TOKEN}` web-cron): günlük döndürme, eski kayıt/oturum/önbellek temizliği, saklama süresi, otomatik yedek.
- Canlı ortam kontrolüne: otomatik yedek kapalı, yedekler yalnızca sunucuda, KVKK metni bağlı değil.
- Testler 50 → 58; `statik_kontrol.py` heredoc'u tanır.

### Değişti
- Ayar şeması: `secim` tipi ve sayı aralıkları; sayı alanlarında `0` artık geçerli.
- `IletisimController`: saatlik mesaj sınırı saklanan (kısaltılmış) IP'ye göre sayılır; IP hiç saklanmıyorsa yalnızca dosya tabanlı hız sınırı geçerlidir.

## [1.2.0] — 2026-11-08

> **Güncelleme:** dosyaları üzerine yazın ve yönetim paneline girin (şema geçişleri otomatik uygulanır) ya da `php database/install.php` çalıştırın.

### Eklendi
- **Tarayıcıdan kurulum sihirbazı** (`/kurulum`): SSH/komut satırı olmayan hostingler için. Varsayılan **kapalı**; `.env` içinde en az 16 karakterlik `KURULUM_TOKEN` varsa ve veritabanında hiç kullanıcı yokken açılır, aksi halde 404 verir. Ön kontrol listesi (PHP sürümü/eklentiler, klasör izinleri, veritabanı), hız sınırı, CSRF, güçlü parola zorunluluğu; veritabanı yoksa oluşturmayı dener.
- **Canlı ortam kontrolü** (Dashboard): debug açık, HTTPS yok, `SESSION_SECURE`/HSTS, `APP_URL` uyumsuzluğu, Cloudflare/proxy (`GUVENILIR_PROXY`), kalan kurulum betikleri/token, 2FA, yazılamayan `storage`, PHP yükleme sınırı, eksik eklentiler ve eski yedek için uyarılar.
- **SSH'siz güncelleme:** yönetici panele girdiğinde kayıtlı şema sürümü eskiyse geçişler otomatik uygulanır (tekrar çalıştırılabilir, hata verirse siteyi bozmaz).
- **docs/YAYINLAMA.md:** hosting + alan adı + SSL ile yayınlama rehberi (cPanel/Plesk, DNS, VPS, Cloudflare, e-posta, yedek, kontrol listesi, sorun giderme).
- `public/.htaccess` içinde hazır (yorumlu) HTTP→HTTPS ve www→www'suz yönlendirme kuralları.
- `temel_yol_hesapla()`: tüm barındırma biçimleri (DocumentRoot, alan adı kökü, alt klasör) için test edilen saf fonksiyon.
- Testler 45 → 50.

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
