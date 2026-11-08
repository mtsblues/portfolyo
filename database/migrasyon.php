<?php
declare(strict_types=1);

/**
 * Şema geçişleri (idempotent): mevcut bir kurulumu veri kaybı olmadan yeni sürüme taşır.
 * `php database/install.php` her çalıştığında schema.sql'den sonra bu dosya uygulanır.
 * Yeni bir sütun gerektiğinde buraya `sutun_ekle(...)` satırı eklenir; tekrar çalıştırmak güvenlidir.
 */

/** Sütun yoksa ekler (MySQL 8 ve MariaDB uyumlu; IF NOT EXISTS gerektirmez). */
function mig_sutun_ekle(PDO $pdo, string $db, string $tablo, string $sutun, string $tanim): bool
{
    $s = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $s->execute([$db, $tablo, $sutun]);
    if ($s->fetchColumn() !== false) {
        return false;
    }
    // Tablo/sütun adları sabit literal'lerdir (kullanıcı girdisi değil).
    $pdo->exec('ALTER TABLE `' . $tablo . '` ADD COLUMN `' . $sutun . '` ' . $tanim);
    return true;
}

/**
 * @return list<string> uygulanan değişikliklerin açıklamaları
 */
function migrasyonlari_uygula(PDO $pdo, string $db, string $yuklemeDizini): array
{
    $log = [];

    // v1.1 — iki adımlı doğrulama (TOTP)
    $sutunlar = [
        'totp_gizli'    => 'VARCHAR(255) NULL DEFAULT NULL',
        'totp_aktif'    => 'TINYINT(1) NOT NULL DEFAULT 0',
        'totp_son_adim' => 'BIGINT NULL DEFAULT NULL',
        'yedek_kodlar'  => 'TEXT NULL DEFAULT NULL',
    ];
    foreach ($sutunlar as $ad => $tanim) {
        if (mig_sutun_ekle($pdo, $db, 'kullanicilar', $ad, $tanim)) {
            $log[] = "kullanicilar.{$ad} eklendi";
        }
    }

    // v1.1 — yükleme dizinindeki eski dosyaları medya kütüphanesine kaydet (geriye dönük)
    if (is_dir($yuklemeDizini)) {
        $ekle = $pdo->prepare(
            'INSERT IGNORE INTO medyalar (dosya, tur, mime, boyut, genislik, yukseklik) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $mimeler = [
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ];
        $sayi = 0;
        foreach (scandir($yuklemeDizini) ?: [] as $dosya) {
            if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm)$/', $dosya, $m) !== 1) {
                continue;
            }
            $yol = $yuklemeDizini . '/' . $dosya;
            $video = in_array($m[1], ['mp4', 'webm'], true);
            $b = $video ? false : @getimagesize($yol);
            $ekle->execute([
                $dosya,
                $video ? 'video' : 'gorsel',
                $mimeler[$m[1]],
                (int) filesize($yol),
                $b !== false ? (int) $b[0] : null,
                $b !== false ? (int) $b[1] : null,
            ]);
            $sayi += $ekle->rowCount();
        }
        if ($sayi > 0) {
            $log[] = "{$sayi} eski yükleme medya kütüphanesine kaydedildi";
        }
    }

    return $log;
}
