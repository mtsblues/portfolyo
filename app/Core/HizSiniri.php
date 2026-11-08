<?php
declare(strict_types=1);

namespace App\Core;

/** Dosya tabanlı kayan pencere hız sınırlayıcı (veritabanından bağımsız, flock ile atomik). */
final class HizSiniri
{
    /**
     * @return bool true: istek izinli ve sayıldı, false: limit aşıldı.
     */
    public static function izinVer(string $anahtar, int $maks, int $saniye): bool
    {
        $dizin = KOK . '/storage/cache/hiz';
        if (!is_dir($dizin) && !@mkdir($dizin, 0750, true) && !is_dir($dizin)) {
            gunluk('HizSiniri: dizin oluşturulamadı');
            return true;
        }
        $dosya = $dizin . '/' . hash('sha256', $anahtar) . '.json';
        $fp = @fopen($dosya, 'c+');
        if ($fp === false) {
            gunluk('HizSiniri: dosya açılamadı');
            return true;
        }
        $simdi = time();
        try {
            if (!flock($fp, LOCK_EX)) {
                return true;
            }
            $icerik = stream_get_contents($fp);
            $liste = is_string($icerik) && $icerik !== '' ? json_decode($icerik, true) : [];
            $liste = is_array($liste) ? $liste : [];
            $liste = array_values(array_filter($liste, static fn($t): bool => is_int($t) && $t > $simdi - $saniye));

            if (count($liste) >= $maks) {
                flock($fp, LOCK_UN);
                return false;
            }
            $liste[] = $simdi;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($liste));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        if (random_int(1, 100) === 1) {
            self::temizle($dizin);
        }
        return true;
    }

    private static function temizle(string $dizin): void
    {
        foreach (glob($dizin . '/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
