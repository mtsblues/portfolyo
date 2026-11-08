<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bağımlılıksız, GÜVENLİ-ÖNCE Markdown çevirici.
 * Güvenlik ilkesi: girdinin tamamı önce htmlspecialchars ile kaçışlanır; ardından yalnızca
 * bu sınıfın ürettiği etiketler eklenir. Ham HTML hiçbir zaman yorumlanmaz. URL'ler beyaz listeyle
 * (http, https, mailto, site içi /yol, #çapa) sınırlanır.
 */
final class Markdown
{
    public static function cevir(string $metin): string
    {
        $metin = str_replace(["\r\n", "\r", "\x1A"], ["\n", "\n", ''], $metin);
        $satirlar = explode("\n", $metin);
        $n = count($satirlar);
        $i = 0;
        $cikti = '';

        while ($i < $n) {
            $s = $satirlar[$i];

            if (trim($s) === '') {
                $i++;
                continue;
            }

            // Medya blokları: tek başına görsel, galeri, video, YouTube, Vimeo
            $medya = self::medyaBlogu($s, $satirlar, $i, $n);
            if ($medya !== null) {
                $cikti .= $medya;
                continue;
            }

            // Çitli kod bloğu
            if (preg_match('/^```\s*([A-Za-z0-9+#._-]*)[^`]*$/', $s, $m) === 1) {
                $dil = strtolower($m[1]);
                $kod = [];
                $i++;
                while ($i < $n && preg_match('/^```\s*$/', $satirlar[$i]) !== 1) {
                    $kod[] = $satirlar[$i];
                    $i++;
                }
                $i++;
                $sinif = $dil !== '' ? ' class="language-' . self::kac($dil) . '"' : '';
                $cikti .= '<pre><code' . $sinif . '>' . self::kac(implode("\n", $kod)) . "</code></pre>\n";
                continue;
            }

            // Başlık
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $s, $m) === 1) {
                $seviye = min(6, strlen($m[1]) + 1);
                $id = Dogrulayici::slugOlustur($m[2]);
                $idAttr = $id !== '' ? ' id="' . self::kac($id) . '"' : '';
                $cikti .= "<h{$seviye}{$idAttr}>" . self::satir($m[2]) . "</h{$seviye}>\n";
                $i++;
                continue;
            }

            // Yatay çizgi
            if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $s) === 1) {
                $cikti .= "<hr>\n";
                $i++;
                continue;
            }

            // Alıntı
            if (preg_match('/^\s*>/', $s) === 1) {
                $ic = [];
                while ($i < $n && preg_match('/^\s*>/', $satirlar[$i]) === 1) {
                    $ic[] = (string) preg_replace('/^\s*> ?/', '', $satirlar[$i]);
                    $i++;
                }
                $cikti .= "<blockquote>\n" . self::cevir(implode("\n", $ic)) . "</blockquote>\n";
                continue;
            }

            // Listeler
            if (preg_match('/^\s*[-*+]\s+/', $s) === 1 || preg_match('/^\s*\d{1,9}[.)]\s+/', $s) === 1) {
                $sirali = preg_match('/^\s*\d/', $s) === 1;
                $desen = $sirali ? '/^\s*\d{1,9}[.)]\s+(.*)$/' : '/^\s*[-*+]\s+(.*)$/';
                $etiket = $sirali ? 'ol' : 'ul';
                $cikti .= "<{$etiket}>\n";
                while ($i < $n && preg_match($desen, $satirlar[$i], $m) === 1) {
                    $cikti .= '<li>' . self::satir($m[1]) . "</li>\n";
                    $i++;
                }
                $cikti .= "</{$etiket}>\n";
                continue;
            }

            // Tablo
            if (str_contains($s, '|') && $i + 1 < $n
                && preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $satirlar[$i + 1]) === 1) {
                $baslik = self::hucreler($s);
                $i += 2;
                $cikti .= '<div class="tablo-kap"><table><thead><tr>';
                foreach ($baslik as $h) {
                    $cikti .= '<th>' . self::satir($h) . '</th>';
                }
                $cikti .= '</tr></thead><tbody>';
                while ($i < $n && trim($satirlar[$i]) !== '' && str_contains($satirlar[$i], '|')) {
                    $cikti .= '<tr>';
                    foreach (self::hucreler($satirlar[$i]) as $h) {
                        $cikti .= '<td>' . self::satir($h) . '</td>';
                    }
                    $cikti .= '</tr>';
                    $i++;
                }
                $cikti .= "</tbody></table></div>\n";
                continue;
            }

            // Paragraf
            $p = [];
            while ($i < $n && trim($satirlar[$i]) !== '' && !self::blokBaslangici($satirlar[$i])) {
                $p[] = trim($satirlar[$i]);
                $i++;
            }
            if ($p === []) {
                $p[] = trim($s);
                $i++;
            }
            $cikti .= '<p>' . self::satir(implode("\n", $p)) . "</p>\n";
        }

        return $cikti;
    }

    /**
     * Medya blokları. Başarılıysa HTML döner ve $i'yi ilerletir; bu satır bir medya bloğu değilse null.
     *   ![altyazı](url)           → <figure> (tek başına satırdaysa)
     *   :::galeri ... :::         → görsel galerisi (her satır bir ![alt](url))
     *   @[video](/yuklemeler/x.mp4)   → site içi video
     *   @[youtube](kimlik|url)    → youtube-nocookie gömme
     *   @[vimeo](kimlik|url)      → Vimeo gömme
     * @param list<string> $satirlar
     */
    private static function medyaBlogu(string $s, array $satirlar, int &$i, int $n): ?string
    {
        $t = trim($s);

        // Galeri
        if (preg_match('/^:::\s*galeri\s*$/i', $t) === 1) {
            $ogeler = '';
            $j = $i + 1;
            while ($j < $n && preg_match('/^:::\s*$/', trim($satirlar[$j])) !== 1) {
                if (preg_match('/^!\[([^\]\n]*)\]\(([^)\s]+)\)\s*$/', trim($satirlar[$j]), $m) === 1) {
                    $u = self::urlGuvenli(self::kac($m[2]));
                    if ($u !== null) {
                        $alt = self::kac($m[1]);
                        $ogeler .= '<figure><img src="' . $u . '" alt="' . $alt . '" loading="lazy">'
                            . ($alt !== '' ? '<figcaption>' . $alt . '</figcaption>' : '') . "</figure>\n";
                    }
                }
                $j++;
            }
            $i = $j + 1;
            return $ogeler !== '' ? '<div class="galeri">' . "\n" . $ogeler . "</div>\n" : '';
        }

        // Site içi video
        if (preg_match('/^@\[video\]\((\/yuklemeler\/[a-f0-9]{32}\.(?:mp4|webm))\)$/', $t, $m) === 1) {
            $i++;
            return '<figure class="video"><video controls preload="metadata" playsinline src="'
                . self::kac(self::temelYol() . $m[1]) . "\"></video></figure>\n";
        }

        // YouTube
        if (preg_match('/^@\[youtube\]\(([^)\s]+)\)$/', $t, $m) === 1) {
            $id = self::youtubeKimligi($m[1]);
            $i++;
            return $id === null ? '' : '<div class="video-kap"><iframe src="https://www.youtube-nocookie.com/embed/' . $id
                . '" title="YouTube video oynatıcı" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen'
                . ' referrerpolicy="strict-origin-when-cross-origin"'
                . " sandbox=\"allow-scripts allow-same-origin allow-presentation allow-popups\"></iframe></div>\n";
        }

        // Vimeo
        if (preg_match('/^@\[vimeo\]\(([^)\s]+)\)$/', $t, $m) === 1) {
            $id = preg_match('/^(?:https:\/\/(?:www\.)?vimeo\.com\/(?:video\/)?)?(\d{4,12})(?:[\/?#].*)?$/', $m[1], $v) === 1 ? $v[1] : null;
            $i++;
            return $id === null ? '' : '<div class="video-kap"><iframe src="https://player.vimeo.com/video/' . $id
                . '" title="Vimeo video oynatıcı" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen'
                . ' referrerpolicy="strict-origin-when-cross-origin"'
                . " sandbox=\"allow-scripts allow-same-origin allow-presentation allow-popups\"></iframe></div>\n";
        }

        // Tek başına görsel → altyazılı figür
        if (preg_match('/^!\[([^\]\n]*)\]\(([^)\s]+)\)$/', $t, $m) === 1) {
            $u = self::urlGuvenli(self::kac($m[2]));
            if ($u !== null) {
                $alt = self::kac($m[1]);
                $i++;
                return '<figure class="gorsel"><img src="' . $u . '" alt="' . $alt . '" loading="lazy">'
                    . ($alt !== '' ? '<figcaption>' . $alt . '</figcaption>' : '') . "</figure>\n";
            }
        }
        return null;
    }

    /** YouTube kimliği (11 karakter) ya da bilinen YouTube adreslerinden kimlik; geçersizse null. */
    private static function youtubeKimligi(string $girdi): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $girdi) === 1) {
            return $girdi;
        }
        $desenler = [
            '#^https://(?:www\.|m\.)?youtube\.com/watch\?(?:[^\#]*&)?v=([A-Za-z0-9_-]{11})(?:[&\#].*)?$#',
            '#^https://youtu\.be/([A-Za-z0-9_-]{11})(?:[/?\#].*)?$#',
            '#^https://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})(?:[/?\#].*)?$#',
        ];
        foreach ($desenler as $d) {
            if (preg_match($d, $girdi, $m) === 1) {
                return $m[1];
            }
        }
        return null;
    }

    /** Alt klasörde (örn. XAMPP /portfolyo) çalışırken site içi yolların önüne eklenecek ön ek. */
    private static function temelYol(): string
    {
        return function_exists('temel_yol') ? temel_yol() : '';
    }

    private static function blokBaslangici(string $s): bool
    {
        return preg_match('/^(```|:::|@\[(?:video|youtube|vimeo)\]|#{1,6}\s|\s*>|\s*[-*+]\s+|\s*\d{1,9}[.)]\s+)/', $s) === 1
            || preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/', $s) === 1;
    }

    /** @return list<string> */
    private static function hucreler(string $satir): array
    {
        $satir = trim($satir);
        $satir = trim($satir, '|');
        return array_map('trim', explode('|', $satir));
    }

    public static function kac(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Satır içi biçimlendirme. Girdi ham metindir, ilk iş kaçışlanır. */
    private static function satir(string $ham): string
    {
        $t = self::kac($ham);
        $yer = [];
        $sakla = static function (string $html) use (&$yer): string {
            $anahtar = "\x1A" . count($yer) . "\x1A";
            $yer[$anahtar] = $html;
            return $anahtar;
        };

        // Satır içi kod (içerik zaten kaçışlı)
        $t = (string) preg_replace_callback('/`([^`\n]+)`/', static fn(array $m): string => $sakla('<code>' . $m[1] . '</code>'), $t);

        // Görsel
        $t = (string) preg_replace_callback('/!\[([^\]\n]*)\]\(([^)\s]+)\)/', static function (array $m) use ($sakla): string {
            $u = self::urlGuvenli($m[2]);
            if ($u === null) {
                return $m[0];
            }
            return $sakla('<img src="' . $u . '" alt="' . $m[1] . '" loading="lazy">');
        }, $t);

        // Bağlantı
        $t = (string) preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', static function (array $m) use ($sakla): string {
            $u = self::urlGuvenli($m[2]);
            if ($u === null) {
                return $m[0];
            }
            $dis = preg_match('#^https?://#i', htmlspecialchars_decode($u, ENT_QUOTES)) === 1;
            $ek = $dis ? ' target="_blank"' : '';
            return $sakla('<a href="' . $u . '" rel="noopener noreferrer nofollow"' . $ek . '>' . self::vurgu($m[1]) . '</a>');
        }, $t);

        $t = self::vurgu($t);

        // Yer tutucuları ters sırayla geri koy (iç içe olanlar için)
        foreach (array_reverse($yer, true) as $anahtar => $html) {
            $t = str_replace($anahtar, $html, $t);
        }
        return str_replace("\n", "<br>\n", $t);
    }

    private static function vurgu(string $t): string
    {
        $t = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $t);
        $t = (string) preg_replace('/(?<![\w_])__(?=\S)(.+?)(?<=\S)__(?![\w_])/s', '<strong>$1</strong>', $t);
        $t = (string) preg_replace('/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/s', '<em>$1</em>', $t);
        $t = (string) preg_replace('/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/s', '<em>$1</em>', $t);
        return (string) preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/s', '<del>$1</del>', $t);
    }

    /**
     * Kaçışlı URL'yi beyaz listeyle doğrular; güvenliyse aynen (kaçışlı) döner, değilse null.
     */
    private static function urlGuvenli(string $kacisli): ?string
    {
        $ham = htmlspecialchars_decode($kacisli, ENT_QUOTES);
        if (preg_match('/[\x00-\x20\x7F]/', $ham) === 1) {
            return null;
        }
        if (preg_match('#^(?:https?://[^/]|mailto:[^/]|/(?!/)|\#)#i', $ham) !== 1) {
            return null;
        }
        // Site içi mutlak yol (/yuklemeler/..., /blog): alt klasörde çalışırken temel yol eklenir.
        if ($ham[0] === '/') {
            return self::kac(self::temelYol()) . $kacisli;
        }
        return $kacisli;
    }
}
