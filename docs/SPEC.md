# Kişisel Portfolyo, Blog ve Bilgi Kütüphanesi — Teknik Spesifikasyon (SPEC)

Sürüm: 1.0.0 (başlangıç spesifikasyonu)
Durum: **Tarihsel belge.** Mimari ve güvenlik ilkeleri hâlâ geçerlidir; ancak 1.1.0 ile eklenen özellikler (medya kütüphanesi, video, 2FA, SMTP,
RSS, yedekleme, ayarlar…) burada anlatılmaz. Güncel durum için README.md, docs/KULLANIM.md ve CHANGELOG.md dosyalarına bakın.
Hedef: Saf (Core) PHP 8.2+ ile framework kullanmadan MVC mimarisinde, güvenli, hızlı ve modern arayüzlü bir web sitesi.

---

## 1. Genel Bakış

Bu sistem; kişisel portfolyo, blog ve bilgi kütüphanesi (çözüm arşivi) işlevlerini tek bir uygulamada birleştirir. Arka planda hiçbir hazır framework kullanılmaz. Tüm HTTP istekleri public/index.php (Front Controller) üzerinden geçer; Yonlendirici sınıfı isteği uygun denetleyici/eylem çiftine yönlendirir.

Ana özellikler:

- Anasayfa: Hero alanı, son projeler ve son makaleler.
- Projeler: Proje listesi ve SEO uyumlu detay sayfaları.
- Blog / Bilgi Kütüphanesi: Kategoriler, etiketler ve kod bloklu makaleler.
- İletişim: AJAX tabanlı güvenli mesaj gönderme.
- Admin Paneli: Modern dashboard; makale, proje, kategori, etiket, mesaj ve ayar yönetimi.
- Koyu/Açık tema, responsive tasarım, syntax highlighting, kopyala butonu.

---

## 2. Hedefler ve Kapsam

### 2.1 Hedefler

| Hedef | Öncelik | Kriter |
|---|---|---|
| Güvenlik | Kritik | PDO prepared statements, CSRF, XSS kaçışlama, session hardening, brute-force limiti, güvenlik başlıkları |
| Bakım yapılabilirlik | Yüksek | Framework'süz ama katmanları net MVC yapısı; her sınıf tek sorumluluk |
| Hız | Yüksek | Statik varlıklar için makul cache başlıkları, ölçülü sorgu sayısı, indeksli şema |
| SEO | Yüksek | Temiz URL, slug, meta etiketler, semantik HTML |
| UX/UI | Yüksek | Minimal, ferah; koyu/açık tema; mobil uyumlu |

### 2.2 Kapsam Dışı

- Üyelik sistemi (yalnızca tek/multi admin kullanıcısı vardır, genel kullanıcı kaydı yoktur).
- E-ticaret, ödeme, abonelik.
- Çok dilli içerik altyapısı (ilk sürümde tek dil: Türkçe).
- Yorum sistemi (mesajlar yalnızca iletişim formu üzerinden ulaşır; makale yorumu kapsam dışıdır).

---

## 3. Varsayımlar

- Çalışma ortamı: PHP 8.2+, MySQL 8.0+ / MariaDB 10.6+.
- Web sunucusu olarak Apache (XAMPP) veya Nginx desteklenir.
- Varsayılan site dili Türkçe; içerik dili Türkçe.
- Yalnızca admin içerik girebilir. Admin içerik alanları Markdown olarak saklanır, çalışma anında güvenli biçimde HTML'e dönüştürülür.
- Tüm istemci tarafı varlıklar (CSS/JS/font/ikon) self-hosted (kendi sunucusunda) tutulur. Bu; hem gizlilik hem de CSP uyumluluğu için zorunludur.
- Email gönderimi ilk sürümde SMTP üzerinden opsiyoneldir; iletişim mesajları öncelikle veritabanına yazılır ve admin panelinden görüntülenir.

---

## 4. Teknoloji Yığını

| Katman | Teknoloji | Not |
|---|---|---|
| Backend | Saf PHP 8.2+ | Composer paketine bağımlılık YOK |
| Veritabanı | MySQL 8.0+ / MariaDB 10.6+ | InnoDB, utf8mb4 |
| Veritabanı erişimi | PDO | Yalnızca prepared statements |
| Frontend | HTML5, Custom CSS (CSS değişkenleri), Vanilla JS | Tailwind yerine kendi kurallı tasarım sistemi |
| Syntax highlighting | highlight.js (self-hosted) | Kod bloklarında vurgu + kopyala butonu |
| İkonlar | Inline SVG / self-hosted SVG sprite | Harici CDN yok |
| Font | Inter veya Roboto (self-hosted woff2) | Harici CDN yok |
| Şifreleme | password_hash() / password_verify() | PASSWORD_BCRYPT (varsayılan) |
| Yapılandırma | .env + config/config.php | Gizli bilgiler koda gömülmez |

---

## 5. Mimari

### 5.1 Katmanlar

```text
HTTP İsteği
    │
    ▼
public/index.php (Front Controller)
    │  bootstrap: sabitler, .env yükleme, hata modu, oturum başlatma
    ▼
Yonlendirici (app/Core/Yonlendirici.php)
    │  URL'i çözümler, route eşleştirir, denetleyici@eylem çağırır
    ▼
Denetleyici (app/Controllers/*)
    │  girdiyi doğrular, model/service çağırır, görünüm seçer
    ▼
Model (app/Models/*)
    │  yalnızca veri erişimi; PDO prepared statements
    ▼
Görünüm (app/Views/*)
    │  HTML üretir; çıktı kaçışlanır (e() yardımcısı)
    ▼
HTTP Yanıtı
```

### 5.2 Bağımlılık Yönü (önemli)

- Denetleyici -> Model kullanabilir.
- Denetleyici -> Gorunum çağırabilir.
- Model doğrudan Gorunum veya Denetleyici çağırmaz.
- Gorunum SQL çalıştırmaz; yalnızca denetleyiciden geçen hazır veriyi yazar.
- Core sınıfları birbirini yalnızca gerektiğinde kullanır; döngüsel bağımlılık oluşturulmaz.

### 5.3 Temel Core Sınıfları

| Sınıf | Görev |
|---|---|
| Yonlendirici | Rota kaydı, HTTP metod kontrolü, slug parametre çözme, 404/405 yönetimi |
| Veritabani | PDO bağlantısı, prepared statement yardımcıları |
| Oturum | Güvenli oturum başlatma, kimlik doğrulama durumu, oturum koruması |
| Csrf | CSRF token üretme/doğrulama, form + AJAX başlığı desteği |
| Dogrulayici | Sunucu tarafı veri doğrulama ve sanitizasyon kuralları |
| Gorunum | Şablon render etme, layout, partial, veri paylaşımı |
| Denetleyici | Temel denetleyici: json yanıt, redirect, 404, view yardımcıları |

---

## 6. Klasör ve Dosya Yapısı

```text
proje-koku/
├── public/
│   ├── index.php                     # Front Controller (tek giriş noktası)
│   ├── .htaccess                     # Apache rewrite kuralları
│   └── assets/
│       ├── css/
│       │   ├── tema.css              # tasarım değişkenleri + base + layout
│       │   └── admin.css             # admin panel stilleri
│       ├── js/
│       │   ├── uygulama.js           # tema değiştirme, mobile menü, AJAX iletisim
│       │   └── admin.js              # panel etkileşimleri
│       ├── img/                      # statik görseller, favicon
│       └── vendor/
│           ├── highlight/            # highlight.js (self-hosted)
│           └── fonts/                # Inter/Roboto woff2
├── app/
│   ├── Core/
│   │   ├── Yonlendirici.php
│   │   ├── Csrf.php
│   │   ├── Oturum.php
│   │   ├── Veritabani.php
│   │   ├── Dogrulayici.php
│   │   ├── Gorunum.php
│   │   └── Denetleyici.php
│   ├── Controllers/
│   │   ├── AnaSayfaController.php
│   │   ├── ProjeController.php
│   │   ├── BlogController.php
│   │   ├── IletisimController.php
│   │   └── Admin/
│   │       ├── GirisController.php
│   │       ├── PanelController.php
│   │       ├── MakaleController.php
│   │       ├── ProjeController.php
│   │       ├── KategoriController.php
│   │       ├── EtiketController.php
│   │       ├── MesajController.php
│   │       ├── AyarController.php
│   │       └── KullaniciController.php
│   ├── Models/
│   │   ├── Kullanici.php
│   │   ├── Kategori.php
│   │   ├── Makale.php
│   │   ├── Etiket.php
│   │   ├── Proje.php
│   │   ├── Mesaj.php
│   │   └── Ayar.php
│   ├── Views/
│   │   ├── layouts/
│   │   │   ├── main.php              # genel site layout (header/nav/footer)
│   │   │   └── admin.php             # admin layout (sidebar/topbar)
│   │   ├── partials/
│   │   │   ├── nav.php
│   │   │   ├── footer.php
│   │   │   ├── proje_karti.php
│   │   │   ├── makale_karti.php
│   │   │   └── pagination.php
│   │   ├── home/
│   │   │   └── index.php
│   │   ├── proje/
│   │   │   ├── liste.php
│   │   │   └── detay.php
│   │   ├── blog/
│   │   │   ├── liste.php
│   │   │   ├── detay.php
│   │   │   └── kategori.php
│   │   ├── iletisim/
│   │   │   └── index.php
│   │   ├── hata/
│   │   │   ├── 404.php
│   │   │   ├── 403.php
│   │   │   └── 500.php
│   │   └── admin/
│   │       ├── giris.php
│   │       ├── panel.php
│   │       ├── makaleler.php
│   │       ├── makale_form.php
│   │       ├── projeler.php
│   │       ├── proje_form.php
│   │       ├── kategoriler.php
│   │       ├── etiketler.php
│   │       ├── mesajlar.php
│   │       ├── ayarlar.php
│   │       └── kullanicilar.php
│   └── helpers/
│       └── fonksiyonlar.php          # e(), csrf_field(), asset(), url() vb.
├── config/
│   ├── config.php                    # .env'i yükler, sabitleri tanımlar
│   └── .env.example                  # örnek yapılandırma (gizli değer yok)
├── database/
│   ├── schema.sql                    # tam şema (DDL) + temel ayarlar
│   └── install.php                   # kurulum CLI betiği (admin oluşturur)
├── storage/
│   ├── cache/                        # opsiyonel view/route cache
│   └── yuklemeler/                   # yüklenen dosyalar (web root DIŞINDA)
├── tests/
│   ├── bootstrap.php
│   ├── Birim/
│   │   ├── DogrulayiciTest.php
│   │   ├── CsrfTest.php
│   │   └── YonlendiriciTest.php
│   └── Entegrasyon/
│       ├── VeritabaniTest.php
│       └── IletisimAkisiTest.php
├── docs/
│   └── SPEC.md                       # bu doküman
├── nginx.conf.example                # Nginx örnek sunucu bloğu
└── README.md                         # kurulum ve kullanım kılavuzu
```

### 6.1 Klasör Sorumlulukları

- public/: Sunucunun doküman kökü BURASI olmalıdır. Uygulama kodu web kökünün dışında kalır. Bu, doğrudan erişimi engeller.
- app/: Tüm uygulama kodu. Core framework görevi görür; Controllers, Models, Views MVC katmanlarıdır.
- config/: Ortam yapılandırması. .env asla git deposuna girmez; yalnızca .env.example sürümlenir.
- database/: Şema ve kurulum betiği. install.php yalnızca komut satırından çalıştırılmalıdır.
- storage/: Yazılabilir alanlar. yuklemeler/ web root dışında olduğu için yüklenen dosyalara doğrudan URL ile erişilemez.
- tests/: Birim ve entegrasyon testleri.

## 7. Veritabanı Şeması

### 7.1 Genel Kurallar

- Motor: InnoDB (FK bütünlüğü ve transaction desteği).
- Karakter seti: utf8mb4, collation: utf8mb4_unicode_ci (taşınabilirlik için önerilir).
- Tüm tablolarda id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY.
- Slug sütunları VARCHAR NOT NULL UNIQUE.
- Zaman alanları UTC olarak saklanır; görünümde dilime çevrilir. olusturulma TIMESTAMP DEFAULT CURRENT_TIMESTAMP; guncellenme TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP.

### 7.2 Tablolar ve İlişkiler

```text
kullanicilar 1─────※ makaleler ※─────1 kategoriler
     │                    │
     │                    │
     │                    ※
     │              makale_etiket
     │                    ※
     │                    │
     │                    ※───── etiketler
     │
kullanicilar (admin) → projeler (yazar bilgisi opsiyonel; projeler ayrı tutulur)

mesajlar bağımsız (iletişim formu)
ayarlar bağımsız (anahtar-değer)
giris_denemeleri bağımsız (brute-force izleme)
```

### 7.3 DDL (database/schema.sql)

```sql
-- =====================================================================
-- Kişisel Portfolyo, Blog ve Bilgi Kütüphanesi — Veritabanı Şeması
-- Motor: InnoDB  |  Karakter seti: utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `portfolyo`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `portfolyo`;

-- ---------------------------------------------------------------------
-- kullanicilar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kullanicilar` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_soyad`      VARCHAR(120)     NOT NULL,
  `eposta`        VARCHAR(180)     NOT NULL,
  `parola_hash`   VARCHAR(255)     NOT NULL COMMENT 'password_hash() çıktısı',
  `rol`           ENUM('admin','editor') NOT NULL DEFAULT 'admin',
  `aktif`         TINYINT(1)       NOT NULL DEFAULT 1,
  `son_giris`     DATETIME         NULL DEFAULT NULL,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guncellenme`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kullanicilar_eposta` (`eposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- kategoriler
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategoriler` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad`            VARCHAR(120)     NOT NULL,
  `slug`          VARCHAR(180)     NOT NULL,
  `aciklama`      VARCHAR(500)     NULL DEFAULT NULL,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guncellenme`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kategoriler_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- makaleler
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `makaleler` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori_id`   BIGINT UNSIGNED NOT NULL,
  `kullanici_id`  BIGINT UNSIGNED NOT NULL,
  `baslik`        VARCHAR(220)     NOT NULL,
  `slug`          VARCHAR(220)     NOT NULL,
  `ozet`          VARCHAR(500)     NULL DEFAULT NULL,
  `icerik`        LONGTEXT         NOT NULL COMMENT 'Markdown olarak saklanır',
  `durum`         ENUM('taslak','yayinda','arsiv') NOT NULL DEFAULT 'taslak',
  `one_cikan`     TINYINT(1)       NOT NULL DEFAULT 0,
  `okunma_suresi` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'dakika olarak hesaplanır',
  `kapak_gorsel`  VARCHAR(255)     NULL DEFAULT NULL,
  `anahtar_kelimeler` VARCHAR(500)  NULL DEFAULT NULL COMMENT 'meta keywords',
  `yayin_tarihi`  DATETIME         NULL DEFAULT NULL,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guncellenme`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_makaleler_slug` (`slug`),
  KEY `idx_makaleler_kategori` (`kategori_id`),
  KEY `idx_makaleler_kullanici` (`kullanici_id`),
  KEY `idx_makaleler_durum_yayin` (`durum`, `yayin_tarihi`),
  KEY `idx_makaleler_one_cikan` (`one_cikan`, `yayin_tarihi`),
  CONSTRAINT `fk_makaleler_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_makaleler_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- etiketler
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `etiketler` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad`            VARCHAR(80)      NOT NULL,
  `slug`          VARCHAR(100)     NOT NULL,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etiketler_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- makale_etiket (çoka çok bağ)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `makale_etiket` (
  `makale_id`     BIGINT UNSIGNED NOT NULL,
  `etiket_id`     BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`makale_id`, `etiket_id`),
  KEY `idx_makale_etiket_etiket` (`etiket_id`),
  CONSTRAINT `fk_makale_etiket_makale` FOREIGN KEY (`makale_id`) REFERENCES `makaleler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_makale_etiket_etiket` FOREIGN KEY (`etiket_id`) REFERENCES `etiketler` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- projeler
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projeler` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `baslik`        VARCHAR(180)     NOT NULL,
  `slug`          VARCHAR(180)     NOT NULL,
  `aciklama`      VARCHAR(500)     NOT NULL,
  `icerik`        LONGTEXT         NOT NULL COMMENT 'Markdown olarak saklanır',
  `gorsel`        VARCHAR(255)     NULL DEFAULT NULL,
  `proje_url`     VARCHAR(255)     NULL DEFAULT NULL,
  `github_url`    VARCHAR(255)     NULL DEFAULT NULL,
  `one_cikan`     TINYINT(1)       NOT NULL DEFAULT 0,
  `durum`         ENUM('aktif','devam_ediyor','tamamlandi','arsiv') NOT NULL DEFAULT 'aktif',
  `sira`          INT              NOT NULL DEFAULT 0,
  `tamamlanma_tarihi` DATE         NULL DEFAULT NULL,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guncellenme`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projeler_slug` (`slug`),
  KEY `idx_projeler_durum_sira` (`durum`, `sira`),
  KEY `idx_projeler_one_cikan` (`one_cikan`, `sira`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- mesajlar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mesajlar` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_soyad`      VARCHAR(120)     NOT NULL,
  `eposta`        VARCHAR(180)     NOT NULL,
  `konu`          VARCHAR(180)     NOT NULL,
  `mesaj`         TEXT             NOT NULL,
  `ip_adresi`     VARBINARY(16)    NOT NULL COMMENT 'inet_pton ile saklanır (IPv4/IPv6)',
  `okundu`        TINYINT(1)       NOT NULL DEFAULT 0,
  `olusturulma`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mesajlar_okundu` (`okundu`, `olusturulma`),
  KEY `idx_mesajlar_eposta` (`eposta`),
  KEY `idx_mesajlar_ip` (`ip_adresi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ayarlar (anahtar-değer)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ayarlar` (
  `anahtar`       VARCHAR(100)     NOT NULL,
  `deger`         TEXT             NULL,
  `aciklama`      VARCHAR(255)     NULL DEFAULT NULL,
  `guncellenme`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- giris_denemeleri (brute-force koruması)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `giris_denemeleri` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kimlik`        VARCHAR(180)     NOT NULL COMMENT 'denenen eposta veya kullanıcı adı',
  `ip_adresi`     VARBINARY(16)    NOT NULL COMMENT 'inet_pton ile saklanır',
  `basarili`      TINYINT(1)       NOT NULL DEFAULT 0,
  `deneme_zamani` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_giris_kimlik_zaman` (`kimlik`, `deneme_zamani`),
  KEY `idx_giris_ip_zaman` (`ip_adresi`, `deneme_zamani`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Temel ayarlar (seed)
-- ---------------------------------------------------------------------
INSERT INTO `ayarlar` (`anahtar`, `deger`, `aciklama`) VALUES
  ('site_baslik', 'Kişisel Portfolyo', 'Site başlığı'),
  ('site_aciklama', 'Yazılım geliştirici, sistem mimarı ve problem çözücü', 'Meta açıklama'),
  ('site_eposta', 'admin@example.local', 'İletişim e-postası'),
  ('anahtar_kelimeler', 'php, mysql, yazılım, blog', 'Varsayılan meta keywords'),
  ('iletisim_mesaj_ucret_siniri', '20', 'Aynı IP için saat başına mesaj limiti')
ON DUPLICATE KEY UPDATE `deger` = VALUES(`deger`);

-- ---------------------------------------------------------------------
-- ÖRNEK ADMIN KULLANICISI
-- NOT: Bu INSERT bilinçli olarak YORUM satırı bırakılmıştır. Sabit/zayıf
-- bir parolayı şemaya yazmak güvenlik açığıdır. Doğru yol: database/install.php
-- betiğini çalıştırıp parolayı çalışma anında password_hash ile üretmektir.
-- Aşağıdaki satır yalnızca SÖZDİZİMİ örneğidir; hash gerçek değildir.
-- ---------------------------------------------------------------------
-- INSERT INTO `kullanicilar` (`ad_soyad`, `eposta`, `parola_hash`, `rol`, `aktif`)
-- VALUES ('Site Yöneticisi', 'admin@example.local', '$2y$12$ORNEK_BCRYPT_HASH_DEGISTIRIN', 'admin', 1);
```

### 7.4 Admin Kullanıcısı ve Parola Politikası

- Şemada sabit admin parolası bırakılmaz. database/install.php (CLI) çalıştırılırken:
  1. kullanicilar tablosunda kayıt olup olmadığı kontrol edilir.
  2. Kayıt yoksa terminalde ad_soyad, eposta ve parola sorulur (parola gizli okunur).
  3. Parola password_hash($parola, PASSWORD_BCRYPT) ile üretilir.
  4. Hash, açık parola olmadan veritabanına yazılır.
- install.php web erişimine kapalı olacak şekilde public/ dışında tutulur; yalnızca CLI'dan çalışır.
- Kurulum sonrası install.php silinmeli veya en azından kabuk erişimi sınırlandırılmalıdır.
- Parola kuralları (Dogrulayici): en az 12 karakter, en az bir büyük harf, bir küçük harf, bir rakam ve bir özel karakter.

## 8. Rota Tablosu

Tüm rotalar Yonlendirici sınıfında tanımlanır. Öncelik sırası: daha spesifik sabit rotalar önce, değişken segmentli rotalar sonra.

### 8.1 Genel Site

| Metot | Yol | Denetleyici@eylem | Açıklama |
|---|---|---|---|
| GET | / | AnaSayfaController@index | Hero + son projeler + son makaleler |
| GET | /projeler | ProjeController@liste | Proje listesi (sayfalı) |
| GET | /proje/{slug} | ProjeController@detay | Proje detayı |
| GET | /blog | BlogController@liste | Makale listesi (sayfalı) |
| GET | /makale/{slug} | BlogController@detay | Makale detayı |
| GET | /kategori/{slug} | BlogController@kategori | Kategoriye ait makaleler |
| GET | /iletisim | IletisimController@index | İletişim formu (CSRF token içerir) |
| POST | /iletisim/gonder | IletisimController@gonder | AJAX JSON yanıt; mesaj doğrulama + CSRF |

### 8.2 Admin (Kimlik Doğrulama)

| Metot | Yol | Denetleyici@eylem | Açıklama |
|---|---|---|---|
| GET | /admin/giris | Admin\\GirisController@index | Giriş formu |
| POST | /admin/giris | Admin\\GirisController@giris | Giriş denemesi (brute-force limitli) |
| GET | /admin/cikis | Admin\\GirisController@cikis | Çıkış (GET + CSRF denetimi) |
| GET | /admin | Admin\\PanelController@index | Dashboard |
| GET | /admin/makaleler | Admin\\MakaleController@liste | Makale listesi |
| GET | /admin/makale/yeni | Admin\\MakaleController@yeni | Yeni makale formu |
| POST | /admin/makale/yeni | Admin\\MakaleController@kaydet | Kayıt (CSRF) |
| GET | /admin/makale/{id}/duzenle | Admin\\MakaleController@duzenle | Düzenleme formu |
| POST | /admin/makale/{id}/guncelle | Admin\\MakaleController@guncelle | Güncelleme (CSRF) |
| POST | /admin/makale/{id}/sil | Admin\\MakaleController@sil | Silme (CSRF) |
| GET | /admin/projeler | Admin\\ProjeController@liste | Proje listesi |
| GET | /admin/proje/yeni | Admin\\ProjeController@yeni | Yeni proje formu |
| POST | /admin/proje/yeni | Admin\\ProjeController@kaydet | Kayıt (CSRF) |
| GET | /admin/proje/{id}/duzenle | Admin\\ProjeController@duzenle | Düzenleme formu |
| POST | /admin/proje/{id}/guncelle | Admin\\ProjeController@guncelle | Güncelleme (CSRF) |
| POST | /admin/proje/{id}/sil | Admin\\ProjeController@sil | Silme (CSRF) |
| GET | /admin/kategoriler | Admin\\KategoriController@liste | Kategori listesi |
| POST | /admin/kategori/yeni | Admin\\KategoriController@kaydet | Kayıt (CSRF) |
| POST | /admin/kategori/{id}/guncelle | Admin\\KategoriController@guncelle | Güncelleme (CSRF) |
| POST | /admin/kategori/{id}/sil | Admin\\KategoriController@sil | Silme (CSRF) |
| GET | /admin/etiketler | Admin\\EtiketController@liste | Etiket listesi |
| POST | /admin/etiket/yeni | Admin\\EtiketController@kaydet | Kayıt (CSRF) |
| POST | /admin/etiket/{id}/guncelle | Admin\\EtiketController@guncelle | Güncelleme (CSRF) |
| POST | /admin/etiket/{id}/sil | Admin\\EtiketController@sil | Silme (CSRF) |
| GET | /admin/mesajlar | Admin\\MesajController@liste | İletişim mesajları |
| POST | /admin/mesaj/{id}/okundu | Admin\\MesajController@okundu | Okundu işaretle (CSRF) |
| POST | /admin/mesaj/{id}/sil | Admin\\MesajController@sil | Silme (CSRF) |
| GET | /admin/ayarlar | Admin\\AyarController@liste | Site ayarları |
| POST | /admin/ayarlar/guncelle | Admin\\AyarController@guncelle | Güncelleme (CSRF) |
| GET | /admin/kullanicilar | Admin\\KullaniciController@liste | Kullanıcı listesi |
| POST | /admin/kullanici/yeni | Admin\\KullaniciController@kaydet | Yeni kullanıcı (CSRF) |
| POST | /admin/kullanici/{id}/guncelle | Admin\\KullaniciController@guncelle | Güncelleme (CSRF) |
| POST | /admin/kullanici/{id}/sil | Admin\\KullaniciController@sil | Silme (CSRF) |

### 8.3 Rota Kuralları

- {slug} deseni: [a-z0-9]+(-[a-z0-9]+)* regex ile sınırlanır. Geçersiz karakter içeren URL'ler 404 döner.
- {id} deseni: yalnızca pozitif tam sayı ([1-9][0-9]*).
- Tanımsız yollar: 404.
- Yanlış HTTP metoduyla gelen tanımlı yol: 405 + Allow başlığı.
- POST rotaları tümü CSRF doğrulamasına tabidir.
- Admin rotaları (giriş ve çıkış hariç) tümü Oturum::kullanici() kontrolünden geçer; yetkisiz istek 302 ile /admin/giris yönlendirilir veya AJAX ise 401 JSON döner.

## 9. Güvenlik Tasarımı

### 9.1 SQL Injection

- Veritabanı erişimi yalnızca PDO üzerinden yapılır.
- Bütün kullanıcı kaynaklı değerler için prepared statements kullanılır. SQL string birleştirme yasaktır.
- Dinamik sıralama/sayfalama değerleri (ör. ORDER BY) kullanıcıdan alınmaz; sunucudaki beyaz liste ile eşleştirilir.
- PDO::ATTR_EMULATE_PREPARES => false ayarlanır (MySQL'in gerçek prepared statement kullanması sağlanır).
- PDO::ERRMODE_EXCEPTION açıktır; hata mesajları istemciye asla yansıtılmaz, yalnızca sunucu loguna yazılır.

### 9.2 XSS (Cross-Site Scripting)

- Tüm çıktılar görünüme yazılmadan önce e() yardımcı fonksiyonu ile kaçışlanır:

  ```php
  function e(mixed $deger): string {
      return htmlspecialchars((string) $deger, ENT_QUOTES, 'UTF-8');
  }
  ```

- Görünüm dosyalarında echo $degisken; yerine <?= e($degisken) ?> kullanılması zorunludur.
- Zengin metin (makale/proje içeriği) Markdown olarak saklanır. Sunucuda Markdown -> HTML dönüşümünden sonra beyaz liste temelli sanitizasyon uygulanır:
  - script, iframe, object, embed, form, style etiketleri kaldırılır.
  - on* olay öznitelikleri kaldırılır.
  - javascript: ve vbscript: URL şemaları reddedilir.
  - target="_blank" verilen bağlantılara rel="noopener noreferrer" eklenir.
- Kod blokları: Markdown'dan gelen <pre><code> blokları önce e() ile kaçışlanır; ardından istemcide highlight.js sınıfları uygulanır. Böylece kod örneği içindeki < ve > işaretleri güvenli kalır.

### 9.3 CSRF

- Csrf sınıfı oturum başına bir token üretir (random_bytes(32) -> hex).
- Tüm formlar token içeren gizli alan taşır:

  ```html
  <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
  ```

- AJAX isteklerinde token X-CSRF-Token başlığı ile gönderilir; sunucu csrf_token alanını veya başlığı hash_equals() ile doğrular.
- Token doğrulaması başarısızsa: normal istek 403, AJAX isteği { "basarili": false, "hata": "CSRF doğrulaması başarısız." } (HTTP 403) döner.
- Admin ve iletişim dahil tüm POST istekleri bu doğrulamaya tabidir.

### 9.4 Oturum Güvenliği

Oturum sınıfı oturumu şu kurallarla başlatır:

- session_name() uygulamaya özel isim (ör. PORTFOLYO_SESSION).
- session.use_strict_mode = 1 (sunucu tarafından bilinmeyen oturum kimliğini reddeder).
- session.use_only_cookies = 1; session.use_trans_sid = 0 (URL'de oturum kimliği taşınmaz).
- session.cookie_httponly = 1.
- session.cookie_secure = 1 (yalnızca HTTPS; yerel HTTP geliştirmede .env üzerinden özel olarak kapatılabilir ama production'da zorunlu).
- session.cookie_samesite = 'Lax' (admin paneli için Strict yerine Lax; form gönderimlerinde çalışır, CSRF riskini azaltır).
- session.gc_maxlifetime uygulama politikası ile sınırlı (ör. 30 dakika boşta kalma); Oturum::kullanici() her istekte son aktiviteyi kontrol eder.
- Başarılı girişte session_regenerate_id(true) ile Session Fixation engellenir.
- Kullanıcı aracısı (User-Agent) ve IP değişimi temel düzeyde denetlenir; eşleşmezse oturum geçersiz kılınır (NAT/mobil IP değişimi göz önüne alınarak IP eşleşmesi yalnızca ilk 3 oktet için yapılır).
- Çıkışta oturum verisi temizlenir, çerez silinir ve session_regenerate_id(true) uygulanır.

### 9.5 Kimlik Doğrulama ve Brute-Force Koruması

- Giriş başarılı/başarısız her deneme giris_denemeleri tablosuna kaydedilir (IP inet_pton ile binary saklanır).
- Aynı kimlik + ip_adresi için son 15 dakikada 5 başarısız deneme -> 15 dakika kilit (HTTP 429).
- Aynı ip_adresi için son 15 dakikada 20 başarısız deneme (farklı kimliklerle) -> IP bazlı kilit (HTTP 429).
- Kilitli durumda giris eylemi kalan süreyi döndürür; parola doğrulaması yapılmaz.
- Zamanlama saldırısına karşı parola doğrulaması password_verify() ile yapılır; kullanıcı yoksa sahte bir bcrypt hash üzerinde password_verify() çalıştırılarak yanıt süresi eşitlenir.
- Giriş formuna da CSRF token konur.
- Giriş başarılıysa Oturum yenilenir ve kullanicilar.son_giris güncellenir.

### 9.6 Şifre Yönetimi

- Parolalar password_hash($parola, PASSWORD_BCRYPT) ile saklanır; açık parola hiçbir yerde kalıcı tutulmaz.
- Parola doğrulama password_verify() ile yapılır.
- Güncelleme sırasında eski parola doğruyken yeni parola kurallara uygun şekilde değiştirilir.
- Parola sıfırlama mekanizması ilk sürümde yalnızca CLI betiğiyle yapılır (e-posta sıfırlama yok).

### 9.7 Güvenlik Başlıkları ve CSP

Uygulama her HTTP yanıtında şu başlıkları döner:

| Başlık | Değer |
|---|---|
| X-Content-Type-Options | nosniff |
| X-Frame-Options | DENY (admin SAMEORIGIN) |
| Referrer-Policy | strict-origin-when-cross-origin |
| Permissions-Policy | camera=(), microphone=(), geolocation=() |
| Content-Security-Policy | Aşağıdaki politika |

CSP başlangıç politikası (self-hosted varlıklar varsayımıyla):

```text
default-src 'self';
base-uri 'self';
form-action 'self';
frame-ancestors 'none';
img-src 'self' data:;
font-src 'self';
style-src 'self';
script-src 'self' 'nonce-RANDOM';
connect-src 'self';
```

- RANDOM, her yanıtta random_bytes(16) ile yeniden üretilen nonce'tur; inline <script> etiketleri yalnızca bu nonce ile basılır. Harici JS dosyaları self-hosted olduğu için ek kaynak gerekmez.
- highlight.js ve kopyala butonu self-hosted olduğundan CSP 'self' ile uyumludur.
- Production gereksinimi doğarsa ek kaynaklar .env üzerinden izin listesine eklenir; ancak varsayılan olarak 'self' korunur.

### 9.8 Dosya Yükleme Güvenliği

- Yükleme (proje kapak görseli, makale kapak görseli) yalnızca giriş yapmış admine açıktır.
- Dosya içeriği, uzantıya bakılmadan finfo (FILEINFO_MIME_TYPE) ile doğrulanır; yalnızca image/jpeg, image/png, image/webp kabul edilir.
- Dosya boyutu sınırı: 2 MB (yapılandırılabilir).
- Hedef dosya adı random_bytes(16) ile üretilir; kullanıcıdan gelen dosya adı asla kullanılmaz.
- Yüklenen dosyalar storage/yuklemeler/ altında, web root dışında tutulur. Dosyalara public/index.php üzerinden ilgili denetleyici ile erişilir; böylece PHP yorumlayıcısı bu klasörü işlemez.
- Dosya erişiminde yol aşımı önlenir: istenen ad yalnızca [a-f0-9]{32}\.(jpg|png|webp) deseniyle eşleştirilir; basename() ve beyaz liste uygulanır.
- SVG yükleme VARSYILAN OLARAK KAPALIDIR (SVG içinde gömülü script riski nedeniyle).

### 9.9 Gizli Bilgiler (.env)

- Veritabanı kimlik bilgileri, uygulama anahtarı, SMTP kimlik bilgileri gibi değerler .env dosyasında tutulur.
- .env dosyası proje kökünde bulunur, git'e asla eklenmez; .env.example sürümlenir.
- .env örneği:

  ```dotenv
  APP_ENV=local
  APP_DEBUG=true
  APP_URL=http://localhost:8080
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_NAME=portfolyo
  DB_KULLANICI=root
  DB_SIFRE=
  SESSION_SECURE=false
  SESSION_NAME=PORTFOLYO_SESSION
  UYGULAMA_ANAHTARI=
  YUKLEME_MAX_BOYUT_MB=2
  SMTP_HOST=
  SMTP_PORT=587
  SMTP_KULLANICI=
  SMTP_SIFRE=
  SMTP_GUVENLI=true
  ```

- config/config.php bu dosyayı okur; değerleri sabitlere/konteyner dizisine alır.
- UYGULAMA_ANAHTARI kurulumda random_bytes(32) ile üretilmeli ve .env'e yazılmalıdır.
- Production'da APP_DEBUG=false ve SESSION_SECURE=true zorunludur.

### 9.10 Girdi Doğrulama

Dogrulayici sınıfı tüm gelen veriyi (GET, POST, FILES) sunucu tarafında doğrular:

| Alan | Kural |
|---|---|
| ad_soyad | 2-120 karakter, boş değil, kontrol karakterleri temizlenir |
| eposta | FILTER_VALIDATE_EMAIL, 180 karakter sınırı |
| konu | 3-180 karakter |
| mesaj | 10-5000 karakter |
| baslik | 3-220 karakter |
| slug | [a-z0-9]+(-[a-z0-9]+)*, 3-220 karakter, benzersiz |
| id | pozitif tam sayı |
| parola | min 12 karakter, büyük/küçük harf, rakam, özel karakter |
| url | FILTER_VALIDATE_URL + http/https şema kontrolü |

- Başarısız doğrulama: normal istekte hata mesajları formla birlikte gösterilir; AJAX'ta 422 Unprocessable Entity + hata alanı döner.
- GET parametreleri yalnızca okuma amaçlı kullanılır; doğrudan SQL'e girmez.

### 9.11 Hata Yönetimi ve Bilgi Sızdırmama

- Production'da display_errors = Off, log_errors = On.
- Yakalanmayan istisnalar yakalanır, 500 sayfası gösterilir; istemciye yığın izi veya SQL hata metni dönmez.
- PHP hataları error_log() ile uygulama loguna yazılır (storage/logs).
- 404, 403, 405, 429, 500 için özel görünümler hazırdır.

## 10. Uygulama Notları

### 10.1 Yardımcı Fonksiyonlar (app/helpers/fonksiyonlar.php)

```php
function e(mixed $deger): string;          // htmlspecialchars ENT_QUOTES UTF-8
function url(string $yol = ''): string;    // APP_URL tabanlı tam URL
function asset(string $dosya): string;     // public/assets/... URL
function csrf_field(): string;             // gizli CSRF input
function csrf_token(): string;             // token değeri
function config(string $anahtar, mixed $varsayilan = null): mixed;
function eski(string $anahtar, string $varsayilan = ''): string; // form eski değeri
```

### 10.2 İsimlendirme ve Kod Stili

- Sınıf adları Türkçe terminolojiye uygun ve StudlyCase.
- Dosya adları sınıf adıyla aynı.
- Metotlar camelCase.
- PHP dosyaları sonunda kapanış ?> etiketi kullanılmaz (gereksiz boşluk/çıktı riski).
- Her model, ilgili tabloya karşılık gelir; tablo adları ve sütunlar sorgularda doğrudan yazılır (değişmez), kullanıcı girdisiyle birleştirilmez.

### 10.3 Markdown ve Kod Blokları

- Makale/proje içeriği Markdown olarak saklanır.
- Sunucu tarafı basit bir Markdown işlemcisi (kendi yazılan, bağımlılıksız) ortak biçimleri destekler: başlıklar, kalın/italik, listeler, tablo, link, resim, satır içi kod ve ayraçlı kod blokları.
- Kod bloklarına dil sınıfı (language-php vb.) eklenir; istemcide highlight.js renklendirir.
- Her kod bloğu yanına erişilebilir kopyala butonu eklenir; kopyalama navigator.clipboard.writeText() ile yapılır (HTTPS gerektirir; HTTP'de execCommand('copy') geri dönüşüyle çalışır).

### 10.4 View/Layout

- Genel site tek layout (main.php), admin panel ayrı layout (admin.php) kullanır.
- Gorunum::render($sablon, $veri) görünümü layouts/main.php içine gömer.
- Görünümde veri, extract() yerine $veri dizisi üzerinden güvenli erişimle kullanılır; $this (Gorunum) yardımcılarına erişim sağlar.
- Partial'lar tekrar eden parçaları ayırır (proje kartı, makale kartı, sayfalama).

### 10.5 Cache Stratejisi (ilk sürüm minimal)

- Statik varlıklar için Cache-Control: public, max-age=86400 önerilir; dosya adı içerik hash'i taşıyorsa immutable eklenebilir.
- Rota/view cache ilk sürümde kapsam dışıdır; veritabanı indeksleri performansı yeterli kılar.
- Front controller ob_start() kullanarak yanlışlıkla oluşan erken çıktıyı yakalar.

---

## 11. Test Stratejisi

- Testler framework'süz, kendi tests/bootstrap.php dosyasıyla çalışır. Basit bir assert kütüphanesi veya --fail-fast tarzı CLI çalıştırıcısı yeterlidir.
- Birim testleri:
  - DogrulayiciTest: e-posta, slug, parola kuralları.
  - CsrfTest: token üretimi, doğrulama, hash_equals davranışı.
  - YonlendiriciTest: rota eşleşmesi, 404/405.
- Entegrasyon testleri (test veritabanı ile):
  - VeritabaniTest: PDO prepared statement çalışması.
  - IletisimAkisiTest: CSRF token olmadan 403; geçerli veriyle 200.
- Güvenlik testleri:
  - CSRF yok -> 403.
  - XSS denemesi (<script>) -> çıktıda kaçışlanmış görünür, yorumlanmaz.
  - SQL injection denemesi (' OR 1=1 --) -> parametreli sorgu sayesinde etkisiz.
  - Giriş brute-force: 5 hatalı deneme -> 429.
- Test veritabanı ayrı portfolyo_test olmalı; seed verileri ile çalıştırılır.

---

## 12. Dağıtım ve Kurulum Taslağı

### 12.1 Gereksinimler

- PHP 8.2+ (pdo_mysql, mbstring, fileinfo eklentileri zorunlu).
- MySQL 8.0+ / MariaDB 10.6+.
- Apache mod_rewrite veya Nginx.
- HTTPS (production zorunlu).

### 12.2 XAMPP / Yerel Kurulum

1. XAMPP kur; PHP 8.2 sürümü seç.
2. xampp/htdocs altına projeyi kopyala veya sembolik link oluştur.
3. Apache DocumentRoot'u proje-koku/public klasörüne yönlendir (httpd-vhosts.conf içinde).
4. xampp/mysql/bin ile MySQL başlat; database/schema.sql dosyasını phpMyAdmin veya mysql CLI ile içe aktar.
5. .env.example dosyasını .env olarak kopyala; DB bilgilerini gir, SESSION_SECURE=false, APP_DEBUG=true.
6. Terminalden php database/install.php çalıştır; admin kullanıcısını oluştur.
7. Tarayıcıda http://localhost aç; /admin/giris ile giriş yap.

### 12.3 Nginx Kurulumu

nginx.conf.example aşağıdaki gibi olmalıdır (özet):

```nginx
server {
    listen 80;
    server_name ornek.com;
    root /var/www/proje/public;
    index index.php;

    # Statik varlıklar
    location /assets/ {
        try_files $uri =404;
        expires 24h;
        add_header Cache-Control "public, max-age=86400";
    }

    # Front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
    }

    # Güvenlik: gizli dosyaları reddet
    location ~ /\. {
        deny all;
    }
}
```

### 12.4 Production Kontrol Listesi

- .env: APP_DEBUG=false, SESSION_SECURE=true.
- UYGULAMA_ANAHTARI üret ve .env'e yaz.
- database/install.php sil veya kabuk erişimini kapat.
- storage/ yazılabilir, app/ ve config/ salt okunur.
- HTTPS (TLS) zorunlu; HSTS başlığı opsiyonel.
- Veritabanı kullanıcısına minimum yetki ver (DROP/ALTER yok; gerekliyse ayrı yönetim hesabı).
- public/assets/vendor dosyalarını self-hosted olarak indir.

---

## 13. Açık Kararlar ve Gelecek Adımlar

- Markdown işlemcisi kendi kodumuzla sınırlı tutulacak; tablo ve dipnot desteği sonraki sürüme bırakılabilir.
- SMTP ile iletişim mesajlarını e-posta olarak iletme opsiyonu .env üzerinden açılabilir; ilk sürümde mesajlar yalnızca panelde saklanır.
- Statik içerik versiyonlama (cache busting) için varlık adlarına içerik hash eklenmesi planlıdır.
- Oturum depolama için varsayılan dosya tabanlı oturum yeterlidir; çok sunuculu yapıya geçilirse Redis önerilir.

---

## 14. Onay Gerektiren Kararlar

1. Collation: utf8mb4_turkish_ci yerine utf8mb4_unicode_ci kullanıldı (taşınabilirlik). Onay gerekli mi?
2. Admin içerikleri Markdown olarak saklanacak ve sunucuda sanitize edilecek. Editör olarak zengin metin (WYSIWYG) talep edilirse risk ve CSP etkisi değerlendirilmelidir.
3. İletişim formu yalnızca veritabanına yazar; e-posta gönderimi opsiyoneldir.
4. SESSION_SECURE varsayılanı production'da true; yerel HTTP testi için false yapılmalıdır.

