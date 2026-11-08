<?php
declare(strict_types=1);

namespace App\Core;

/** Temel denetleyici: görünüm, JSON, yönlendirme ve hata yardımcıları. */
abstract class Denetleyici
{
    /** @param array<string,mixed> $veri */
    protected function gorunum(string $sablon, array $veri = [], string $layout = 'main', int $kod = 200): void
    {
        http_response_code($kod);
        echo Gorunum::render($sablon, $veri, $layout);
    }

    /** @param array<string,mixed> $veri */
    protected function json(array $veri, int $kod = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($kod);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Yalnızca site içi yollara yönlendirir (open redirect / header injection koruması). */
    protected function yonlendir(string $yol, int $kod = 302): never
    {
        if ($yol === '' || $yol[0] !== '/' || str_starts_with($yol, '//') || preg_match('/[\r\n\\\\]/', $yol) === 1) {
            $yol = '/';
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . url($yol), true, $kod);
        exit;
    }

    protected function hata(int $kod, string $mesaj = ''): void
    {
        $basliklar = [
            403 => 'Erişim reddedildi',
            404 => 'Sayfa bulunamadı',
            405 => 'İzin verilmeyen yöntem',
            429 => 'Çok fazla istek',
            500 => 'Sunucu hatası',
        ];
        $this->gorunum('hata/hata', [
            'kod'     => $kod,
            'baslik'  => $basliklar[$kod] ?? 'Hata',
            'mesaj'   => $mesaj,
            'sayfaBaslik' => ($basliklar[$kod] ?? 'Hata') . ' · ' . $kod,
        ], 'main', $kod);
    }

    protected function girdi(string $anahtar, string $varsayilan = ''): string
    {
        $d = $_POST[$anahtar] ?? $varsayilan;
        return is_string($d) ? $d : $varsayilan;
    }

    protected function istekJson(): bool
    {
        $kabul = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xhr === 'xmlhttprequest' || str_contains($kabul, 'application/json');
    }

    /** Sayfalama hesabı. @return array{sayfa:int,toplam:int,limit:int,offset:int} */
    protected function sayfala(int $kayitSayisi, int $limit): array
    {
        $toplam = max(1, (int) ceil($kayitSayisi / $limit));
        $sayfa = min(sayfa_no(), $toplam);
        return ['sayfa' => $sayfa, 'toplam' => $toplam, 'limit' => $limit, 'offset' => ($sayfa - 1) * $limit];
    }
}
