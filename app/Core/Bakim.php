<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Ayar;
use Throwable;

/**
 * Bakım görevleri: günlük döndürme, eski kayıt temizliği, mesaj saklama süresi, otomatik yedek.
 * Tetikleyiciler: yönetici panele girişi (günde en çok bir kez), `php bin/bakim.php` (cron) veya
 * web-cron adresi (/bakim/{BAKIM_TOKEN}); böylece cron/SSH olmayan hostinglerde de çalışır.
 */
final class Bakim
{
    private const ARALIK_SN = 20 * 3600;

    private static function durumDosyasi(): string
    {
        return KOK . '/storage/cache/bakim-son.txt';
    }

    public static function sonCalisma(): int
    {
        return is_file(self::durumDosyasi()) ? (int) @file_get_contents(self::durumDosyasi()) : 0;
    }

    /** Zamanı geldiyse çalıştırır (en çok günde bir). Hata vermez. */
    public static function gerekirse(): void
    {
        if (time() - self::sonCalisma() < self::ARALIK_SN) {
            return;
        }
        try {
            self::calistir();
        } catch (Throwable $t) {
            gunluk('Bakım hatası: ' . $t->getMessage());
        }
    }

    /**
     * @return array<string,mixed> yapılan işlerin özeti
     */
    public static function calistir(): array
    {
        $dizin = KOK . '/storage/cache';
        if (!is_dir($dizin)) {
            @mkdir($dizin, 0750, true);
        }
        // Eşzamanlı çalışmayı engelle
        $kilit = @fopen($dizin . '/bakim.kilit', 'c');
        if ($kilit === false || !flock($kilit, LOCK_EX | LOCK_NB)) {
            return ['atlandi' => 'başka bir bakım çalışıyor'];
        }
        $ozet = [];
        try {
            @file_put_contents(self::durumDosyasi(), (string) time());
            $ozet['gunluk_dondurulen'] = self::gunlukleriDondur();
            $ozet['hiz_dosyalari'] = self::eskiDosyalariSil(KOK . '/storage/cache/hiz', 86400, '*.json');
            $ozet['oturum_dosyalari'] = self::eskiDosyalariSil(KOK . '/storage/oturum', 3 * 86400, 'sess_*');
            $ozet['veritabani'] = self::veritabaniTemizligi();
            try {
                $y = Yedek::otomatik();
                $ozet['yedek'] = $y['calisti'] ? ($y['sql'] ?? 'alındı') : (string) $y['neden'];
            } catch (Throwable $t) {
                gunluk('Otomatik yedek hatası: ' . $t->getMessage());
                $ozet['yedek'] = 'hata: ' . $t->getMessage();
            }
            Denetim::kaydet('bakim.calisti', null, mb_substr((string) json_encode($ozet, JSON_UNESCAPED_UNICODE), 0, 480, 'UTF-8'), 0);
        } finally {
            flock($kilit, LOCK_UN);
            fclose($kilit);
        }
        return $ozet;
    }

    /** 2 MB'ı aşan günlükleri tarih damgalı dosyaya taşır; en yeni 5 arşivi tutar. */
    private static function gunlukleriDondur(): int
    {
        $n = 0;
        $dizin = KOK . '/storage/logs';
        foreach (['uygulama', 'php-hata'] as $ad) {
            $dosya = $dizin . '/' . $ad . '.log';
            if (is_file($dosya) && filesize($dosya) > 2 * 1048576) {
                @rename($dosya, $dizin . '/' . $ad . '-' . date('Ymd-His') . '.log');
                $n++;
            }
            $arsivler = glob($dizin . '/' . $ad . '-*.log') ?: [];
            rsort($arsivler);
            foreach (array_slice($arsivler, 5) as $eski) {
                @unlink($eski);
            }
        }
        return $n;
    }

    private static function eskiDosyalariSil(string $dizin, int $saniye, string $desen): int
    {
        $n = 0;
        $esik = time() - $saniye;
        foreach (glob($dizin . '/' . $desen) ?: [] as $d) {
            if (is_file($d) && (int) filemtime($d) < $esik && @unlink($d)) {
                $n++;
            }
        }
        return $n;
    }

    /** @return array<string,int> */
    private static function veritabaniTemizligi(): array
    {
        $o = [];
        $o['giris_denemeleri'] = Veritabani::calistir('DELETE FROM giris_denemeleri WHERE deneme_zamani < (UTC_TIMESTAMP() - INTERVAL 2 DAY)');
        $o['sifirlama'] = Veritabani::calistir('DELETE FROM sifre_sifirlama WHERE son_kullanma < (UTC_TIMESTAMP() - INTERVAL 1 DAY) OR (kullanildi = 1 AND olusturulma < (UTC_TIMESTAMP() - INTERVAL 1 DAY))');
        $mutlak = max(1, (int) config('oturum.mutlak_dk', 480));
        $o['oturumlar'] = Veritabani::calistir('DELETE FROM oturumlar WHERE son_aktivite < (UTC_TIMESTAMP() - INTERVAL ? MINUTE) OR (iptal = 1 AND son_aktivite < (UTC_TIMESTAMP() - INTERVAL 1 DAY))', [$mutlak * 2]);
        $o['denetim'] = Denetim::temizle(365);
        $gun = (int) Ayar::al('mesaj_saklama_gun', '0');
        $o['eski_mesaj'] = $gun > 0
            ? Veritabani::calistir('DELETE FROM mesajlar WHERE olusturulma < (UTC_TIMESTAMP() - INTERVAL ? DAY)', [$gun])
            : 0;
        return $o;
    }
}
