<?php
declare(strict_types=1);

/**
 * Düzenli bakım: günlük döndürme, eski kayıt/oturum/önbellek temizliği, saklama süresi, otomatik yedek.
 * Önerilen zamanlama (günde bir): 0 3 * * *  php /yol/bin/bakim.php
 * Cron yoksa panel açıldığında ~20 saatte bir kendiliğinden çalışır; ya da /bakim/{BAKIM_TOKEN} adresi.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Bakim;
use App\Core\Yapilandirma;

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');

try {
    $ozet = Bakim::calistir();
} catch (Throwable $t) {
    fwrite(STDERR, 'HATA: ' . $t->getMessage() . "\n");
    exit(1);
}
foreach ($ozet as $anahtar => $deger) {
    echo '  • ' . $anahtar . ': ' . (is_scalar($deger) ? (string) $deger : json_encode($deger, JSON_UNESCAPED_UNICODE)) . "\n";
}
echo "✓ Bakım tamamlandı.\n";
