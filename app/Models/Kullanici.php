<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Veritabani;

final class Kullanici extends Model
{
    protected const TABLO = 'kullanicilar';

    /** @return array<string,mixed>|null */
    public static function epostaIle(string $eposta): ?array
    {
        return Veritabani::tek('SELECT * FROM kullanicilar WHERE eposta = ? LIMIT 1', [$eposta]);
    }

    /** @return list<array<string,mixed>> */
    public static function tumu(): array
    {
        return Veritabani::hepsi('SELECT id, ad_soyad, eposta, rol, aktif, son_giris, olusturulma FROM kullanicilar ORDER BY id ASC');
    }

    public static function olustur(string $adSoyad, string $eposta, string $parola, string $rol = 'admin'): int
    {
        Veritabani::calistir(
            'INSERT INTO kullanicilar (ad_soyad, eposta, parola_hash, rol, aktif) VALUES (?, ?, ?, ?, 1)',
            [$adSoyad, $eposta, self::hashle($parola), $rol]
        );
        return Veritabani::sonId();
    }

    public static function hashle(string $parola): string
    {
        return password_hash($parola, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function parolaGuncelle(int $id, string $parola): void
    {
        Veritabani::calistir('UPDATE kullanicilar SET parola_hash = ? WHERE id = ?', [self::hashle($parola), $id]);
    }

    public static function hashYenile(int $id, string $hash): void
    {
        Veritabani::calistir('UPDATE kullanicilar SET parola_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public static function profilGuncelle(int $id, string $adSoyad, string $rol, bool $aktif): void
    {
        Veritabani::calistir(
            'UPDATE kullanicilar SET ad_soyad = ?, rol = ?, aktif = ? WHERE id = ?',
            [$adSoyad, $rol, $aktif ? 1 : 0, $id]
        );
    }

    public static function sonGirisGuncelle(int $id): void
    {
        Veritabani::calistir('UPDATE kullanicilar SET son_giris = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    // ---- İki adımlı doğrulama (TOTP) ----

    /** @return array{totp_aktif:int,totp_gizli:?string,totp_son_adim:?int,yedek_kodlar:?string}|null */
    public static function totpBilgi(int $id): ?array
    {
        return Veritabani::tek('SELECT totp_aktif, totp_gizli, totp_son_adim, yedek_kodlar FROM kullanicilar WHERE id = ? LIMIT 1', [$id]);
    }

    public static function ikiAdimAktifMi(int $id): bool
    {
        return (int) Veritabani::skaler('SELECT totp_aktif FROM kullanicilar WHERE id = ?', [$id]) === 1;
    }

    /** @param list<string> $kodOzetleri yedek kod özetleri */
    public static function totpEtkinlestir(int $id, string $sifreliGizli, array $kodOzetleri, int $adim): void
    {
        Veritabani::calistir(
            'UPDATE kullanicilar SET totp_gizli = ?, totp_aktif = 1, totp_son_adim = ?, yedek_kodlar = ? WHERE id = ?',
            [$sifreliGizli, $adim, json_encode(array_values($kodOzetleri)), $id]
        );
    }

    public static function totpKapat(int $id): void
    {
        Veritabani::calistir(
            'UPDATE kullanicilar SET totp_gizli = NULL, totp_aktif = 0, totp_son_adim = NULL, yedek_kodlar = NULL WHERE id = ?',
            [$id]
        );
    }

    /** Aynı zaman adımının ikinci kez kullanılmasını (tekrar saldırısı) atomik olarak engeller. */
    public static function totpAdimKaydet(int $id, int $adim): bool
    {
        return Veritabani::calistir(
            'UPDATE kullanicilar SET totp_son_adim = ? WHERE id = ? AND (totp_son_adim IS NULL OR totp_son_adim < ?)',
            [$adim, $id, $adim]
        ) === 1;
    }

    /** Yedek kodu tek kullanımlık tüketir; geçerliyse true. */
    public static function yedekKodTuket(int $id, string $ozet): bool
    {
        $mevcut = (string) Veritabani::skaler('SELECT yedek_kodlar FROM kullanicilar WHERE id = ?', [$id]);
        $liste = json_decode($mevcut, true);
        if (!is_array($liste)) {
            return false;
        }
        foreach ($liste as $i => $k) {
            if (is_string($k) && hash_equals($k, $ozet)) {
                unset($liste[$i]);
                // Eşzamanlı çift kullanımı önlemek için eski değere göre koşullu güncelleme
                return Veritabani::calistir(
                    'UPDATE kullanicilar SET yedek_kodlar = ? WHERE id = ? AND yedek_kodlar = ?',
                    [json_encode(array_values($liste)), $id, $mevcut]
                ) === 1;
            }
        }
        return false;
    }

    public static function yedekKodSayisi(int $id): int
    {
        $l = json_decode((string) Veritabani::skaler('SELECT yedek_kodlar FROM kullanicilar WHERE id = ?', [$id]), true);
        return is_array($l) ? count($l) : 0;
    }

    public static function aktifAdminSayisi(): int
    {
        return (int) Veritabani::skaler("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin' AND aktif = 1");
    }

    public static function epostaVarMi(string $eposta, ?int $haricId = null): bool
    {
        $sql = 'SELECT 1 FROM kullanicilar WHERE eposta = ?';
        $p = [$eposta];
        if ($haricId !== null) {
            $sql .= ' AND id <> ?';
            $p[] = $haricId;
        }
        return Veritabani::skaler($sql . ' LIMIT 1', $p) !== null;
    }
}
