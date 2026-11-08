# Portfolyo — kişisel portfolyo, blog ve bilgi kütüphanesi

Saf (framework'süz) **PHP 8.2+** ile yazılmış; güvenli, hızlı ve sade bir **portfolyo + blog + çözüm arşivi** sistemi.
Kurulumu birkaç dakika sürer, paylaşımlı hostingde de çalışır, ayarların tamamı yönetim panelinden yapılır.

[![CI](https://github.com/KULLANICI/portfolyo/actions/workflows/ci.yml/badge.svg)](https://github.com/KULLANICI/portfolyo/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![Lisans](https://img.shields.io/badge/lisans-MIT-green)
![Bağımlılık](https://img.shields.io/badge/composer%20bağımlılığı-yok-blue)

> English summary: [README.en.md](README.en.md)

Kendi kişisel sitem için yaptım; **başkalarının da kendi ihtiyacına göre kullanabilmesi için** açık kaynak olarak paylaşıyorum.
Site adı, ana sayfa başlığı, "Hakkımda", sosyal bağlantılar, logo ve paylaşım görseli gibi her şey kod değiştirmeden **Ayarlar** sayfasından düzenlenir.

## Özellikler

**Ziyaretçi tarafı**
- Ana sayfa (düzenlenebilir karşılama alanı), projeler, blog / bilgi kütüphanesi (kategori, etiket, arama, sayfalama), "Hakkımda", iletişim
- Koyu / açık tema, mobil uyumlu, erişilebilir (klavye, `prefers-reduced-motion`), modern minimal tasarım — harici CDN/font yok
- Kod bloklarında sözdizimi renklendirme + **kopyala** düğmesi (highlight.js, kendi sunucunuzda)
- SEO: temiz URL, `sitemap.xml`, **RSS**, dinamik `robots.txt`, Open Graph / Twitter kartı, JSON-LD
- İletişim formu: AJAX, bot tuzağı (honeypot), hız sınırı, kaydı panelde + isteğe bağlı **e-posta bildirimi (SMTP)**

**İçerik ve medya**
- Güvenli Markdown: başlık, liste, tablo, alıntı, kod, bağlantı
- **Görsel** (altyazılı figür, tıklayınca büyür), **galeri**, **video** (MP4/WebM, ileri-geri sarma destekli), **YouTube/Vimeo** gömme (gizlilik dostu `youtube-nocookie`)
- **Medya kütüphanesi**: çoklu yükleme, alt metin, kopyalanabilir Markdown kodu, kullanımdaki dosyayı silme koruması
- Yüklenen fotoğraflardan **EXIF/GPS konum bilgisi otomatik silinir** (GD gerekmez)
- Editörde canlı önizleme, yükleme düğmesi, kütüphaneden seçme, hazır şablonlar

**Yönetim**
- Dashboard, makale / proje / kategori / etiket / mesaj / kullanıcı yönetimi, roller (admin, editör)
- **İki adımlı doğrulama (TOTP)** — Google/Microsoft Authenticator, Authy, Bitwarden, 1Password… yedek kodlarla
- Brute-force kilidi, güçlü parola politikası, kendi parolasını değiştirme
- **Yedekleme ve geri yükleme sihirbazı** (panelden tek tık; mysqldump gerekmez): kuru çalıştırma doğrulaması, geri yüklemeden önce otomatik yedek + hata olursa geri alma, otomatik zamanlama, **e-posta / FTP(S) / WebDAV ile dış yedek**
- **Güvenlik merkezi:** açık oturumlar (uzaktan kapatma), denetim günlüğü, e-postayla **parola sıfırlama**, yeni cihaz bildirimi, zorunlu 2FA seçeneği
- **Sayfalar + KVKK:** serbest sayfalar, hazır aydınlatma/gizlilik şablonları, iletişim formunda onay kutusu, mesaj saklama süresi, IP kısaltma
- Veri kaybı olmadan **güncelleme** (SSH olmadan da: panele girince şema otomatik güncellenir) ve **bakım görevleri** (günlük döndürme, temizlik; cron ya da web-cron)
- **Tarayıcıdan kurulum sihirbazı** (SSH olmayan hostingler için; varsayılan kapalı, token korumalı) ve panelde **canlı ortam kontrolü** (debug açık, HTTPS yok, proxy ayarı eksik, `install.php` duruyor…)

**Güvenlik** (ayrıntı: [SECURITY.md](SECURITY.md))
PDO hazır sorgular · her çıktı kaçışlanır · CSRF · sıkı CSP (nonce) · oturum sertleştirme (HttpOnly, SameSite, IP/UA bağlama) ·
`password_hash` (bcrypt) · içerik tabanlı MIME doğrulama ile yükleme (SVG yok, yüklemeler web root dışında) · başlık enjeksiyonuna karşı korumalı SMTP · hız sınırları

## Hızlı başlangıç

Gereksinimler: **PHP 8.2+** (`pdo_mysql`, `mbstring`, `fileinfo`, `openssl`; önerilen `gd`, `exif`, `zip`) ve **MySQL 8 / MariaDB 10.4+**.

```bash
git clone https://github.com/KULLANICI/portfolyo.git
cd portfolyo
cp config/.env.example .env           # Windows: copy config\.env.example .env   → DB_* değerlerini düzenleyin
php database/install.php              # veritabanı + tablolar + ilk yönetici (parolayı sorar)
php -S localhost:8000 -t public public/index.php
```

Site: <http://localhost:8000> · Yönetim: <http://localhost:8000/admin/giris>

Diğer kurulum yolları — **XAMPP (Windows)**, Linux (Apache/Nginx), Docker — için: **[docs/KURULUM.md](docs/KURULUM.md)**.
**Hosting + alan adı + SSL ile yayınlamak** (SSH olmadan, tarayıcıdan kurulum dahil) için: **[docs/YAYINLAMA.md](docs/YAYINLAMA.md)**.
İçerik yazma, medya ve ayarlar için: **[docs/KULLANIM.md](docs/KULLANIM.md)**.

## Yapılandırma

Tüm sunucu ayarları `.env` dosyasındadır (şablon: [`config/.env.example`](config/.env.example)). Site içeriği/markası ise panelde **Ayarlar** altındadır.

| Alan | Ne yapar |
|---|---|
| `APP_URL`, `APP_ENV`, `APP_DEBUG` | Adres (canonical/RSS/og:image için), ortam, hata gösterimi |
| `DB_*` | Veritabanı bağlantısı |
| `SESSION_SECURE`, `HSTS` | HTTPS çerezi ve HSTS (canlıda `true`) |
| `YUKLEME_MAX_BOYUT_MB`, `YUKLEME_VIDEO_MAX_MB` | Görsel / video boyut sınırları |
| `MAIL_*` | İletişim formu için SMTP bildirimi (isteğe bağlı) |
| `YEDEK_SAKLA` | Tutulacak yedek sayısı |

## Komutlar

```bash
php database/install.php                      # kur / güncelle (veri korunur)
php database/install.php --parola-sifirla=a@b.c   # parola sıfırla
php database/install.php --2fa-sifirla=a@b.c      # telefonunu kaybeden kullanıcının 2FA'sını kapat
php bin/yedekle.php [--medya] [--dis]         # veritabanı (+ medya) yedeği → storage/yedekler/ (+ dış hedefler)
php bin/geri-yukle.php <dosya> [--kuru]       # yedekten geri yükle (önce doğrular, öncesinde otomatik yedek alır)
php bin/bakim.php                             # bakım görevleri (cron: günde bir)
php bin/parola-sifirla.php a@b.c              # sunucudan parola sıfırla (geçici parola üretir)
php bin/eposta-test.php [alici@adres.com]     # SMTP ayarlarını sına
php tests/calistir.php                        # otomatik testler (veritabanı gerekmez)
python tests/statik_kontrol.py                # PHP'siz hızlı tutarlılık denetimi (isteğe bağlı)
```

## Proje yapısı

```text
app/Core/          Çekirdek: yönlendirici, DB, oturum, CSRF, Markdown, yükleme, TOTP, SMTP…
app/Controllers/   İstek işleyiciler (Admin/ altında yönetim)
app/Models/        Veri erişimi (PDO hazır sorgular)
app/Views/         Şablonlar (layouts/, partials/, admin/, …)
config/            config.php, rotalar.php, .env.example
database/          schema.sql, migrasyon.php, install.php, seed.php (isteğe bağlı örnek içerik)
bin/               Komut satırı araçları (yedek, e-posta testi)
public/            Tek web girişi: index.php + assets/   ← DocumentRoot burası olmalı
storage/           Yüklemeler, oturumlar, günlükler, yedekler (web root dışında)
tests/             Otomatik testler
docs/              Rehberler
```

## Başkaları için notlar / sınırlar

- Arayüz dili **Türkçe**dir (çok dilli altyapı yok). Çeviri/çok dillilik katkıları memnuniyetle karşılanır.
- Tek yazar / küçük ekip için tasarlandı: yorum sistemi, genel üyelik ve e-ticaret kapsam dışıdır.
- GD eklentisi yoksa çok büyük görseller küçültülmez (EXIF/GPS yine silinir).
- Güvenlik için yalnızca kendi sunucunuzdaki kaynaklar (self-hosted) kullanılır; harici script/font eklerseniz `app/Core/Guvenlik.php` içindeki CSP'yi güncellemeniz gerekir.

## Katkı, güvenlik, lisans

- Katkı: [CONTRIBUTING.md](CONTRIBUTING.md) · Davranış kuralları: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- Güvenlik açığı bildirimi: [SECURITY.md](SECURITY.md) (lütfen herkese açık issue açmayın)
- Sürüm notları: [CHANGELOG.md](CHANGELOG.md)
- Lisans: [MIT](LICENSE)

Yazı tipi: [Inter](public/assets/fonts/INTER-LICENSE.txt) (SIL OFL). Sözdizimi renklendirme: highlight.js (BSD-3).
