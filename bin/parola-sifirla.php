<?php
declare(strict_types=1);

/**
 * Sunucudan yönetici parolasını sıfırlar (e-posta ayarı yoksa kurtarma yolu).
 *
 *   php bin/parola-sifirla.php eposta@ornek.com
 *
 * Yeni parola komut satırına YAZILMAZ (kabuk geçmişine girmesin): rastgele üretilir ve bir kez gösterilir.
 * Tüm açık oturumlar kapatılır; giriş denemesi kilidi kaldırılır. 2FA açıksa o da devam eder.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalıştırılabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Denetim;
use App\Core\Veritabani;
use App\Core\Yapilandirma;
use App\Models\Kullanici;
use App\Models\OturumKaydi;

Yapilandirma::yukle(KOK . '/.env');
Yapilandirma::configAyarla(require KOK . '/config/config.php');

$eposta = mb_strtolower(trim((string) ($argv[1] ?? '')), 'UTF-8');
if ($eposta === '') {
    fwrite(STDERR, "Kullanım: php bin/parola-sifirla.php eposta@ornek.com\n");
    exit(1);
}
try {
    $k = Kullanici::epostaIle($eposta);
    if ($k === null) {
        fwrite(STDERR, "HATA: Bu e-postayla kullanıcı yok.\n");
        exit(1);
    }
    // Her sınıftan en az bir karakter + rastgele gövde (Dogrulayici kurallarını sağlar)
    $set = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#$%&*+-=?@'];
    $parola = '';
    foreach ($set as $s) {
        $parola .= $s[random_int(0, strlen($s) - 1)];
    }
    $hepsi = implode('', $set);
    for ($i = 0; $i < 14; $i++) {
        $parola .= $hepsi[random_int(0, strlen($hepsi) - 1)];
    }
    $parola = str_shuffle($parola);
    Kullanici::parolaGuncelle((int) $k['id'], $parola);
    Veritabani::calistir('UPDATE kullanicilar SET aktif = 1 WHERE id = ?', [(int) $k['id']]);
    Veritabani::calistir('DELETE FROM giris_denemeleri WHERE kimlik IN (?, ?)', [$eposta, '2fa:' . (int) $k['id']]);
    OturumKaydi::tumunuKapat((int) $k['id']);
    Denetim::kaydet('parola.sifirlandi', $eposta, 'komut satırı', (int) $k['id']);
} catch (Throwable $t) {
    fwrite(STDERR, 'HATA: ' . $t->getMessage() . "\n");
    exit(1);
}
echo "✓ Parola sıfırlandı: {$eposta}\n  Geçici parola (şimdi not edin, tekrar gösterilmez): {$parola}\n  Girişten sonra Profil sayfasından değiştirin.\n";
