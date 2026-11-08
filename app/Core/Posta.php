<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bağımlılıksız, minimal SMTP istemcisi (STARTTLS / örtülü TLS, AUTH LOGIN).
 * Yapılandırma: .env MAIL_* değerleri (config/config.php → 'posta').
 * Başlık enjeksiyonuna karşı tüm başlık değerlerinden CR/LF temizlenir; adresler doğrulanır.
 */
final class Posta
{
    private const ZAMAN_ASIMI = 10;

    public static function yapilandirildi(): bool
    {
        return trim((string) config('posta.host', '')) !== '';
    }

    /**
     * @return array{ok:bool,hata:?string}
     */
    public static function gonder(string $alici, string $konu, string $metin, ?string $yanitAdresi = null, ?string $yanitAdi = null): array
    {
        $c = (array) config('posta', []);
        $host = trim((string) ($c['host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'hata' => 'MAIL_HOST ayarlı değil.'];
        }
        $port = (int) ($c['port'] ?? 587);
        $guvenli = (string) ($c['guvenli'] ?? 'tls');
        $kullanici = (string) ($c['kullanici'] ?? '');
        $gonderen = trim((string) ($c['gonderen'] ?? ''));
        if ($gonderen === '') {
            $gonderen = filter_var($kullanici, FILTER_VALIDATE_EMAIL) !== false ? $kullanici : 'noreply@' . preg_replace('/^[^.]+\./', '', $host);
        }
        $alici = self::adres($alici);
        $gonderen = self::adres($gonderen);
        if ($alici === null || $gonderen === null) {
            return ['ok' => false, 'hata' => 'Geçersiz gönderen veya alıcı e-posta adresi.'];
        }

        $site = self::temizle((string) ayar('site_baslik', 'Portfolyo'));
        $baslik = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::kodlaAd($site) . ' <' . $gonderen . '>',
            'To: <' . $alici . '>',
            'Subject: ' . self::kodlaAd(self::temizle($konu)),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (preg_replace('/^.*@/', '', $gonderen) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: Portfolyo',
        ];
        $yanit = $yanitAdresi !== null ? self::adres($yanitAdresi) : null;
        if ($yanit !== null) {
            $baslik[] = 'Reply-To: ' . ($yanitAdi !== null && trim($yanitAdi) !== '' ? self::kodlaAd(self::temizle($yanitAdi)) . ' ' : '') . '<' . $yanit . '>';
        }
        $govde = implode("\r\n", $baslik) . "\r\n\r\n" . chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $metin)), 76, "\r\n");

        $f = null;
        try {
            $baglanti = ($guvenli === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
            $baglam = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
            $f = @stream_socket_client($baglanti, $no, $metinHata, self::ZAMAN_ASIMI, STREAM_CLIENT_CONNECT, $baglam);
            if ($f === false) {
                return ['ok' => false, 'hata' => 'SMTP sunucusuna bağlanılamadı: ' . self::temizle((string) $metinHata)];
            }
            stream_set_timeout($f, self::ZAMAN_ASIMI);
            self::bekle($f, 220);
            $ehlo = 'EHLO ' . self::yerelAd();
            self::komut($f, $ehlo, 250);
            if ($guvenli === 'tls') {
                self::komut($f, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($f, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS el sıkışması başarısız (sertifika doğrulanamadı?).');
                }
                self::komut($f, $ehlo, 250);
            }
            if ($kullanici !== '') {
                self::komut($f, 'AUTH LOGIN', 334);
                self::komut($f, base64_encode($kullanici), 334);
                self::komut($f, base64_encode((string) ($c['sifre'] ?? '')), 235);
            }
            self::komut($f, 'MAIL FROM:<' . $gonderen . '>', 250);
            self::komut($f, 'RCPT TO:<' . $alici . '>', [250, 251]);
            self::komut($f, 'DATA', 354);
            fwrite($f, $govde . "\r\n.\r\n");
            self::bekle($f, 250);
            @fwrite($f, "QUIT\r\n");
            fclose($f);
            return ['ok' => true, 'hata' => null];
        } catch (\Throwable $t) {
            if (is_resource($f)) {
                @fclose($f);
            }
            return ['ok' => false, 'hata' => self::temizle($t->getMessage())];
        }
    }

    /** @param resource $f */
    private static function komut($f, string $komut, int|array $beklenen): void
    {
        fwrite($f, $komut . "\r\n");
        self::bekle($f, $beklenen);
    }

    /** @param resource $f */
    private static function bekle($f, int|array $beklenen): void
    {
        $son = '';
        while (($satir = fgets($f, 1024)) !== false) {
            $son = $satir;
            if (strlen($satir) < 4 || $satir[3] !== '-') {
                break;
            }
        }
        $kod = (int) substr($son, 0, 3);
        if (!in_array($kod, (array) $beklenen, true)) {
            // Yanıt metni parola içermez; yine de CR/LF temizlenir.
            throw new \RuntimeException('SMTP yanıtı beklenmedik (' . self::temizle(trim($son)) . ')');
        }
    }

    private static function adres(string $a): ?string
    {
        $a = trim($a);
        return (preg_match('/[\r\n<>,;"]/', $a) !== 1 && filter_var($a, FILTER_VALIDATE_EMAIL) !== false) ? $a : null;
    }

    private static function temizle(string $s): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s));
    }

    private static function kodlaAd(string $s): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $s) === 1 && !str_contains($s, '"') ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private static function yerelAd(): string
    {
        $h = parse_url((string) config('uygulama.url', ''), PHP_URL_HOST);
        return is_string($h) && preg_match('/^[A-Za-z0-9.\-]+$/', $h) === 1 ? $h : 'localhost';
    }
}
