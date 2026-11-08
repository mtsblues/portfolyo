<?php
declare(strict_types=1);

/**
 * SMTP ayarlarını sınar:  php bin/eposta-test.php [alici@adres.com]
 * Alıcı verilmezse MAIL_ALICI, o da boşsa panelde kayıtlı iletişim e-postası kullanılır.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Posta;
use App\Core\Yapilandirma;
use App\Models\Ayar;

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');

if (!Posta::yapilandirildi()) {
    fwrite(STDERR, "HATA: MAIL_HOST .env içinde ayarlı değil. config/.env.example dosyasındaki MAIL_* satırlarına bakın.\n");
    exit(1);
}
$alici = $argv[1] ?? ((string) config('posta.alici') !== '' ? (string) config('posta.alici') : Ayar::al('site_eposta'));
echo 'Gönderiliyor → ' . $alici . ' ... ';
$s = Posta::gonder($alici, 'Portfolyo SMTP testi', "Bu bir test iletisidir.\nSMTP ayarlarınız çalışıyor.\n");
if ($s['ok']) {
    echo "✓ gönderildi\n";
    exit(0);
}
echo "✗\n" . 'HATA: ' . $s['hata'] . "\n";
exit(1);
