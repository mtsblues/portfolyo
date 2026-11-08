<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Medya;

/**
 * Güvenli medya yükleme: içerik tabanlı MIME doğrulama (uzantıya/istemci MIME'ına güvenilmez),
 * rastgele dosya adı, web root dışında saklama. JPEG/PNG/WebP görseller GD ile yeniden kodlanır
 * (EXIF/GPS gibi üstveri temizlenir, EXIF yönü uygulanır, çok büyük görseller küçültülür).
 */
final class Yukleme
{
    public const GORSEL = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    public const VIDEO = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    public const AD_DESENI = '/^[a-f0-9]{32}\.(?:jpg|png|webp|gif|mp4|webm)$/';

    /** Yeniden kodlanan görsellerin en uzun kenar sınırı (px). */
    private const MAKS_KENAR = 2560;
    /** Bellek güvenliği: bu piksel sayısından büyük görseller yeniden kodlanmadan (doğrulanmış olarak) saklanır. */
    private const MAKS_YENIDEN_KODLAMA_PIKSEL = 16000000;
    private const MAKS_PIKSEL = 40000000;

    /**
     * @param array<string,mixed>|null $dosya $_FILES girdisi (tek dosya)
     * @return array{ad:?string,hata:?string,tur?:string,mime?:string,boyut?:int,genislik?:?int,yukseklik?:?int}
     *         ad null ve hata null => dosya seçilmedi
     */
    public static function kaydet(?array $dosya, bool $videoIzinli = false, ?int $kullaniciId = null): array
    {
        if ($dosya === null || !isset($dosya['error']) || is_array($dosya['error'])) {
            return ['ad' => null, 'hata' => null];
        }
        $kod = (int) $dosya['error'];
        if ($kod === UPLOAD_ERR_NO_FILE) {
            return ['ad' => null, 'hata' => null];
        }
        if ($kod === UPLOAD_ERR_INI_SIZE || $kod === UPLOAD_ERR_FORM_SIZE) {
            return ['ad' => null, 'hata' => 'Dosya sunucunun yükleme sınırını aşıyor (php.ini: upload_max_filesize / post_max_size).'];
        }
        if ($kod !== UPLOAD_ERR_OK) {
            return ['ad' => null, 'hata' => 'Dosya yüklenemedi.'];
        }

        $tmp = (string) ($dosya['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ad' => null, 'hata' => 'Geçersiz yükleme.'];
        }
        $boyut = (int) filesize($tmp);
        if ($boyut <= 0) {
            return ['ad' => null, 'hata' => 'Dosya boş.'];
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($mime === 'video/x-m4v') {
            $mime = 'video/mp4';
        }
        $izinliTurler = $videoIzinli ? self::GORSEL + self::VIDEO : self::GORSEL;
        if (!isset($izinliTurler[$mime])) {
            return ['ad' => null, 'hata' => $videoIzinli
                ? 'Desteklenen biçimler: JPEG, PNG, WebP, GIF, MP4, WebM.'
                : 'Yalnızca JPEG, PNG, WebP veya GIF görsel yüklenebilir.'];
        }

        $video = isset(self::VIDEO[$mime]);
        $maks = $video ? (int) config('yukleme.video_maks_bayt', 52428800) : (int) config('yukleme.maks_bayt', 5242880);
        if ($boyut > $maks) {
            return ['ad' => null, 'hata' => 'Dosya boyutu ' . intdiv($maks, 1048576) . ' MB sınırını aşıyor.'];
        }

        $gen = null;
        $yuk = null;
        $kaynak = $tmp;
        $gecici = null;
        if ($video) {
            if (!self::videoImzasiGecerli($tmp, $mime)) {
                return ['ad' => null, 'hata' => 'Video dosyası geçersiz.'];
            }
        } else {
            $bilgi = @getimagesize($tmp);
            if ($bilgi === false || $bilgi[0] < 1 || $bilgi[1] < 1 || $bilgi[0] * $bilgi[1] > self::MAKS_PIKSEL) {
                return ['ad' => null, 'hata' => 'Görsel dosyası geçersiz.'];
            }
            [$gen, $yuk] = [(int) $bilgi[0], (int) $bilgi[1]];
            if ($mime !== 'image/gif') {
                $yeni = null;
                // 1) Çok büyük görsel: GD varsa küçült (yeniden kodlama üstverisini de temizler).
                if (max($gen, $yuk) > self::MAKS_KENAR) {
                    $yeni = self::yenidenKodla($tmp, $mime, $gen, $yuk);
                }
                // 2) Aksi halde: kayıpsız üstveri (EXIF/GPS/IPTC/XMP) temizliği — GD gerektirmez.
                if ($yeni === null) {
                    $yeni = GorselTemizle::temizle($tmp, $mime);
                    if ($yeni !== null) {
                        $b2 = @getimagesize($yeni);
                        if ($b2 === false || (int) $b2[0] !== $gen || (int) $b2[1] !== $yuk) {
                            @unlink($yeni); // temizlenmiş dosya özgünle uyuşmuyor: güvenli tarafta kal
                            $yeni = null;
                        }
                    }
                }
                // 3) Ayrıştırma başarısızsa GD ile yeniden kodlamayı dene (varsa).
                if ($yeni === null) {
                    $yeni = self::yenidenKodla($tmp, $mime, $gen, $yuk);
                }
                if ($yeni !== null) {
                    $gecici = $yeni;
                    $kaynak = $yeni;
                    $b2 = @getimagesize($yeni);
                    if ($b2 !== false) {
                        [$gen, $yuk] = [(int) $b2[0], (int) $b2[1]];
                    }
                    $boyut = (int) filesize($yeni);
                }
            }
        }

        $dizin = (string) config('yukleme.dizin');
        if (!is_dir($dizin) && !@mkdir($dizin, 0750, true) && !is_dir($dizin)) {
            return ['ad' => null, 'hata' => 'Yükleme dizini oluşturulamadı.'];
        }
        $ad = bin2hex(random_bytes(16)) . '.' . $izinliTurler[$mime];
        $hedef = $dizin . '/' . $ad;
        $tasindi = $gecici !== null
            ? (@copy($kaynak, $hedef) && @unlink($gecici))
            : move_uploaded_file($tmp, $hedef);
        if (!$tasindi) {
            return ['ad' => null, 'hata' => 'Dosya kaydedilemedi.'];
        }
        @chmod($hedef, 0640);

        $sonuc = [
            'ad' => $ad, 'hata' => null, 'tur' => $video ? 'video' : 'gorsel', 'mime' => $mime,
            'boyut' => $boyut, 'genislik' => $gen, 'yukseklik' => $yuk,
        ];
        try {
            Medya::ekle($sonuc, $kullaniciId);
        } catch (\Throwable $t) {
            // Kütüphane kaydı başarısız olsa bile dosya kullanılabilir; günlüğe yaz.
            gunluk('Medya kaydı yazılamadı: ' . $t->getMessage());
        }
        return $sonuc;
    }

    /**
     * Çoklu yükleme alanını ($_FILES['dosyalar']) tek tek dosyalara böler.
     * @param array<string,mixed>|null $alan
     * @return list<array<string,mixed>>
     */
    public static function dosyaListesi(?array $alan): array
    {
        if ($alan === null || !isset($alan['error'])) {
            return [];
        }
        if (!is_array($alan['error'])) {
            return [$alan];
        }
        $liste = [];
        foreach (array_keys($alan['error']) as $i) {
            $liste[] = [
                'name'     => $alan['name'][$i] ?? '',
                'type'     => $alan['type'][$i] ?? '',
                'tmp_name' => $alan['tmp_name'][$i] ?? '',
                'error'    => $alan['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $alan['size'][$i] ?? 0,
            ];
        }
        return $liste;
    }

    /** MP4: 4-8. baytlarda "ftyp"; WebM/Matroska: EBML başlığı (1A 45 DF A3). */
    private static function videoImzasiGecerli(string $yol, string $mime): bool
    {
        $f = @fopen($yol, 'rb');
        if ($f === false) {
            return false;
        }
        $bas = (string) fread($f, 12);
        fclose($f);
        if ($mime === 'video/mp4') {
            return strlen($bas) >= 12 && substr($bas, 4, 4) === 'ftyp';
        }
        return strlen($bas) >= 4 && str_starts_with($bas, "\x1A\x45\xDF\xA3");
    }

    /**
     * GD ile yeniden kodlar: üstveri (EXIF/GPS) temizlenir, EXIF yönü uygulanır, büyük görsel küçültülür.
     * GD yoksa/başarısızsa null döner (doğrulanmış özgün dosya saklanır).
     */
    private static function yenidenKodla(string $kaynak, string $mime, int $gen, int $yuk): ?string
    {
        if (!extension_loaded('gd') || $gen * $yuk > self::MAKS_YENIDEN_KODLAMA_PIKSEL) {
            return null;
        }
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($kaynak),
            'image/png'  => @imagecreatefrompng($kaynak),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($kaynak) : false,
            default      => false,
        };
        if ($img === false) {
            return null;
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($kaynak);
            $yon = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $aci = [3 => 180, 6 => -90, 8 => 90][$yon] ?? 0;
            if ($aci !== 0) {
                $d = @imagerotate($img, $aci, 0);
                if ($d !== false) {
                    $img = $d;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > self::MAKS_KENAR) {
            $oran = self::MAKS_KENAR / max($w, $h);
            $k = @imagescale($img, max(1, (int) round($w * $oran)), max(1, (int) round($h * $oran)), IMG_BICUBIC);
            if ($k !== false) {
                $img = $k;
            }
        }

        $cikti = tempnam(sys_get_temp_dir(), 'ymg');
        if ($cikti === false) {
            return null;
        }
        $tamam = false;
        if ($mime === 'image/jpeg') {
            $tamam = imagejpeg($img, $cikti, 86);
        } elseif ($mime === 'image/png') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $tamam = imagepng($img, $cikti, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $tamam = imagewebp($img, $cikti, 85);
        }
        if (!$tamam || (int) filesize($cikti) <= 0) {
            @unlink($cikti);
            return null;
        }
        return $cikti;
    }

    public static function gecerliAd(?string $ad): bool
    {
        return $ad !== null && preg_match(self::AD_DESENI, $ad) === 1;
    }

    /** Dosya adından MIME türü (yalnızca doğrulanmış ad deseni için). */
    public static function mimeTuru(string $ad): ?string
    {
        $uz = strtolower(pathinfo($ad, PATHINFO_EXTENSION));
        $harita = array_flip(self::GORSEL + self::VIDEO);
        return $harita[$uz] ?? null;
    }

    public static function yol(string $ad): ?string
    {
        if (!self::gecerliAd($ad)) {
            return null;
        }
        $tam = (string) config('yukleme.dizin') . '/' . $ad;
        return is_file($tam) ? $tam : null;
    }

    /** Dosyayı ve kütüphane kaydını siler. */
    public static function sil(?string $ad): void
    {
        if ($ad === null || !self::gecerliAd($ad)) {
            return;
        }
        if (($yol = self::yol($ad)) !== null) {
            @unlink($yol);
        }
        try {
            Medya::dosyaSil($ad);
        } catch (\Throwable $t) {
            gunluk('Medya kaydı silinemedi: ' . $t->getMessage());
        }
    }
}
