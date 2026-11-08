<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Parola sıfırlama jetonları: rastgele 256 bit, veritabanında yalnızca SHA-256 özeti, 60 dakika geçerli, tek kullanımlık.
 * Yeni jeton, kullanıcının önceki bekleyen jetonlarını geçersiz kılar.
 */
final class SifreSifirlama
{
    public const OMUR_DK = 60;

    /** @return string e-postayla gönderilecek ham jeton (64 hex) */
    public static function olustur(int $kullaniciId): string
    {
        $jeton = bin2hex(random_bytes(32));
        Veritabani::calistir('UPDATE sifre_sifirlama SET kullanildi = 1 WHERE kullanici_id = ? AND kullanildi = 0', [$kullaniciId]);
        Veritabani::calistir(
            'INSERT INTO sifre_sifirlama (kullanici_id, token_ozeti, son_kullanma, ip_adresi)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ' . self::OMUR_DK . ' MINUTE), ?)',
            [$kullaniciId, hash('sha256', $jeton), ip_sakla_ikili(istemci_ip())]
        );
        return $jeton;
    }

    /** @return array{id:int,kullanici_id:int,eposta:string,ad_soyad:string}|null jeton geçerliyse */
    public static function dogrula(string $jeton): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $jeton) !== 1) {
            return null;
        }
        $s = Veritabani::tek(
            'SELECT s.id, s.kullanici_id, u.eposta, u.ad_soyad
             FROM sifre_sifirlama s JOIN kullanicilar u ON u.id = s.kullanici_id
             WHERE s.token_ozeti = ? AND s.kullanildi = 0 AND s.son_kullanma > UTC_TIMESTAMP() AND u.aktif = 1 LIMIT 1',
            [hash('sha256', $jeton)]
        );
        return $s === null ? null : ['id' => (int) $s['id'], 'kullanici_id' => (int) $s['kullanici_id'], 'eposta' => (string) $s['eposta'], 'ad_soyad' => (string) $s['ad_soyad']];
    }

    /** Jetonu tüketir. false: başka bir istek önce tüketti (yarış). */
    public static function tuket(int $id): bool
    {
        return Veritabani::calistir('UPDATE sifre_sifirlama SET kullanildi = 1 WHERE id = ? AND kullanildi = 0', [$id]) === 1;
    }
}
