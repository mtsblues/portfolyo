<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Denetim günlüğü: giriş olayları ve yönetici eylemleri (kim, ne, ne zaman, hangi IP'den).
 * Kayıt hatası asla asıl işlemi bozmaz (sessizce uygulama günlüğüne yazılır).
 */
final class Denetim
{
    public static function kaydet(string $eylem, ?string $hedef = null, ?string $ayrinti = null, ?int $kullaniciId = null): void
    {
        try {
            if ($kullaniciId === null && isset($_SESSION) && is_array($_SESSION['kullanici'] ?? null)) {
                $kullaniciId = (int) $_SESSION['kullanici']['id'];
            }
            Veritabani::calistir(
                'INSERT INTO denetim_gunlugu (kullanici_id, eylem, hedef, ayrinti, ip_adresi) VALUES (?, ?, ?, ?, ?)',
                [
                    $kullaniciId,
                    mb_substr($eylem, 0, 60, 'UTF-8'),
                    $hedef !== null ? mb_substr($hedef, 0, 190, 'UTF-8') : null,
                    $ayrinti !== null ? mb_substr($ayrinti, 0, 500, 'UTF-8') : null,
                    PHP_SAPI === 'cli' ? null : ip_sakla_ikili(istemci_ip()),
                ]
            );
        } catch (\Throwable $t) {
            gunluk('Denetim kaydı yazılamadı: ' . $t->getMessage());
        }
    }

    /** @return array{0:string,1:list<mixed>} */
    private static function kosul(?string $onek, ?int $kullaniciId): array
    {
        $where = ' WHERE 1=1';
        $p = [];
        if ($onek !== null && $onek !== '') {
            $where .= ' AND d.eylem LIKE ?';
            $p[] = addcslashes($onek, '%_\\') . '%';
        }
        if ($kullaniciId !== null) {
            $where .= ' AND d.kullanici_id = ?';
            $p[] = $kullaniciId;
        }
        return [$where, $p];
    }

    public static function say(?string $onek = null, ?int $kullaniciId = null): int
    {
        [$where, $p] = self::kosul($onek, $kullaniciId);
        return (int) Veritabani::skaler('SELECT COUNT(*) FROM denetim_gunlugu d' . $where, $p);
    }

    /** @return list<array<string,mixed>> */
    public static function liste(int $limit, int $offset, ?string $onek = null, ?int $kullaniciId = null): array
    {
        [$where, $p] = self::kosul($onek, $kullaniciId);
        array_push($p, $limit, $offset);
        return Veritabani::hepsi(
            'SELECT d.id, d.eylem, d.hedef, d.ayrinti, d.ip_adresi, d.olusturulma, u.ad_soyad, u.eposta
             FROM denetim_gunlugu d LEFT JOIN kullanicilar u ON u.id = d.kullanici_id' . $where
            . ' ORDER BY d.id DESC LIMIT ? OFFSET ?',
            $p
        );
    }

    /** Eski kayıtları siler. @return int silinen satır */
    public static function temizle(int $gun = 365): int
    {
        return Veritabani::calistir('DELETE FROM denetim_gunlugu WHERE olusturulma < (UTC_TIMESTAMP() - INTERVAL ? DAY)', [max(30, $gun)]);
    }

    /** Eylem koduna okunabilir Türkçe etiket. */
    public static function etiket(string $eylem): string
    {
        static $harita = [
            'giris.basarili' => 'Giriş yapıldı', 'giris.basarisiz' => 'Başarısız giriş', 'giris.2fa_hatali' => 'Hatalı doğrulama kodu',
            'cikis' => 'Çıkış yapıldı', 'parola.sifirlama_istendi' => 'Parola sıfırlama istendi', 'parola.sifirlandi' => 'Parola sıfırlandı',
            'parola.degisti' => 'Parola değiştirildi', '2fa.acildi' => '2FA açıldı', '2fa.kapandi' => '2FA kapatıldı',
            'oturum.kapatildi' => 'Oturum sonlandırıldı', 'yedek.alindi' => 'Yedek alındı', 'yedek.indirildi' => 'Yedek indirildi',
            'yedek.silindi' => 'Yedek silindi', 'yedek.geri_yuklendi' => 'Yedekten geri yüklendi', 'yedek.disa_gonderildi' => 'Yedek dışarı gönderildi',
            'yedek.yuklendi' => 'Yedek dosyası yüklendi', 'yedek.dis_gonderildi' => 'Yedek dışarı gönderildi',
            'yedek.medya_geri_yuklendi' => 'Medya geri yüklendi', 'yedek.geri_yukleme_basarisiz' => 'Geri yükleme başarısız',
            'yedek.geri_yukleme_reddedildi' => 'Geri yükleme reddedildi (kimlik doğrulama)', 'admin.islem' => 'Yönetici işlemi', 'bakim.calisti' => 'Bakım görevleri çalıştı',
        ];
        return $harita[$eylem] ?? $eylem;
    }
}
