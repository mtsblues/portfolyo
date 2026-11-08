SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- Kişisel Portfolyo · Blog · Bilgi Kütüphanesi — MySQL/MariaDB Şeması
-- Model katmanıyla birebir uyumludur (app/Models/*). UTC zaman dilimi kullanır.
-- Kurulum: php database/install.php  (veya phpMyAdmin ile içe aktar)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `kullanicilar` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ad_soyad`     VARCHAR(120) NOT NULL,
    `eposta`       VARCHAR(180) NOT NULL UNIQUE,
    `parola_hash`  VARCHAR(255) NOT NULL,
    `rol`          ENUM('admin','editor') NOT NULL DEFAULT 'admin',
    `aktif`        TINYINT(1) NOT NULL DEFAULT 1,
    `son_giris`    DATETIME NULL,
    `olusturulma`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kategoriler` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ad`          VARCHAR(120) NOT NULL,
    `slug`        VARCHAR(220) NOT NULL UNIQUE,
    `aciklama`    VARCHAR(500) NULL,
    `olusturulma` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `makaleler` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kategori_id`      INT UNSIGNED NOT NULL,
    `kullanici_id`     INT UNSIGNED NOT NULL,
    `baslik`           VARCHAR(255) NOT NULL,
    `slug`             VARCHAR(220) NOT NULL UNIQUE,
    `ozet`             VARCHAR(500) NULL,
    `icerik`           LONGTEXT NOT NULL,
    `durum`            ENUM('taslak','yayinda','arsiv') NOT NULL DEFAULT 'taslak',
    `one_cikan`        TINYINT(1) NOT NULL DEFAULT 0,
    `okunma_suresi`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `kapak_gorsel`     VARCHAR(255) NULL,
    `anahtar_kelimeler` VARCHAR(300) NULL,
    `yayin_tarihi`     DATETIME NULL,
    `guncellenme`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `olusturulma`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_makale_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_makale_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar`(`id`) ON DELETE RESTRICT,
    INDEX `idx_durum_yayin` (`durum`, `yayin_tarihi`),
    INDEX `idx_kategori` (`kategori_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `etiketler` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ad`          VARCHAR(80) NOT NULL,
    `slug`        VARCHAR(220) NOT NULL UNIQUE,
    `olusturulma` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ad` (`ad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `makale_etiket` (
    `makale_id` INT UNSIGNED NOT NULL,
    `etiket_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`makale_id`, `etiket_id`),
    CONSTRAINT `fk_me_makale` FOREIGN KEY (`makale_id`) REFERENCES `makaleler`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_me_etiket` FOREIGN KEY (`etiket_id`) REFERENCES `etiketler`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `projeler` (
    `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `baslik`             VARCHAR(255) NOT NULL,
    `slug`               VARCHAR(220) NOT NULL UNIQUE,
    `aciklama`           VARCHAR(500) NOT NULL,
    `icerik`             LONGTEXT NOT NULL,
    `gorsel`             VARCHAR(255) NULL,
    `proje_url`          VARCHAR(255) NULL,
    `github_url`         VARCHAR(255) NULL,
    `one_cikan`          TINYINT(1) NOT NULL DEFAULT 0,
    `durum`              ENUM('aktif','devam_ediyor','tamamlandi','arsiv') NOT NULL DEFAULT 'aktif',
    `sira`               INT NOT NULL DEFAULT 0,
    `tamamlanma_tarihi`  DATE NULL,
    `guncellenme`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `olusturulma`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_durum_sira` (`durum`, `sira`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mesajlar` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ad_soyad`    VARCHAR(120) NOT NULL,
    `eposta`      VARCHAR(180) NOT NULL,
    `konu`        VARCHAR(180) NOT NULL,
    `mesaj`       TEXT NOT NULL,
    `ip_adresi`   VARBINARY(16) NOT NULL,
    `okundu`      TINYINT(1) NOT NULL DEFAULT 0,
    `olusturulma` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_okundu_id` (`okundu`, `id`),
    INDEX `idx_ip_zaman` (`ip_adresi`, `olusturulma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `giris_denemeleri` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kimlik`       VARCHAR(190) NOT NULL,
    `ip_adresi`    VARBINARY(16) NOT NULL,
    `basarili`     TINYINT(1) NOT NULL DEFAULT 0,
    `deneme_zamani` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_kimlik_ip_zaman` (`kimlik`, `ip_adresi`, `deneme_zamani`),
    INDEX `idx_ip_zaman` (`ip_adresi`, `deneme_zamani`),
    INDEX `idx_zaman` (`deneme_zamani`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ayarlar` (
    `anahtar` VARCHAR(190) PRIMARY KEY,
    `deger`   TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medya kütüphanesi: yüklenen görsel ve videolar (dosyalar storage/yuklemeler/ altında, web root dışında)
CREATE TABLE IF NOT EXISTS `medyalar` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `dosya`        VARCHAR(64) NOT NULL,
    `tur`          ENUM('gorsel','video') NOT NULL DEFAULT 'gorsel',
    `mime`         VARCHAR(60) NOT NULL,
    `boyut`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `genislik`     INT UNSIGNED NULL,
    `yukseklik`    INT UNSIGNED NULL,
    `alt_metin`    VARCHAR(255) NULL,
    `kullanici_id` BIGINT UNSIGNED NULL,
    `olusturulma`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_medya_dosya` (`dosya`),
    INDEX `idx_medya_tur` (`tur`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan ayarlar (admin panelinden düzenlenebilir). Mevcut değerlerin üzerine YAZILMAZ.
INSERT IGNORE INTO `ayarlar` (`anahtar`, `deger`) VALUES
    ('site_baslik', 'Portfolyom'),
    ('site_aciklama', 'Projelerim, yazılarım ve çözüm arşivim.'),
    ('site_eposta', 'iletisim@ornek.com'),
    ('anahtar_kelimeler', 'portfolyo, blog, yazılım'),
    ('iletisim_mesaj_ucret_siniri', '20'),
    ('iletisim_bildirim', '1'),
    ('hero_rozet', 'Portfolyo · Blog · Bilgi kütüphanesi'),
    ('hero_baslik_bas', 'Merhaba, ben'),
    ('hero_baslik_vurgu', 'adınızı yazın'),
    ('hero_baslik_son', ''),
    ('hero_aciklama', 'Geliştirdiğim projeleri ve karşılaştığım sorunların çözümlerini burada paylaşıyorum.'),
    ('hakkimda_foto', ''),
    ('hakkimda_icerik', ''),
    ('sosyal_github', ''),
    ('sosyal_linkedin', ''),
    ('sosyal_x', ''),
    ('sosyal_youtube', ''),
    ('sosyal_instagram', ''),
    ('site_logo', ''),
    ('og_gorsel', ''),
    ('altbilgi_metin', '');

SET FOREIGN_KEY_CHECKS = 1;
