<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Veritabani;

/**
 * Aktif yönetici oturumlarının izlenmesi (uzaktan sonlandırma için).
 * Oturum kimliği (PHPSESSID) saklanmaz: girişte üretilen ayrı, rastgele bir izleme anahtarının SHA-256 özeti tutulur;
 * böylece veritabanı sızsa bile oturum çalınamaz ve oturum kimliği yenilense bile izleme sürer.
 */
final class OturumKaydi
{
    private const DOKUNMA_ARALIGI = 60;

    private static function ozet(string $anahtar): string
    {
        return hash('sha256', $anahtar);
    }

    /** Girişte çağrılır. */
    public static function baslat(int $kullaniciId): void
    {
        $anahtar = bin2hex(random_bytes(24));
        $_SESSION['_izleme'] = $anahtar;
        $_SESSION['_dokunus'] = time();
        Veritabani::calistir(
            'INSERT INTO oturumlar (anahtar_ozeti, kullanici_id, ip_adresi, tarayici) VALUES (?, ?, ?, ?)',
            [self::ozet($anahtar), $kullaniciId, ip_sakla_ikili(istemci_ip()), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8')]
        );
    }

    /** Geçerli oturumun özeti (yoksa null). */
    public static function mevcutOzet(): ?string
    {
        $a = $_SESSION['_izleme'] ?? null;
        return is_string($a) && $a !== '' ? self::ozet($a) : null;
    }

    /**
     * Oturum hâlâ geçerli mi? İptal edilmişse false. İzleme anahtarı olmayan (özellik öncesi) oturumlar benimsenir.
     */
    public static function gecerliMi(int $kullaniciId): bool
    {
        $ozet = self::mevcutOzet();
        if ($ozet === null) {
            self::baslat($kullaniciId); // eski oturumu izlemeye al
            return true;
        }
        $iptal = Veritabani::skaler('SELECT iptal FROM oturumlar WHERE anahtar_ozeti = ? AND kullanici_id = ?', [$ozet, $kullaniciId]);
        if ($iptal === null) { // kayıt yok (temizlenmiş/geri yüklenmiş veritabanı): yeniden kaydet
            Veritabani::calistir(
                'INSERT IGNORE INTO oturumlar (anahtar_ozeti, kullanici_id, ip_adresi, tarayici) VALUES (?, ?, ?, ?)',
                [$ozet, $kullaniciId, ip_sakla_ikili(istemci_ip()), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8')]
            );
            return true;
        }
        if ((int) $iptal === 1) {
            return false;
        }
        if (time() - (int) ($_SESSION['_dokunus'] ?? 0) >= self::DOKUNMA_ARALIGI) {
            $_SESSION['_dokunus'] = time();
            Veritabani::calistir('UPDATE oturumlar SET son_aktivite = UTC_TIMESTAMP() WHERE anahtar_ozeti = ?', [$ozet]);
        }
        return true;
    }

    /** Çıkışta geçerli oturumu kapatır. */
    public static function bitir(): void
    {
        $ozet = self::mevcutOzet();
        if ($ozet !== null) {
            Veritabani::calistir('UPDATE oturumlar SET iptal = 1 WHERE anahtar_ozeti = ?', [$ozet]);
        }
    }

    /** @return list<array<string,mixed>> kullanıcının aktif oturumları */
    public static function liste(int $kullaniciId): array
    {
        return Veritabani::hepsi(
            'SELECT anahtar_ozeti, ip_adresi, tarayici, olusturulma, son_aktivite FROM oturumlar
             WHERE kullanici_id = ? AND iptal = 0 ORDER BY son_aktivite DESC',
            [$kullaniciId]
        );
    }

    public static function kapat(string $ozet, int $kullaniciId): bool
    {
        return Veritabani::calistir('UPDATE oturumlar SET iptal = 1 WHERE anahtar_ozeti = ? AND kullanici_id = ? AND iptal = 0', [$ozet, $kullaniciId]) === 1;
    }

    /** Geçerli oturum dışındaki tüm oturumları kapatır. @return int kapatılan sayısı */
    public static function digerleriniKapat(int $kullaniciId): int
    {
        return Veritabani::calistir(
            'UPDATE oturumlar SET iptal = 1 WHERE kullanici_id = ? AND iptal = 0 AND anahtar_ozeti <> ?',
            [$kullaniciId, self::mevcutOzet() ?? '']
        );
    }

    /** Kullanıcının TÜM oturumlarını kapatır (parola sıfırlama/değişiminden sonra). */
    public static function tumunuKapat(int $kullaniciId): int
    {
        return Veritabani::calistir('UPDATE oturumlar SET iptal = 1 WHERE kullanici_id = ? AND iptal = 0', [$kullaniciId]);
    }

    /** "Chrome · Windows" gibi kısa tarayıcı/işletim sistemi etiketi. */
    public static function tarayiciEtiketi(string $ua): string
    {
        $tarayici = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'Bilinmeyen',
            default => 'Diğer',
        };
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        return $os !== '' ? $tarayici . ' · ' . $os : $tarayici;
    }
}
