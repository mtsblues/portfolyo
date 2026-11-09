<?php
declare(strict_types=1);

namespace App\Core;

/** Özgeçmiş ve tanıtım yardımcıları: tarih biçimi, vCard, içindekiler, teknoloji etiketleri (saf fonksiyonlar). */
final class CvYardimci
{
    private const AYLAR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    /** "YYYY-AA" geçerli mi? */
    public static function ayGecerli(string $ym): bool
    {
        return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $ym) === 1;
    }

    /** "2021-03" → "Mart 2021"; geçersiz/boş → "". */
    public static function ayYil(?string $ym): string
    {
        if ($ym === null || !self::ayGecerli($ym)) {
            return '';
        }
        return self::AYLAR[(int) substr($ym, 5, 2) - 1] . ' ' . substr($ym, 0, 4);
    }

    /** "Mart 2021 – Şubat 2023" / "Mart 2021 – Devam ediyor" / tek tarih. */
    public static function aralik(?string $bas, ?string $bit, bool $devam): string
    {
        $b = self::ayYil($bas);
        $s = $devam ? 'Devam ediyor' : self::ayYil($bit);
        if ($b !== '' && $s !== '') {
            return $b . ' – ' . $s;
        }
        return $b !== '' ? $b : $s;
    }

    /** Seviye (0-100) → 1..5 nokta. */
    public static function nokta(?int $seviye): int
    {
        if ($seviye === null || $seviye <= 0) {
            return 0;
        }
        return max(1, min(5, (int) ceil($seviye / 20)));
    }

    /** vCard 3.0 değer kaçışı: \ ; , ve satır sonu. CR/LF enjeksiyonu olamaz. */
    public static function vcardKac(string $s): string
    {
        $s = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s);
        return str_replace(["\r\n", "\r", "\n"], '\\n', $s);
    }

    /** RFC 6350/2426 satır katlama: 75 bayttan uzun satırlar UTF-8 karakter bölmeden katlanır. */
    private static function katla(string $satir): string
    {
        if (strlen($satir) <= 75) {
            return $satir;
        }
        $cikti = '';
        $uzunluk = 75;
        $kalan = $satir;
        while (strlen($kalan) > $uzunluk) {
            $kes = $uzunluk;
            while ($kes > 0 && (ord($kalan[$kes]) & 0xC0) === 0x80) {
                $kes--;
            }
            $cikti .= substr($kalan, 0, $kes) . "\r\n ";
            $kalan = substr($kalan, $kes);
            $uzunluk = 74;
        }
        return $cikti . $kalan;
    }

    /**
     * vCard 3.0 üretir.
     * @param array{ad:string,unvan?:string,eposta?:string,telefon?:string,url?:string,konum?:string,not?:string} $d
     */
    public static function vcard(array $d): string
    {
        $ad = trim($d['ad']);
        $parcalar = preg_split('/\s+/u', $ad) ?: [$ad];
        $soyad = count($parcalar) > 1 ? (string) array_pop($parcalar) : '';
        $ilk = implode(' ', $parcalar);
        $satirlar = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . self::vcardKac($soyad) . ';' . self::vcardKac($ilk) . ';;;',
            'FN:' . self::vcardKac($ad),
        ];
        $ekle = static function (string $etiket, ?string $deger) use (&$satirlar): void {
            if ($deger !== null && trim($deger) !== '') {
                $satirlar[] = $etiket . self::vcardKac(trim($deger));
            }
        };
        $ekle('TITLE:', $d['unvan'] ?? null);
        $ekle('EMAIL;TYPE=INTERNET:', $d['eposta'] ?? null);
        $ekle('TEL;TYPE=CELL:', $d['telefon'] ?? null);
        $ekle('URL:', $d['url'] ?? null);
        if (isset($d['konum']) && trim($d['konum']) !== '') {
            $satirlar[] = 'ADR;TYPE=WORK:;;' . self::vcardKac(trim($d['konum'])) . ';;;;';
        }
        $ekle('NOTE:', $d['not'] ?? null);
        $satirlar[] = 'END:VCARD';
        return implode("\r\n", array_map([self::class, 'katla'], $satirlar)) . "\r\n";
    }

    /**
     * Markdown çıktısındaki (h2/h3, id'li) başlıklardan içindekiler listesi.
     * @return list<array{id:string,metin:string,seviye:int}>
     */
    public static function icindekiler(string $html): array
    {
        $liste = [];
        if (preg_match_all('/<h([23]) id="([a-z0-9-]+)">(.*?)<\/h\1>/su', $html, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $b) {
                $metin = trim(html_entity_decode(strip_tags($b[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($metin !== '') {
                    $liste[] = ['id' => $b[2], 'metin' => $metin, 'seviye' => (int) $b[1]];
                }
            }
        }
        return $liste;
    }

    /**
     * "PHP, MySQL ,php" → ['PHP','MySQL'] (büyük/küçük harf duyarsız tekilleştirme, en çok 12, her biri ≤30 karakter).
     * @return list<string>
     */
    public static function teknolojiler(?string $ham): array
    {
        if ($ham === null || trim($ham) === '') {
            return [];
        }
        $liste = [];
        $gorulen = [];
        foreach (explode(',', $ham) as $t) {
            $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags($t)));
            if ($t === '' || mb_strlen($t) > 30) {
                continue;
            }
            $k = mb_strtolower($t);
            if (isset($gorulen[$k])) {
                continue;
            }
            $gorulen[$k] = true;
            $liste[] = $t;
            if (count($liste) >= 12) {
                break;
            }
        }
        return $liste;
    }

    /** Saklanacak biçim: "PHP, MySQL". */
    public static function teknolojiMetni(?string $ham): ?string
    {
        $l = self::teknolojiler($ham);
        return $l === [] ? null : implode(', ', $l);
    }
}
