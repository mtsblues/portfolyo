<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Temiz URL yönlendirici.
 * Parametre tipleri: {slug}, {id}, {dosya}. Tanımsız yol 404, yanlış metot 405 (+Allow).
 */
final class Yonlendirici
{
    private const TIPLER = [
        'slug'  => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'id'    => '[1-9][0-9]{0,17}',
        'dosya' => '[a-f0-9]{32}\.(?:jpg|png|webp|gif|mp4|webm)',
        'jeton' => '[a-f0-9]{64}',
        'yedek' => '[A-Za-z0-9][A-Za-z0-9._-]{4,119}',
        'anahtar' => '[A-Za-z0-9]{16,64}',
    ];

    /** @var list<array{metot:string,regex:string,isleyici:array|callable}> */
    private array $rotalar = [];

    /** @var list<callable> */
    private array $araKatmanlar = [];

    /** @var callable|null */
    private $bulunamadi = null;

    /** @var callable|null */
    private $izinVerilmedi = null;

    public function __construct(private string $tabanYol = '')
    {
        $this->tabanYol = rtrim($tabanYol, '/');
    }

    public function ekle(string $metot, string $yol, array|callable $isleyici): void
    {
        $metot = strtoupper($metot);
        if (!in_array($metot, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('Desteklenmeyen metot: ' . $metot);
        }
        $this->rotalar[] = ['metot' => $metot, 'regex' => $this->regexOlustur($yol), 'isleyici' => $isleyici];
    }

    public function get(string $yol, array|callable $isleyici): void
    {
        $this->ekle('GET', $yol, $isleyici);
    }

    public function post(string $yol, array|callable $isleyici): void
    {
        $this->ekle('POST', $yol, $isleyici);
    }

    /** İşleyiciden önce çalışır: function (string $metot, string $yol): void */
    public function ara(callable $katman): void
    {
        $this->araKatmanlar[] = $katman;
    }

    public function bulunamadiIsleyici(callable $c): void
    {
        $this->bulunamadi = $c;
    }

    public function izinVerilmediIsleyici(callable $c): void
    {
        $this->izinVerilmedi = $c;
    }

    private function regexOlustur(string $yol): string
    {
        $parcalar = preg_split('/(\{[a-z]+\})/', $yol, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';
        foreach ($parcalar === false ? [] : $parcalar as $parca) {
            if (preg_match('/^\{([a-z]+)\}$/', $parca, $m) === 1) {
                if (!isset(self::TIPLER[$m[1]])) {
                    throw new InvalidArgumentException('Bilinmeyen rota parametresi: ' . $m[1]);
                }
                $regex .= '(?P<' . $m[1] . '>' . self::TIPLER[$m[1]] . ')';
            } else {
                $regex .= preg_quote($parca, '#');
            }
        }
        return '#^' . $regex . '$#D';
    }

    /** İstek yolunu normalleştirir; geçersizse null döner. */
    public function yolNormallestir(string $uri): ?string
    {
        // parse_url("//host") yolu null döndürdüğü için yol elle ayrıştırılır.
        $parcalar = preg_split('/[?#]/', $uri, 2);
        $yol = $parcalar === false || $parcalar[0] === '' ? '/' : $parcalar[0];
        $yol = rawurldecode($yol);
        if (preg_match('/[\x00-\x1F\x7F]/', $yol) === 1 || !mb_check_encoding($yol, 'UTF-8')) {
            return null;
        }
        if (
            $this->tabanYol !== ''
            && str_starts_with($yol, $this->tabanYol)
            && (strlen($yol) === strlen($this->tabanYol) || $yol[strlen($this->tabanYol)] === '/')
        ) {
            $yol = substr($yol, strlen($this->tabanYol));
        }
        $yol = '/' . trim($yol, '/');
        return $yol;
    }

    /**
     * Yan etkisiz eşleştirme (test edilebilir).
     * @return array{durum:int,isleyici:array|callable|null,parametreler:array<string,string|int>,izinli:list<string>}
     */
    public function eslestir(string $metot, string $uri): array
    {
        $metot = strtoupper($metot);
        if ($metot === 'HEAD') {
            $metot = 'GET';
        }
        $yol = $this->yolNormallestir($uri);
        if ($yol === null) {
            return ['durum' => 404, 'isleyici' => null, 'parametreler' => [], 'izinli' => []];
        }

        $izinli = [];
        foreach ($this->rotalar as $rota) {
            if (preg_match($rota['regex'], $yol, $m) !== 1) {
                continue;
            }
            if ($rota['metot'] === $metot) {
                $parametreler = [];
                foreach ($m as $ad => $deger) {
                    if (is_string($ad)) {
                        $parametreler[$ad] = $ad === 'id' ? (int) $deger : $deger;
                    }
                }
                return ['durum' => 200, 'isleyici' => $rota['isleyici'], 'parametreler' => $parametreler, 'izinli' => []];
            }
            $izinli[] = $rota['metot'];
        }
        if ($izinli !== []) {
            $izinli = array_values(array_unique($izinli));
            if (in_array('GET', $izinli, true)) {
                $izinli[] = 'HEAD';
            }
            return ['durum' => 405, 'isleyici' => null, 'parametreler' => [], 'izinli' => $izinli];
        }
        return ['durum' => 404, 'isleyici' => null, 'parametreler' => [], 'izinli' => []];
    }

    public function calistir(string $metot, string $uri): void
    {
        $sonuc = $this->eslestir($metot, $uri);
        $yol = $this->yolNormallestir($uri) ?? '/';

        if ($sonuc['durum'] === 404) {
            http_response_code(404);
            if ($this->bulunamadi !== null) {
                ($this->bulunamadi)();
            }
            return;
        }
        if ($sonuc['durum'] === 405) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $sonuc['izinli']));
            if ($this->izinVerilmedi !== null) {
                ($this->izinVerilmedi)($sonuc['izinli']);
            }
            return;
        }

        foreach ($this->araKatmanlar as $katman) {
            $katman(strtoupper($metot), $yol);
        }

        $isleyici = $sonuc['isleyici'];
        $parametreler = array_values($sonuc['parametreler']);
        if (is_array($isleyici)) {
            [$sinif, $eylem] = $isleyici;
            $nesne = new $sinif();
            $nesne->$eylem(...$parametreler);
            return;
        }
        $isleyici(...$parametreler);
    }
}
