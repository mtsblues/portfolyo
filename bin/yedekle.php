<?php
declare(strict_types=1);

/**
 * Yedek alma — saf PHP, mysqldump gerektirmez. Web panelindeki "Yedekleme" ile aynı motoru kullanır.
 *
 *   php bin/yedekle.php                  Veritabanı yedeği (.sql.gz) → storage/yedekler/
 *   php bin/yedekle.php --medya          Ek olarak yüklenen medyayı (.tar.gz) yedekler
 *   php bin/yedekle.php --sakla=30       En yeni 30 yedeği tutar (varsayılan: YEDEK_SAKLA, 14)
 *   php bin/yedekle.php --dis            Ayarlı dış hedeflere (e-posta/FTP/WebDAV) da gönderir
 *
 * Geri yükleme: php bin/geri-yukle.php <dosya>   (önce kuru çalıştırma ile doğrular)
 * Zamanlama: docs/KURULUM.md → "Yedekleme".
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

$secenek = [];
foreach (array_slice($argv ?? [], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m) !== 1) {
        fwrite(STDERR, "HATA: Bilinmeyen argüman: {$a}\n");
        exit(1);
    }
    $secenek[$m[1]] = $m[2] ?? true;
}
if (isset($secenek['yuklemeler'])) { // eski adı (v1.2 ve öncesi): --medya ile aynı
    $secenek['medya'] = true;
}

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');
$sakla = isset($secenek['sakla']) ? max(1, (int) $secenek['sakla']) : (int) config('yedek.sakla', 14);

try {
    $sql = Yedek::sqlAl();
    echo '✓ Veritabanı yedeği: ' . $sql . ' (' . number_format((int) filesize($sql) / 1024, 1, ',', '.') . " KB)\n";
    $dosyalar = [$sql];
    if (isset($secenek['medya'])) {
        $medya = Yedek::medyaAl();
        echo $medya !== null ? '✓ Medya yedeği: ' . $medya . "\n" : "• Yüklenmiş medya yok, atlandı.\n";
        if ($medya !== null) {
            $dosyalar[] = $medya;
        }
    }
    if (isset($secenek['dis'])) {
        $hedefler = Yedek::hedefler();
        if ($hedefler === []) {
            echo "! Dış hedef yapılandırılmamış (.env: SMTP / YEDEK_FTP_* / YEDEK_WEBDAV_*).\n";
        }
        foreach ($dosyalar as $d) {
            $uygun = str_contains(basename($d), 'medya-') ? array_values(array_intersect($hedefler, ['ftp', 'webdav'])) : $hedefler;
            foreach ($uygun === [] ? [] : Yedek::disaGonder($d, $uygun) as $ad => $sonuc) {
                echo '  ' . (($sonuc['ok'] ?? false) ? '✓ ' : '✗ ') . $ad . ': ' . ($sonuc['mesaj'] ?? '') . "\n";
            }
        }
    }
    Yedek::dondur($sakla);
    Denetim::kaydet('yedek.alindi', basename($sql), 'komut satırı');
} catch (Throwable $t) {
    fwrite(STDERR, 'HATA: ' . $t->getMessage() . "\n");
    exit(1);
}
