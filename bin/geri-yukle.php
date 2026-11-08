<?php
declare(strict_types=1);

/**
 * Yedekten geri yükleme (komut satırı).
 *
 *   php bin/geri-yukle.php portfolyo-20260101-030000.sql.gz            Önce kuru çalıştırma, sonra onay ister
 *   php bin/geri-yukle.php portfolyo-....sql.gz --kuru                 Yalnızca doğrular, hiçbir şey değiştirmez
 *   php bin/geri-yukle.php portfolyo-....sql.gz --evet                 Onay sormadan uygular (otomasyon)
 *   php bin/geri-yukle.php medya-20260101-030000.tar.gz                Medya arşivini geri yükler
 *
 * Dosya adı storage/yedekler/ içinden okunur. Geri yüklemeden önce otomatik "geriyukleme-oncesi-*" yedeği alınır;
 * hata olursa eski veri geri konur.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Denetim;
use App\Core\Yapilandirma;
use App\Core\Yedek;

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');

$ad = '';
$kuru = false;
$evet = false;
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--kuru') {
        $kuru = true;
    } elseif ($a === '--evet') {
        $evet = true;
    } elseif ($ad === '' && !str_starts_with($a, '--')) {
        $ad = basename($a);
    } else {
        fwrite(STDERR, "HATA: Bilinmeyen argüman: {$a}\n");
        exit(1);
    }
}
$yol = $ad !== '' ? Yedek::yol($ad) : null;
if ($yol === null) {
    fwrite(STDERR, "Kullanım: php bin/geri-yukle.php <yedek-dosyası> [--kuru|--evet]\nMevcut yedekler:\n");
    foreach (Yedek::liste() as $y) {
        fwrite(STDERR, '  ' . $y['ad'] . "\n");
    }
    exit(1);
}

try {
    if (str_starts_with($ad, 'medya-') || str_ends_with($ad, '.tar.gz')) {
        if ($kuru) {
            echo "Medya arşivi bulundu: {$ad} (kuru çalıştırmada içerik doğrulanmaz)\n";
            exit(0);
        }
        $s = Yedek::medyaGeriYukle($yol);
        echo '✓ Medya geri yüklendi: ' . json_encode($s, JSON_UNESCAPED_UNICODE) . "\n";
        Denetim::kaydet('yedek.medya_geri_yuklendi', $ad, 'komut satırı');
        exit(0);
    }

    $d = Yedek::dogrula($yol);
    if (!$d['ok']) {
        fwrite(STDERR, "✗ Doğrulama başarısız:\n");
        foreach ((array) $d['hatalar'] as $h) {
            fwrite(STDERR, '  - ' . $h . "\n");
        }
        exit(2);
    }
    echo '✓ Doğrulandı: ' . count((array) $d['tablolar']) . ' tablo, ' . (int) $d['ifade'] . ' ifade, tarih: ' . ($d['tarih'] ?? '?') . ', şema: ' . ($d['sema'] ?? '?') . "\n";
    if ($kuru) {
        echo "Kuru çalıştırma: hiçbir şey değiştirilmedi.\n";
        exit(0);
    }
    if (!$evet) {
        echo "\nUYARI: Mevcut veritabanı içeriği bu yedekle DEĞİŞTİRİLECEK (öncesinde otomatik yedek alınır).\nDevam etmek için GERI YUKLE yazın: ";
        $cevap = trim((string) fgets(STDIN));
        if ($cevap !== 'GERI YUKLE') {
            echo "Vazgeçildi.\n";
            exit(0);
        }
    }
    $s = Yedek::geriYukle($yol);
    if (!$s['ok']) {
        fwrite(STDERR, '✗ ' . $s['mesaj'] . "\n");
        exit(1);
    }
    echo '✓ ' . $s['mesaj'] . ($s['oncesi'] ? ' (önceki durum: ' . $s['oncesi'] . ')' : '') . "\n";
    Denetim::kaydet('yedek.geri_yuklendi', $ad, 'komut satırı');
} catch (Throwable $t) {
    fwrite(STDERR, 'HATA: ' . $t->getMessage() . "\n");
    exit(1);
}
