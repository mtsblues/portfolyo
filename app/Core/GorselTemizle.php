<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Görsellerden gizlilik üstverisini (EXIF/GPS, IPTC, XMP, yorumlar, çekim cihazı bilgisi) KAYIPSIZ siler.
 * GD gerektirmez: JPEG/PNG/WebP dosya yapısı doğrudan ayrıştırılır, piksel verisine dokunulmaz.
 * JPEG'de yalnızca fotoğrafın doğru dönmesi için gereken "Orientation" değeri, tek alanlı temiz bir EXIF
 * bloğu olarak korunur.
 */
final class GorselTemizle
{
    /** @return string|null temizlenmiş içeriğin yazıldığı geçici dosyanın yolu; ayrıştırılamazsa null */
    public static function temizle(string $yol, string $mime): ?string
    {
        $ham = @file_get_contents($yol);
        if ($ham === false || $ham === '') {
            return null;
        }
        $sonuc = match ($mime) {
            'image/jpeg' => self::jpeg($ham),
            'image/png'  => self::png($ham),
            'image/webp' => self::webp($ham),
            default      => null,
        };
        if ($sonuc === null) {
            return null;
        }
        $gecici = tempnam(sys_get_temp_dir(), 'tmz');
        if ($gecici === false || file_put_contents($gecici, $sonuc) === false) {
            return null;
        }
        return $gecici;
    }

    public static function jpeg(string $b): ?string
    {
        $n = strlen($b);
        if ($n < 4 || $b[0] !== "\xFF" || $b[1] !== "\xD8") {
            return null;
        }
        $govde = '';
        $yon = 1;
        $i = 2;
        $sosGordu = false;
        while ($i + 2 <= $n) {
            if ($b[$i] !== "\xFF") {
                return null;
            }
            $m = ord($b[$i + 1]);
            if ($m === 0xFF) { // dolgu baytı
                $i++;
                continue;
            }
            if ($m === 0xD9) { // EOI
                $govde .= "\xFF\xD9";
                $i = $n;
                break;
            }
            if (($m >= 0xD0 && $m <= 0xD7) || $m === 0x01) { // uzunluksuz işaretçiler
                $govde .= substr($b, $i, 2);
                $i += 2;
                continue;
            }
            if ($m === 0xDA) { // SOS: sonrası entropi kodlu piksel verisi — olduğu gibi kopyala
                // Entropi verisinde 0xFF yalnızca 00 / RSTn ile gelir; ilk FFD9 gerçek EOI'dir.
                // EOI'den sonraki her şey (eklenmiş PHP/HTML gibi "polyglot" artıkları) atılır.
                $son = strpos($b, "\xFF\xD9", $i);
                $govde .= $son === false ? substr($b, $i) : substr($b, $i, $son - $i + 2);
                $sosGordu = true;
                $i = $n;
                break;
            }
            if ($i + 4 > $n) {
                return null;
            }
            $len = (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
            if ($len < 2 || $i + 2 + $len > $n) {
                return null;
            }
            $seg = substr($b, $i, 2 + $len);
            $tut = true;
            if ($m === 0xE1) { // APP1: EXIF veya XMP
                $icerik = substr($seg, 4);
                if (str_starts_with($icerik, "Exif\0\0")) {
                    $yon = self::exifYon(substr($icerik, 6)) ?? $yon;
                }
                $tut = false;
            } elseif ($m === 0xED || $m === 0xFE) { // APP13 (Photoshop/IPTC), COM (yorum)
                $tut = false;
            } elseif ($m >= 0xE3 && $m <= 0xEF && $m !== 0xEE) { // APP3..APP15 (APP14=Adobe renk bilgisi korunur)
                $tut = false;
            }
            if ($tut) {
                $govde .= $seg;
            }
            $i += 2 + $len;
        }
        if (!$sosGordu) {
            return null;
        }
        $exif = '';
        if ($yon >= 2 && $yon <= 8) {
            $tiff = 'II' . pack('v', 42) . pack('V', 8) . pack('v', 1) . pack('v', 0x0112) . pack('v', 3) . pack('V', 1)
                . pack('v', $yon) . "\0\0" . pack('V', 0);
            $veri = "Exif\0\0" . $tiff;
            $exif = "\xFF\xE1" . pack('n', strlen($veri) + 2) . $veri;
        }
        return "\xFF\xD8" . $exif . $govde;
    }

    /** TIFF içinden Orientation (0x0112) değerini okur; yoksa/bozuksa null. */
    private static function exifYon(string $tiff): ?int
    {
        $n = strlen($tiff);
        if ($n < 8) {
            return null;
        }
        $bo = substr($tiff, 0, 2);
        if ($bo !== 'II' && $bo !== 'MM') {
            return null;
        }
        $kucuk = $bo === 'II';
        $u16 = static fn(int $o): int => $kucuk ? ((ord($tiff[$o + 1]) << 8) | ord($tiff[$o])) : ((ord($tiff[$o]) << 8) | ord($tiff[$o + 1]));
        $u32 = static fn(int $o): int => $kucuk
            ? (ord($tiff[$o + 3]) << 24 | ord($tiff[$o + 2]) << 16 | ord($tiff[$o + 1]) << 8 | ord($tiff[$o]))
            : (ord($tiff[$o]) << 24 | ord($tiff[$o + 1]) << 16 | ord($tiff[$o + 2]) << 8 | ord($tiff[$o + 3]));
        if ($u16(2) !== 42) {
            return null;
        }
        $ifd = $u32(4);
        if ($ifd < 8 || $ifd + 2 > $n) {
            return null;
        }
        $sayi = $u16($ifd);
        for ($k = 0; $k < min($sayi, 64); $k++) {
            $o = $ifd + 2 + $k * 12;
            if ($o + 12 > $n) {
                return null;
            }
            if ($u16($o) === 0x0112) {
                $deger = $u16($o + 8);
                return $deger >= 1 && $deger <= 8 ? $deger : null;
            }
        }
        return null;
    }

    public static function png(string $b): ?string
    {
        if (substr($b, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $n = strlen($b);
        // Görüntü için gerekli/renk bilgisi taşıyan parçalar; tEXt/zTXt/iTXt/eXIf/tIME ve bilinmeyen yardımcılar atılır.
        $tut = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'pHYs', 'bKGD', 'hIST',
            'cICP', 'acTL', 'fcTL', 'fdAT'];
        $cikti = substr($b, 0, 8);
        $i = 8;
        $bitti = false;
        while ($i + 12 <= $n) {
            $len = (int) unpack('N', substr($b, $i, 4))[1];
            $tip = substr($b, $i + 4, 4);
            $toplam = 12 + $len;
            if ($len > $n || $i + $toplam > $n) {
                return null;
            }
            if (in_array($tip, $tut, true) || ($tip !== '' && ctype_upper($tip[0]))) { // büyük harfle başlayanlar zorunlu parçadır
                $cikti .= substr($b, $i, $toplam);
            }
            $i += $toplam;
            if ($tip === 'IEND') {
                $bitti = true;
                break;
            }
        }
        return $bitti ? $cikti : null;
    }

    public static function webp(string $b): ?string
    {
        $n = strlen($b);
        if ($n < 12 || substr($b, 0, 4) !== 'RIFF' || substr($b, 8, 4) !== 'WEBP') {
            return null;
        }
        // RIFF başlığındaki boyutun ötesindeki artıklar (eklenmiş veri) yok sayılır.
        $n = min($n, 8 + (int) unpack('V', substr($b, 4, 4))[1]);
        $govde = '';
        $i = 12;
        while ($i + 8 <= $n) {
            $tip = substr($b, $i, 4);
            $len = (int) unpack('V', substr($b, $i + 4, 4))[1];
            if ($i + 8 + $len > $n) {
                return null;
            }
            $toplam = 8 + $len + ($len & 1);
            $parca = substr($b, $i, min($toplam, $n - $i));
            $i += $toplam;
            if ($tip === 'EXIF' || $tip === 'XMP ') {
                continue;
            }
            if ($tip === 'VP8X' && $len >= 10) {
                $parca[8] = chr(ord($parca[8]) & ~0x0C); // EXIF (0x08) ve XMP (0x04) bayraklarını kapat
            }
            $govde .= $parca;
        }
        return 'RIFF' . pack('V', 4 + strlen($govde)) . 'WEBP' . $govde;
    }
}
