<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Denetim;
use App\Core\HizSiniri;
use App\Core\Oturum;
use App\Core\Totp;
use App\Core\Yedek;
use App\Models\Ayar;
use App\Models\Kullanici;
use Throwable;

/**
 * Yedekleme ve geri yükleme (yalnızca yönetici).
 * Geri yükleme: kuru çalıştırma özeti → parola + yazılı onay (+ 2FA kodu) → otomatik önceki yedek → uygula.
 */
final class YedekController extends AdminTemel
{
    private const YUKLEME_UZANTILARI = ['sql', 'gz'];

    public function __construct()
    {
        parent::__construct();
        $this->yalnizcaAdmin();
    }

    public function liste(): void
    {
        $this->admin('yedekler', [
            'sayfaBaslik' => 'Yedekleme',
            'yedekler'    => Yedek::liste(),
            'hedefler'    => Yedek::hedefler(),
            'sonYedek'    => Yedek::sonSqlZamani(),
            'otoGun'      => (int) Ayar::al('otomatik_yedek_gun', '0'),
            'yuklemeLimit' => self::yuklemeLimiti(),
            'medyaDestek' => class_exists(\PharData::class) && \Phar::canCompress(\Phar::GZ),
        ]);
    }

    public function al(): void
    {
        @set_time_limit(0);
        try {
            $sql = Yedek::sqlAl();
            $mesaj = 'Veritabanı yedeği alındı: ' . basename($sql);
            $medya = null;
            if ($this->girdi('medya') === '1') {
                $medya = Yedek::medyaAl();
                $mesaj .= $medya !== null ? ' · medya: ' . basename($medya) : ' · (yüklenmiş medya yok)';
            }
            Yedek::dondur((int) config('yedek.sakla', 14));
            Denetim::kaydet('yedek.alindi', basename($sql), $medya !== null ? 'medya dahil' : 'yalnızca veritabanı', $this->kullanici['id']);
        } catch (Throwable $t) {
            gunluk('Yedek alınamadı: ' . $t->getMessage());
            $this->geriDon('/admin/yedekler', 'hata', 'Yedek alınamadı: ' . $t->getMessage());
        }
        $this->geriDon('/admin/yedekler', 'basari', $mesaj);
    }

    public function indir(string $yedek): never
    {
        $yol = Yedek::yol($yedek);
        if ($yol === null) {
            $this->hata(404);
            exit;
        }
        Denetim::kaydet('yedek.indirildi', $yedek, null, $this->kullanici['id']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $yedek . '"');
        header('Content-Length: ' . (int) filesize($yol));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($yol);
        exit;
    }

    public function sil(string $yedek): void
    {
        if (!Yedek::sil($yedek)) {
            $this->geriDon('/admin/yedekler', 'hata', 'Yedek bulunamadı.');
        }
        Denetim::kaydet('yedek.silindi', $yedek, null, $this->kullanici['id']);
        $this->geriDon('/admin/yedekler', 'basari', 'Yedek silindi.');
    }

    public function gonder(string $yedek): void
    {
        $yol = Yedek::yol($yedek);
        $secili = array_values(array_intersect((array) ($_POST['hedef'] ?? []), Yedek::hedefler()));
        if ($yol === null || $secili === []) {
            $this->geriDon('/admin/yedekler', 'hata', 'Yedek veya hedef seçilmedi.');
        }
        @set_time_limit(0);
        $ozet = [];
        $hepsiOk = true;
        foreach (Yedek::disaGonder($yol, $secili) as $h => $s) {
            $hepsiOk = $hepsiOk && $s['ok'];
            $ozet[] = ($s['ok'] ? '✓ ' : '✗ ') . $s['mesaj'];
        }
        Denetim::kaydet('yedek.dis_gonderildi', $yedek, implode(', ', $secili), $this->kullanici['id']);
        $this->geriDon('/admin/yedekler', $hepsiOk ? 'basari' : 'hata', implode(' · ', $ozet));
    }

    /** Dışarıdan alınmış yedek dosyasını yükler ve kuru çalıştırma ile doğrular. */
    public function yukle(): void
    {
        $d = $_FILES['yedek'] ?? null;
        if (!is_array($d) || ($d['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $d['tmp_name'])) {
            $this->geriDon('/admin/yedekler', 'hata', 'Dosya yüklenemedi (boyut sınırı: ' . self::bicim(self::yuklemeLimiti()) . ').');
        }
        $ad = strtolower((string) $d['name']);
        $gz = str_ends_with($ad, '.sql.gz');
        if (!$gz && !str_ends_with($ad, '.sql')) {
            $this->geriDon('/admin/yedekler', 'hata', 'Yalnızca .sql veya .sql.gz yedekleri yüklenebilir.');
        }
        $hedefAd = 'yuklenen-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . ($gz ? '.sql.gz' : '.sql');
        $hedef = Yedek::dizin() . '/' . $hedefAd;
        if (!move_uploaded_file((string) $d['tmp_name'], $hedef)) {
            $this->geriDon('/admin/yedekler', 'hata', 'Dosya kaydedilemedi.');
        }
        @chmod($hedef, 0600);
        $r = Yedek::dogrula($hedef);
        if (!$r['ok']) {
            @unlink($hedef);
            $this->geriDon('/admin/yedekler', 'hata', 'Geçerli bir Portfolyo yedeği değil: ' . implode(' | ', array_slice($r['hatalar'], 0, 3)));
        }
        Yedek::dondur((int) config('yedek.sakla', 14));
        Denetim::kaydet('yedek.yuklendi', $hedefAd, null, $this->kullanici['id']);
        Oturum::flash('basari', 'Yedek doğrulandı ve yüklendi. Geri yüklemek için listeden “Geri yükle”yi seçin.');
        $this->yonlendir('/admin/yedekler/' . $hedefAd . '/geri-yukle');
    }

    /** Adım 1: kuru çalıştırma özeti + onay formu. */
    public function geriYuklemeForm(string $yedek): void
    {
        $yol = Yedek::yol($yedek);
        if ($yol === null) {
            $this->hata(404);
            return;
        }
        $medya = str_starts_with($yedek, 'medya-');
        $this->admin('yedek_geri_yukle', [
            'sayfaBaslik' => 'Geri yükle',
            'yedek'       => $yedek,
            'medya'       => $medya,
            'rapor'       => $medya ? null : Yedek::dogrula($yol),
            'iki2fa'      => Kullanici::ikiAdimAktifMi($this->kullanici['id']),
            'boyut'       => (int) filesize($yol),
            'zaman'       => (int) filemtime($yol),
        ]);
    }

    /** Adım 2: kimlik doğrulama + yazılı onay → uygula. */
    public function geriYukle(string $yedek): void
    {
        $yol = Yedek::yol($yedek);
        if ($yol === null) {
            $this->hata(404);
            return;
        }
        $geri = '/admin/yedekler/' . $yedek . '/geri-yukle';
        if (!HizSiniri::izinVer('yedek-geri:' . $this->kullanici['id'], 5, 900)) {
            $this->geriDon($geri, 'hata', 'Çok fazla deneme. 15 dakika sonra tekrar deneyin.');
        }
        $k = Kullanici::idIle($this->kullanici['id']);
        $parola = $this->girdi('mevcut_parola');
        if ($k === null || strlen($parola) > 1024 || !password_verify($parola, (string) $k['parola_hash'])) {
            Denetim::kaydet('yedek.geri_yukleme_reddedildi', $yedek, 'parola hatalı', $this->kullanici['id']);
            $this->geriDon($geri, 'hata', 'Parola hatalı.');
        }
        if (Kullanici::ikiAdimAktifMi($this->kullanici['id']) && !$this->ikiAdimKoduDogru($this->girdi('kod'))) {
            Denetim::kaydet('yedek.geri_yukleme_reddedildi', $yedek, '2FA hatalı', $this->kullanici['id']);
            $this->geriDon($geri, 'hata', 'Doğrulama kodu hatalı veya süresi dolmuş.');
        }
        if (trim($this->girdi('onay')) !== 'GERI YUKLE') {
            $this->geriDon($geri, 'hata', 'Onay için kutuya tam olarak GERI YUKLE yazın.');
        }

        $kimlik = $this->kullanici['id'];
        if (str_starts_with($yedek, 'medya-')) {
            $s = Yedek::medyaGeriYukle($yol);
            Denetim::kaydet($s['ok'] ? 'yedek.medya_geri_yuklendi' : 'yedek.geri_yukleme_basarisiz', $yedek, $s['mesaj'], $kimlik);
            $this->geriDon('/admin/yedekler', $s['ok'] ? 'basari' : 'hata', $s['mesaj']);
        }

        $s = Yedek::geriYukle($yol);
        // Denetim kaydı geri yüklenen veritabanına yazılır; yedekte olmayan kullanıcı kimliği için null kullan
        Denetim::kaydet($s['ok'] ? 'yedek.geri_yuklendi' : 'yedek.geri_yukleme_basarisiz', $yedek, $s['mesaj'], null);
        if (!$s['ok']) {
            $this->geriDon('/admin/yedekler', 'hata', $s['mesaj']);
        }
        // Kullanıcı tablosu değişmiş olabilir: güvenli tarafta kal, oturumu kapat.
        Oturum::cikis();
        Oturum::flash('basari', 'Yedek geri yüklendi. Önceki durum otomatik olarak “' . ($s['oncesi'] ?? '') . '” dosyasına yedeklendi. Lütfen yeniden giriş yapın.');
        $this->yonlendir('/admin/giris');
    }

    private function ikiAdimKoduDogru(string $kod): bool
    {
        $kod = trim($kod);
        $id = $this->kullanici['id'];
        if ($kod === '' || strlen($kod) > 32) {
            return false;
        }
        if (preg_match('/^\d{3}\s?\d{3}$/', $kod) === 1) {
            $bilgi = Kullanici::totpBilgi($id);
            $gizli = $bilgi !== null && $bilgi['totp_gizli'] !== null ? Totp::coz((string) $bilgi['totp_gizli']) : null;
            $adim = $gizli !== null ? Totp::dogrula($gizli, $kod) : null;
            return $adim !== null && Kullanici::totpAdimKaydet($id, $adim);
        }
        return Totp::yedekKodBicimiMi($kod) && Kullanici::yedekKodTuket($id, Totp::yedekKodOzeti($kod));
    }

    private static function yuklemeLimiti(): int
    {
        $bayt = static function (string $v): int {
            $v = trim($v);
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824,
                'm' => $n * 1048576,
                'k' => $n * 1024,
                default => $n,
            };
        };
        $a = $bayt((string) ini_get('upload_max_filesize'));
        $b = $bayt((string) ini_get('post_max_size'));
        return max(0, min($a > 0 ? $a : PHP_INT_MAX, $b > 0 ? $b : PHP_INT_MAX));
    }

    private static function bicim(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 1, ',', '.') . ' KB';
    }
}
