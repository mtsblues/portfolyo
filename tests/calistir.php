<?php
declare(strict_types=1);

/**
 * Bağımlılıksız test çalıştırıcı:  php tests/calistir.php
 * Veritabanı gerektirmez (SQL enjeksiyon testi için pdo_sqlite varsa bellek içi DB kullanır).
 * Çıkış kodu: tümü geçerse 0, aksi halde 1.
 */

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Csrf;
use App\Core\Dogrulayici;
use App\Core\HizSiniri;
use App\Core\Markdown;
use App\Core\Veritabani;
use App\Core\Yapilandirma;
use App\Core\Yonlendirici;
use App\Core\Yukleme;

Yapilandirma::configAyarla([
    'uygulama' => ['zaman_dilimi' => 'Europe/Istanbul', 'url' => ''],
    'yukleme'  => ['maks_bayt' => 2097152, 'dizin' => KOK . '/storage/yuklemeler'],
    'guvenlik' => ['guvenilir_proxy' => false, 'hsts' => false],
]);

$gecen = 0;
$kalan = [];

function test(string $ad, callable $f): void
{
    global $gecen, $kalan;
    try {
        $f();
        $gecen++;
        echo "  ✓ {$ad}\n";
    } catch (Throwable $t) {
        $kalan[] = $ad . ' → ' . $t->getMessage();
        echo "  ✗ {$ad}\n      " . $t->getMessage() . "\n";
    }
}

function esit(mixed $beklenen, mixed $gercek, string $not = ''): void
{
    if ($beklenen !== $gercek) {
        throw new RuntimeException(($not !== '' ? $not . ': ' : '') . 'beklenen ' . var_export($beklenen, true) . ', gelen ' . var_export($gercek, true));
    }
}

function dogru(bool $kosul, string $not): void
{
    if (!$kosul) {
        throw new RuntimeException($not);
    }
}

echo "== e() / XSS kaçışlama\n";
test('e() ENT_QUOTES uygular', function (): void {
    esit('&lt;a href=&quot;x&quot;&gt;&#039;', e('<a href="x">\''));
    esit('', e(null));
});

echo "== Doğrulayıcı\n";
test('e-posta', function (): void {
    dogru(Dogrulayici::epostaGecerli('ali@ornek.com'), 'geçerli e-posta reddedildi');
    dogru(!Dogrulayici::epostaGecerli('ali@'), 'geçersiz e-posta kabul edildi');
    dogru(!Dogrulayici::epostaGecerli("ali@ornek.com\r\nBcc: x@y.z"), 'başlık enjeksiyonu kabul edildi');
});
test('slug', function (): void {
    dogru(Dogrulayici::slugGecerli('powershell-otomasyon-betigi'), 'geçerli slug reddedildi');
    foreach (['Büyük', 'a_b', '-a', 'a-', 'a--b', '../x', 'a b', ''] as $kotu) {
        dogru(!Dogrulayici::slugGecerli($kotu), "kötü slug kabul edildi: {$kotu}");
    }
});
test('slugOlustur Türkçe', function (): void {
    esit('cozum-powershell-otomasyon-betigi', Dogrulayici::slugOlustur('Çözüm: PowerShell Otomasyon Betiği!'));
    esit('istanbul-gunesi', Dogrulayici::slugOlustur('İstanbul Güneşi'));
});
test('parola politikası', function (): void {
    dogru(Dogrulayici::parolaHatalari('Kisa1!') !== [], 'kısa parola kabul edildi');
    dogru(Dogrulayici::parolaHatalari('tamamenkucukharf123!') !== [], 'büyük harfsiz kabul edildi');
    esit([], Dogrulayici::parolaHatalari('Guclu-Parola-2026!'));
});
test('yap(): kurallar ve temizleme', function (): void {
    $d = Dogrulayici::yap(
        ['ad' => "  Ali\x00\x07 Veli ", 'eposta' => 'bozuk', 'mesaj' => 'kısa', 'url' => 'javascript:alert(1)'],
        ['ad' => 'gerekli|min:2|max:120', 'eposta' => 'gerekli|eposta', 'mesaj' => 'gerekli|min:10', 'url' => 'bos_olabilir|url', 'yok' => 'gerekli']
    );
    esit('Ali Veli', $d->deger('ad'));
    foreach (['eposta', 'mesaj', 'url', 'yok'] as $alan) {
        dogru(isset($d->hatalar()[$alan]), "{$alan} için hata beklenirdi");
    }
    dogru(!isset($d->hatalar()['ad']), 'ad geçerli olmalı');
});
test('dizi girdisi reddedilir', function (): void {
    $d = Dogrulayici::yap(['ad' => ['x']], ['ad' => 'gerekli']);
    dogru($d->basarisiz(), 'dizi kabul edildi');
});

echo "== CSRF\n";
test('token üretimi ve doğrulama', function (): void {
    $_SESSION = [];
    $t = Csrf::token();
    esit(64, strlen($t));
    esit($t, Csrf::token(), 'aynı oturumda sabit');
    dogru(Csrf::dogrula($t), 'geçerli token reddedildi');
    dogru(!Csrf::dogrula('x'), 'sahte token kabul edildi');
    dogru(!Csrf::dogrula(''), 'boş token kabul edildi');
    dogru(!Csrf::dogrula(null), 'null token kabul edildi');
    Csrf::yenile();
    dogru(!Csrf::dogrula($t), 'eski token yenileme sonrası geçerli');
});
test('oturumsuz doğrulama başarısız', function (): void {
    $_SESSION = [];
    dogru(!Csrf::dogrula(str_repeat('a', 64)), 'token olmadan kabul edildi');
});
test('istegiDogrula: POST alanı ve başlık', function (): void {
    $_SESSION = [];
    $t = Csrf::token();
    $_POST = ['csrf_token' => $t];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = null;
    dogru(Csrf::istegiDogrula(), 'POST token reddedildi');
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $t;
    dogru(Csrf::istegiDogrula(), 'başlık token reddedildi');
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'yanlis';
    dogru(!Csrf::istegiDogrula(), 'yanlış token kabul edildi');
    $_POST = ['csrf_token' => ['dizi']];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = null;
    dogru(!Csrf::istegiDogrula(), 'dizi token kabul edildi');
    $_POST = [];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
});

echo "== Yönlendirici\n";
$r = new Yonlendirici('');
(require KOK . '/config/rotalar.php')($r);
test('sabit ve parametreli rotalar', function () use ($r): void {
    esit(200, $r->eslestir('GET', '/')['durum']);
    esit(200, $r->eslestir('GET', '/blog/')['durum'], 'sondaki slash');
    esit(200, $r->eslestir('GET', '/blog?sayfa=2')['durum'], 'sorgu dizesi');
    $m = $r->eslestir('GET', '/makale/powershell-otomasyon-betigi');
    esit(200, $m['durum']);
    esit(['slug' => 'powershell-otomasyon-betigi'], $m['parametreler']);
    esit('detay', $m['isleyici'][1]);
    $a = $r->eslestir('GET', '/admin/makale/12/duzenle');
    esit(['id' => 12], $a['parametreler'], 'id int olmalı');
});
test('HEAD, GET gibi eşleşir', function () use ($r): void {
    esit(200, $r->eslestir('HEAD', '/projeler')['durum']);
});
test('404: tanımsız ve geçersiz parametre', function () use ($r): void {
    foreach (['/yok', '/makale/Buyuk_Harf', '/makale/a--b', '/makale/', '/admin/makale/0/duzenle', '/admin/makale/abc/duzenle',
              '/admin/makale/-1/duzenle', '/makale/..%2f..%2f.env', "/makale/x%00", '/yuklemeler/../../.env', '/yuklemeler/abc.php',
              '/index.php', '//evil.com'] as $yol) {
        esit(404, $r->eslestir('GET', $yol)['durum'], $yol);
    }
});
test('405 + Allow', function () use ($r): void {
    $s = $r->eslestir('GET', '/iletisim/gonder');
    esit(405, $s['durum']);
    dogru(in_array('POST', $s['izinli'], true), 'Allow POST içermeli');
    $s = $r->eslestir('POST', '/blog');
    esit(405, $s['durum']);
    dogru(in_array('GET', $s['izinli'], true), 'Allow GET içermeli');
    esit(405, $r->eslestir('GET', '/admin/cikis')['durum'], 'çıkış GET ile yapılamaz');
    esit(405, $r->eslestir('GET', '/admin/makale/5/sil')['durum'], 'silme GET ile yapılamaz');
});
test('yüklemeler deseni', function () use ($r): void {
    esit(200, $r->eslestir('GET', '/yuklemeler/' . str_repeat('a1', 16) . '.png')['durum']);
    esit(404, $r->eslestir('GET', '/yuklemeler/' . str_repeat('a1', 16) . '.svg')['durum']);
    esit(404, $r->eslestir('GET', '/yuklemeler/' . str_repeat('a1', 15) . '.png')['durum']);
});
test('temel yol (alt klasör) çıkarılır', function () use ($r): void {
    $alt = new Yonlendirici('/portfolyo/public');
    (require KOK . '/config/rotalar.php')($alt);
    esit(200, $alt->eslestir('GET', '/portfolyo/public/blog')['durum']);
    esit(200, $alt->eslestir('GET', '/portfolyo/public/')['durum']);
});
test('XAMPP alt klasör: kök .htaccess ile /portfolyo/ altında rotalar çalışır', function (): void {
    // Kök .htaccess isteği iç olarak public/'e yönlendirir: taban yol "/portfolyo", istek "/portfolyo/blog".
    $alt = new Yonlendirici('/portfolyo');
    (require KOK . '/config/rotalar.php')($alt);
    esit(200, $alt->eslestir('GET', '/portfolyo/')['durum']);
    esit(200, $alt->eslestir('GET', '/portfolyo')['durum']);
    esit(200, $alt->eslestir('GET', '/portfolyo/blog?sayfa=2')['durum']);
    // Sınır denetimi: "/portfolyofoo" taban yol sayılmamalı.
    esit('/portfolyofoo/blog', $alt->yolNormallestir('/portfolyofoo/blog'));
});

echo "== Markdown (XSS güvenliği)\n";
test('ham HTML kaçışlanır', function (): void {
    $h = Markdown::cevir("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>");
    dogru(!str_contains($h, '<script'), 'script etiketi yaşıyor');
    dogru(!str_contains($h, '<img'), 'ham img etiketi yaşıyor');
    dogru(str_contains($h, '&lt;script&gt;'), 'kaçışlı çıktı yok');
});
test('tehlikeli URL şemaları reddedilir', function (): void {
    foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html;base64,AAAA', 'vbscript:x', '//evil.com/x', 'javascript&#58;alert(1)'] as $u) {
        $h = Markdown::cevir("[tıkla]({$u})");
        dogru(!str_contains($h, '<a '), "tehlikeli bağlantı üretildi: {$u}");
        $h = Markdown::cevir("![x]({$u})");
        dogru(!str_contains($h, '<img'), "tehlikeli görsel üretildi: {$u}");
    }
});
test('güvenli bağlantılar rel ile üretilir', function (): void {
    $h = Markdown::cevir('[site](https://ornek.com/a?b=1&c=2) ve [yerel](/blog)');
    dogru(str_contains($h, 'href="https://ornek.com/a?b=1&amp;c=2"'), 'https bağlantı yok: ' . $h);
    dogru(str_contains($h, 'rel="noopener noreferrer nofollow"'), 'rel eksik');
    dogru(str_contains($h, 'href="/blog"'), 'yerel bağlantı yok');
});
test('öznitelik kırma denemesi etkisiz', function (): void {
    $h = Markdown::cevir('![a" onerror="alert(1)](/a.png) [x](https://a.com/"onmouseover="alert(1))');
    // Tırnaklar &quot; olarak kaçışlandığı için öznitelik değerinden çıkılamaz.
    dogru(!str_contains($h, '" onerror="'), 'onerror özniteliği oluştu: ' . $h);
    dogru(!str_contains($h, '"onmouseover="'), 'onmouseover özniteliği oluştu: ' . $h);
    dogru(str_contains($h, '&quot;'), 'tırnaklar kaçışlanmadı: ' . $h);
});
test('kod bloğu içeriği kaçışlanır', function (): void {
    $h = Markdown::cevir("```php\n<?php echo '<b>x</b>'; ?>\n```");
    dogru(str_contains($h, '<pre><code class="language-php">'), 'kod bloğu yok');
    dogru(!str_contains($h, '<b>'), 'kod içindeki HTML yorumlandı');
    dogru(str_contains($h, '&lt;?php'), 'php açılışı kaçışlanmadı');
});
test('biçimlendirme', function (): void {
    $h = Markdown::cevir("# Başlık\n\n**kalın** ve *italik* ve `kod`\n\n- bir\n- iki\n\n1. a\n2. b\n\n> alıntı\n\n| A | B |\n|---|---|\n| 1 | 2 |");
    foreach (['<h2 id="baslik">', '<strong>kalın</strong>', '<em>italik</em>', '<code>kod</code>', '<ul>', '<ol>', '<blockquote>', '<table>'] as $beklenen) {
        dogru(str_contains($h, $beklenen), "eksik: {$beklenen}");
    }
});
test('yer tutucu kaçağı yok', function (): void {
    dogru(!str_contains(Markdown::cevir("x\x1A0\x1A `a` y"), "\x1A"), 'kontrol karakteri sızdı');
});

echo "== Yükleme / Hız sınırı\n";
test('yükleme dosya adı deseni', function (): void {
    dogru(Yukleme::gecerliAd(str_repeat('ab', 16) . '.jpg'), 'geçerli ad reddedildi');
    foreach (['../x.jpg', str_repeat('ab', 16) . '.php', str_repeat('ab', 16) . '.jpg.php', 'x.png', str_repeat('AB', 16) . '.png', null] as $kotu) {
        dogru(!Yukleme::gecerliAd($kotu), 'kötü ad kabul edildi: ' . var_export($kotu, true));
    }
    esit(null, Yukleme::yol('../../.env'));
});
test('hız sınırı pencere içinde engeller', function (): void {
    $anahtar = 'test:' . bin2hex(random_bytes(6));
    dogru(HizSiniri::izinVer($anahtar, 3, 60), '1. istek');
    dogru(HizSiniri::izinVer($anahtar, 3, 60), '2. istek');
    dogru(HizSiniri::izinVer($anahtar, 3, 60), '3. istek');
    dogru(!HizSiniri::izinVer($anahtar, 3, 60), '4. istek engellenmeli');
    dogru(HizSiniri::izinVer($anahtar . 'b', 3, 60), 'farklı anahtar bağımsız olmalı');
});

echo "== PDO prepared statement (SQL enjeksiyon)\n";
if (extension_loaded('pdo_sqlite')) {
    test("' OR 1=1 -- parametre olarak etkisiz", function (): void {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE kullanicilar (id INTEGER PRIMARY KEY, eposta TEXT, parola_hash TEXT)');
        Veritabani::ayarla($pdo);
        Veritabani::calistir('INSERT INTO kullanicilar (eposta, parola_hash) VALUES (?, ?)', ['a@b.c', 'h1']);
        Veritabani::calistir('INSERT INTO kullanicilar (eposta, parola_hash) VALUES (?, ?)', ['x@y.z', 'h2']);
        $kotu = "' OR 1=1 --";
        esit([], Veritabani::hepsi('SELECT * FROM kullanicilar WHERE eposta = ?', [$kotu]), 'enjeksiyon satır döndürdü');
        esit([], Veritabani::hepsi('SELECT * FROM kullanicilar WHERE eposta = :e', ['e' => $kotu]));
        Veritabani::calistir('INSERT INTO kullanicilar (eposta, parola_hash) VALUES (?, ?)', [$kotu . "'); DROP TABLE kullanicilar; --", 'h3']);
        esit(3, (int) Veritabani::skaler('SELECT COUNT(*) FROM kullanicilar'), 'tablo bozuldu');
        Veritabani::ayarla(null);
    });
} else {
    echo "  (atlandı: pdo_sqlite yok — MySQL ile elle test için README 'Güvenlik testleri' bölümüne bakın)\n";
}

echo "== Bütünlük: sınıflar ve rotalar\n";
test('tüm sınıflar yüklenir (imza uyumsuzluğu yok) ve her rota geçerli bir metoda bağlıdır', function (): void {
    // php -l bu hatayı yakalamaz: örn. alt sınıfta ana sınıfla çakışan metot imzası.
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(KOK . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $yol = str_replace('\\', '/', $f->getPathname());
        if (substr($yol, -4) !== '.php' || str_contains($yol, '/Views/') || str_contains($yol, '/helpers/') || substr($yol, -13) === 'bootstrap.php') {
            continue;
        }
        $sinif = 'App\\' . str_replace('/', '\\', substr($yol, strlen(KOK . '/app/'), -4));
        dogru(class_exists($sinif), 'sınıf yüklenemedi: ' . $sinif);
    }
    $r = new Yonlendirici('');
    (require KOK . '/config/rotalar.php')($r);
    $rf = new ReflectionProperty($r, 'rotalar');
    $rf->setAccessible(true);
    foreach ($rf->getValue($r) as $rota) {
        $h = $rota['isleyici'];
        dogru(!is_array($h) || method_exists($h[0], $h[1]), 'rota metodu yok: ' . $h[0] . '::' . $h[1]);
    }
});
test('yeni rotalar eşleşir', function (): void {
    $r = new Yonlendirici('');
    (require KOK . '/config/rotalar.php')($r);
    foreach (['/rss.xml', '/robots.txt', '/hakkimda', '/admin/medya', '/admin/profil', '/admin/giris/dogrula'] as $y) {
        esit(200, $r->eslestir('GET', $y)['durum'], $y);
    }
    esit(200, $r->eslestir('POST', '/admin/medya/yukle')['durum']);
    esit(200, $r->eslestir('POST', '/admin/onizle')['durum']);
    esit(405, $r->eslestir('GET', '/admin/medya/yukle')['durum']);
    esit(200, $r->eslestir('GET', '/yuklemeler/' . str_repeat('ab', 16) . '.mp4')['durum']);
    esit(404, $r->eslestir('GET', '/yuklemeler/' . str_repeat('ab', 16) . '.svg')['durum']);
    esit(404, $r->eslestir('GET', '/yuklemeler/' . str_repeat('ab', 16) . '.php')['durum']);
});
test('ayar alanları tutarlı (benzersiz anahtar, geçerli tip)', function (): void {
    $gecerliTipler = ['text', 'email', 'number', 'url', 'yol', 'yol_dis', 'metin', 'onay', 'secim'];
    $gorulen = [];
    foreach (App\Models\Ayar::GRUPLAR as $g) {
        foreach ($g['alanlar'] as $anahtar => $a) {
            dogru(!isset($gorulen[$anahtar]), 'yinelenen ayar: ' . $anahtar);
            $gorulen[$anahtar] = true;
            dogru(in_array($a[1], $gecerliTipler, true), 'geçersiz tip: ' . $anahtar);
            dogru(preg_match('/^[a-z0-9_]{3,60}$/', $anahtar) === 1, 'geçersiz anahtar: ' . $anahtar);
        }
    }
    dogru(count($gorulen) >= 15, 'ayar sayısı beklenenden az');
});

echo "== Markdown medya (görsel, galeri, video, gömme)\n";
test('tek başına görsel altyazılı <figure> olur; alt metin kaçışlanır', function (): void {
    $ad = str_repeat('a1', 16) . '.png';
    $h = Markdown::cevir("![Manzara](/yuklemeler/{$ad})");
    dogru(str_contains($h, '<figure class="gorsel"><img src="/yuklemeler/' . $ad . '" alt="Manzara" loading="lazy"><figcaption>Manzara</figcaption>'), 'figure üretilmedi: ' . $h);
    $x = Markdown::cevir("![x\" onerror=\"alert(1)](/yuklemeler/{$ad})");
    dogru(!str_contains($x, 'onerror="'), 'alt metinden öznitelik enjeksiyonu: ' . $x);
});
test('satır içi görsel paragraf içinde kalır', function (): void {
    $h = Markdown::cevir('Metin ![ikon](/yuklemeler/' . str_repeat('b2', 16) . '.png) devam.');
    dogru(str_starts_with($h, '<p>') && str_contains($h, '<img') && !str_contains($h, '<figure'), $h);
});
test('galeri: geçerli görseller alınır, tehlikeli adresler atlanır', function (): void {
    $h = Markdown::cevir(":::galeri\n![Bir](/yuklemeler/" . str_repeat('c3', 16) . ".jpg)\n![kötü](javascript:alert(1))\n![Dış](https://example.com/x.png)\n:::");
    esit(2, substr_count($h, '<figure>'), $h);
    dogru(str_contains($h, '<div class="galeri">') && !str_contains($h, 'javascript:'), $h);
});
test('site içi video: yalnızca doğrulanmış yükleme adı', function (): void {
    $ad = str_repeat('d4', 16) . '.mp4';
    dogru(str_contains(Markdown::cevir("@[video](/yuklemeler/{$ad})"), '<video controls preload="metadata" playsinline src="/yuklemeler/' . $ad . '">'), 'video üretilmedi');
    foreach (['@[video](/yuklemeler/../../etc/passwd.mp4)', '@[video](https://evil.com/x.mp4)', '@[video](/yuklemeler/abc.mp4)', '@[video](/yuklemeler/' . str_repeat('d4', 16) . '.php)'] as $kotu) {
        dogru(!str_contains(Markdown::cevir($kotu), '<video'), 'kötü video kabul edildi: ' . $kotu);
    }
});
test('YouTube/Vimeo: yalnızca kesin biçimli kimlikler iframe olur', function (): void {
    foreach (['dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?t=5', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=x', 'https://www.youtube.com/shorts/dQw4w9WgXcQ'] as $g) {
        $h = Markdown::cevir("@[youtube]({$g})");
        dogru(str_contains($h, 'src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"') && str_contains($h, 'sandbox='), 'youtube reddedildi: ' . $g);
    }
    foreach (['https://evil.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXc', 'javascript:alert(1)', '"><script>alert(1)</script>', 'http://youtu.be/dQw4w9WgXcQ'] as $k) {
        dogru(!str_contains(Markdown::cevir("@[youtube]({$k})"), '<iframe'), 'kötü youtube kabul edildi: ' . $k);
    }
    dogru(str_contains(Markdown::cevir('@[vimeo](https://vimeo.com/123456789)'), 'src="https://player.vimeo.com/video/123456789"'), 'vimeo');
    dogru(!str_contains(Markdown::cevir('@[vimeo](abc)'), '<iframe'), 'kötü vimeo kabul edildi');
});

echo "== TOTP / iki adımlı doğrulama\n";
test('RFC 6238 resmî test vektörleri (SHA1, 8 hane)', function (): void {
    $anahtar = '12345678901234567890';
    foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $t => $beklenen) {
        esit($beklenen, App\Core\Totp::hotp($anahtar, intdiv($t, 30), 8), "t={$t}");
    }
});
test('base32 gidiş-dönüş ve doğrulama penceresi', function (): void {
    esit('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', App\Core\Totp::base32Kodla('12345678901234567890'));
    esit('12345678901234567890', App\Core\Totp::base32Coz('GEZD GNBV-GY3T QOJQ GEZDGNBVGY3TQOJQ'));
    $g = App\Core\Totp::gizliUret();
    esit(32, strlen($g));
    $simdi = 1700000000;
    $kod = App\Core\Totp::hotp((string) App\Core\Totp::base32Coz($g), intdiv($simdi, 30));
    dogru(App\Core\Totp::dogrula($g, $kod, $simdi) === intdiv($simdi, 30), 'geçerli kod reddedildi');
    dogru(App\Core\Totp::dogrula($g, $kod, $simdi + 30) !== null, '±1 adım toleransı yok');
    esit(null, App\Core\Totp::dogrula($g, $kod, $simdi + 120), 'eski kod kabul edildi');
    esit(null, App\Core\Totp::dogrula($g, '12345a', $simdi));
    esit(null, App\Core\Totp::dogrula($g, '', $simdi));
});
test('gizli anahtar şifreleme ve yedek kodlar', function (): void {
    App\Core\Yapilandirma::configAyarla(['uygulama' => ['anahtar' => 'test-anahtari-123', 'zaman_dilimi' => 'Europe/Istanbul', 'url' => ''],
        'yukleme' => ['maks_bayt' => 2097152, 'dizin' => KOK . '/storage/yuklemeler'], 'guvenlik' => ['guvenilir_proxy' => false, 'hsts' => false]]);
    $g = App\Core\Totp::gizliUret();
    $s = App\Core\Totp::sifrele($g);
    dogru(!str_contains($s, $g), 'gizli anahtar düz metin saklandı');
    esit($g, App\Core\Totp::coz($s));
    esit(null, App\Core\Totp::coz(substr($s, 0, -4) . 'AAAA'), 'bozulmuş şifreli metin çözüldü');
    $kodlar = App\Core\Totp::yedekKodlariUret(8);
    esit(8, count(array_unique($kodlar)));
    dogru(App\Core\Totp::yedekKodBicimiMi($kodlar[0]), 'yedek kod biçimi');
    esit(App\Core\Totp::yedekKodOzeti($kodlar[0]), App\Core\Totp::yedekKodOzeti(strtoupper(str_replace('-', '', $kodlar[0]))));
    dogru(App\Core\Totp::yedekKodOzeti($kodlar[0]) !== App\Core\Totp::yedekKodOzeti($kodlar[1]), 'farklı kodlar aynı özet');
});

echo "== Görsel üstveri temizliği (GD gerektirmez)\n";
test('PNG: metin/EXIF/zaman parçaları silinir, görüntü parçaları kalır', function (): void {
    $chunk = static fn(string $t, string $v): string => pack('N', strlen($v)) . $t . $v . pack('N', crc32($t . $v));
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0)) . $chunk('tEXt', "Author\0GIZLI-YAZAR")
        . $chunk('eXIf', 'GIZLI-GPS') . $chunk('tIME', "\x07\xEA\x0B\x08\x0C\x00\x00") . $chunk('IDAT', gzcompress("\0\x01\x02\x03")) . $chunk('IEND', '') . '<' . '?php GIZLI-KOD ?' . '>';
    $s = App\Core\GorselTemizle::png($png);
    dogru($s !== null, 'temizlenemedi');
    foreach (['GIZLI-YAZAR', 'GIZLI-GPS', 'tIME', 'GIZLI-KOD'] as $yasak) {
        dogru(!str_contains($s, $yasak), 'kaldı: ' . $yasak);
    }
    dogru(str_contains($s, 'IHDR') && str_contains($s, 'IDAT') && str_ends_with($s, 'IEND' . pack('N', crc32('IEND'))), 'görüntü parçaları eksik');
});
test('JPEG: EXIF/GPS/XMP/IPTC/yorum silinir, yön korunur, EOI sonrası artık atılır', function (): void {
    $seg = static fn(int $m, string $v): string => "\xFF" . chr($m) . pack('n', strlen($v) + 2) . $v;
    $exif = static fn(int $yon): string => "Exif\0\0" . 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1) . pack('n', 0x0112) . pack('n', 3) . pack('N', 1) . pack('n', $yon) . "\0\0" . pack('N', 0) . 'GIZLI-GPS';
    $yap = static fn(int $yon): string => "\xFF\xD8" . $seg(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0") . $seg(0xE1, $exif($yon))
        . $seg(0xE1, "http://ns.adobe.com/xap/1.0/\0GIZLI-XMP") . $seg(0xED, "Photoshop 3.0\0GIZLI-IPTC") . $seg(0xFE, 'GIZLI-YORUM')
        . $seg(0xDB, str_repeat("\x10", 65)) . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00" . "\x12\x34\xFF\x00\x56" . "\xFF\xD9" . '<' . '?php GIZLI-KOD ?' . '>';
    $s = App\Core\GorselTemizle::jpeg($yap(6));
    dogru($s !== null, 'temizlenemedi');
    foreach (['GIZLI-GPS', 'GIZLI-XMP', 'GIZLI-IPTC', 'GIZLI-YORUM', 'GIZLI-KOD', 'adobe.com'] as $yasak) {
        dogru(!str_contains($s, $yasak), 'kaldı: ' . $yasak);
    }
    dogru(str_contains($s, "Exif\0\0II") && str_contains($s, pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', 6)), 'yön (Orientation=6) korunmadı');
    dogru(str_contains($s, 'JFIF') && str_contains($s, "\x12\x34\xFF\x00\x56") && str_ends_with($s, "\xFF\xD9"), 'JFIF/piksel verisi bozuldu');
    $d = App\Core\GorselTemizle::jpeg($yap(1));
    dogru($d !== null && !str_contains($d, 'Exif'), 'yön 1 iken EXIF bloğu eklenmemeli');
});
test('WebP: EXIF/XMP parçaları silinir, VP8X bayrakları kapanır, RIFF boyutu tutarlı', function (): void {
    $vp8x = 'VP8X' . pack('V', 10) . chr(0x1C) . "\0\0\0" . "\x3F\0\0" . "\x2F\0\0";
    $govde = $vp8x . 'EXIF' . pack('V', 6) . 'GIZLIX' . 'XMP ' . pack('V', 6) . 'GIZLIY' . 'ALPH' . pack('V', 2) . 'ab';
    $webp = 'RIFF' . pack('V', 4 + strlen($govde)) . 'WEBP' . $govde . 'GIZLI-ARTIK';
    $s = App\Core\GorselTemizle::webp($webp);
    dogru($s !== null && !str_contains($s, 'GIZLI'), 'temizlenemedi');
    esit(0x10, ord($s[20]), 'VP8X bayrakları');
    esit(strlen($s) - 8, unpack('V', substr($s, 4, 4))[1], 'RIFF boyutu');
});
test('bozuk/kesik görsel girdileri hata vermeden reddedilir', function (): void {
    esit(null, App\Core\GorselTemizle::jpeg('not a jpeg'));
    esit(null, App\Core\GorselTemizle::jpeg("\xFF\xD8\xFF\xE1\x00\xFFabc"));
    esit(null, App\Core\GorselTemizle::png("\x89PNG\r\n\x1a\n\x00\x00\xFF\xFFIHDR"));
    esit(null, App\Core\GorselTemizle::png('xx'));
    esit(null, App\Core\GorselTemizle::webp('RIFF....WEBPVP8 ' . "\xFF\xFF\xFF\xFF"));
});

echo "== Yükleme ve e-posta güvenliği\n";
test('yükleme dosya adı deseni ve MIME eşlemesi', function (): void {
    $h = str_repeat('0f', 16);
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm'] as $uz => $mime) {
        dogru(Yukleme::gecerliAd("{$h}.{$uz}"), "ad reddedildi: {$uz}");
        esit($mime, Yukleme::mimeTuru("{$h}.{$uz}"));
    }
    foreach (['svg', 'php', 'html', 'phtml', 'exe', 'jpg.php'] as $uz) {
        dogru(!Yukleme::gecerliAd("{$h}.{$uz}"), "ad kabul edildi: {$uz}");
    }
    dogru(!Yukleme::gecerliAd('../' . $h . '.png') && !Yukleme::gecerliAd($h . '.png/'), 'yol gezdirme');
});
test('çoklu yükleme alanı tek tek dosyalara bölünür', function (): void {
    $alan = ['name' => ['a.png', 'b.jpg'], 'type' => ['', ''], 'tmp_name' => ['x', 'y'], 'error' => [0, 4], 'size' => [1, 0]];
    $l = Yukleme::dosyaListesi($alan);
    esit(2, count($l));
    esit('b.jpg', $l[1]['name']);
    esit(4, $l[1]['error']);
    esit([], Yukleme::dosyaListesi(null));
});
test('Posta: yapılandırma yoksa ve adreste başlık enjeksiyonu varsa gönderilmez', function (): void {
    App\Core\Yapilandirma::configAyarla(['posta' => ['host' => ''], 'uygulama' => ['url' => '']]);
    dogru(!App\Core\Posta::yapilandirildi(), 'host boşken yapılandırılmış sayıldı');
    esit(false, App\Core\Posta::gonder('a@b.co', 'k', 'm')['ok']);
    App\Core\Yapilandirma::configAyarla(['posta' => ['host' => '127.0.0.1', 'port' => 1, 'guvenli' => 'yok', 'gonderen' => 'x@example.test'], 'uygulama' => ['url' => '']]);
    $s = App\Core\Posta::gonder("kurban@example.test\r\nBcc: x@y.z", 'k', 'm');
    esit(false, $s['ok']);
    dogru(str_contains((string) $s['hata'], 'Geçersiz'), 'enjeksiyonlu adres doğrulanmadı: ' . (string) $s['hata']);
});

echo "== Hosting / canlı ortam senaryoları\n";
test('taban yol: tüm barındırma biçimleri (DocumentRoot, alan adı kökü, alt klasör)', function (): void {
    esit('', temel_yol_hesapla('/index.php', '/blog'), 'DocumentRoot=public');
    esit('', temel_yol_hesapla('/public/index.php', '/blog?sayfa=2'), 'alan adı kökünde tüm proje + kök .htaccess');
    esit('', temel_yol_hesapla('/public/index.php', '/'), 'alan adı kökü, ana sayfa');
    esit('/portfolyo', temel_yol_hesapla('/portfolyo/public/index.php', '/portfolyo/blog'), 'alt klasör (XAMPP)');
    esit('/portfolyo', temel_yol_hesapla('/portfolyo/public/index.php', '/portfolyo/'), 'alt klasör ana sayfa');
    esit('/portfolyo/public', temel_yol_hesapla('/portfolyo/public/index.php', '/portfolyo/public/blog'), 'public doğrudan çağrılırsa');
    esit('/siteler/benim', temel_yol_hesapla('/siteler/benim/public/index.php', '/siteler/benim/makale/x'), 'derin alt klasör');
});
test('kurulum sihirbazı yalnızca uzun token + sıfır kullanıcıyla açılır', function (): void {
    $uzun = str_repeat('a', 16);
    dogru(App\Core\Kurulum::etkinMi($uzun, 0), 'açılmalıydı');
    dogru(!App\Core\Kurulum::etkinMi('', 0), 'token boşken açıldı');
    dogru(!App\Core\Kurulum::etkinMi('kisa-token', 0), 'kısa token kabul edildi');
    dogru(!App\Core\Kurulum::etkinMi($uzun, 1), 'kullanıcı varken açıldı');
    dogru(!App\Core\Kurulum::etkinMi($uzun, null), 'bilinmeyen durumda açıldı');
});
test('SQL ifade bölücü yorumları atar ve ifadeleri ayırır', function (): void {
    $s = App\Core\Kurulum::sqlIfadeleri("-- yorum\nCREATE TABLE a (id INT);\n\n-- baska\nINSERT INTO a VALUES (1);\nINSERT INTO a VALUES (2);\n");
    esit(3, count($s));
    dogru(str_starts_with($s[0], 'CREATE TABLE a') && !str_contains(implode('', $s), 'yorum'), 'yorum kaldı');
    $gercek = App\Core\Kurulum::sqlIfadeleri((string) file_get_contents(KOK . '/database/schema.sql'));
    dogru(count($gercek) >= 14, 'schema.sql ifadeleri bulunamadı: ' . count($gercek));
});
test('canlı ortam kontrolü: tehlikeli yapılandırmaları işaretler', function (): void {
    $temel = ['ortam' => 'production', 'debug' => false, 'url' => 'https://ornek.com', 'istek_host' => 'ornek.com', 'https' => true,
        'proxy_basligi' => false, 'guvenilir_proxy' => false, 'oturum_secure' => true, 'hsts' => true, 'install_var' => false,
        'seed_var' => false, 'kurulum_token' => false, 'totp_aktif' => true, 'depolama_yazilabilir' => true,
        'php_yukleme_bayt' => 64 * 1048576, 'video_siniri_bayt' => 50 * 1048576, 'gd' => true, 'zip' => true, 'smtp' => true,
        'son_yedek' => 1000, 'simdi' => 1000 + 3600, 'php' => '8.3'];
    $seviyeler = static fn(array $o): array => array_map(static fn($s) => $s['seviye'] . ':' . $s['baslik'], App\Core\Saglik::degerlendir($o));
    $var = static fn(array $liste, string $seviye, string $parca): bool => count(array_filter($liste, static fn($x) => str_starts_with($x, $seviye . ':') && str_contains($x, $parca))) > 0;

    $iyi = $seviyeler($temel);
    dogru(!$var($iyi, 'kritik', '') && !$var($iyi, 'uyari', ''), 'sağlıklı yapılandırma uyarı verdi: ' . implode(' | ', $iyi));

    dogru($var($seviyeler(['debug' => true] + $temel), 'kritik', 'APP_DEBUG'), 'canlıda debug kritik değil');
    dogru($var($seviyeler(['https' => false] + $temel), 'kritik', 'HTTPS'), 'HTTPS yokken kritik değil');
    dogru($var($seviyeler(['oturum_secure' => false] + $temel), 'uyari', 'SESSION_SECURE'), 'SESSION_SECURE uyarısı yok');
    dogru($var($seviyeler(['proxy_basligi' => true, 'https' => true] + $temel), 'uyari', 'Proxy'), 'proxy uyarısı yok');
    dogru($var($seviyeler(['guvenilir_proxy' => true, 'proxy_basligi' => false] + $temel), 'uyari', 'GUVENILIR_PROXY'), 'gereksiz proxy güveni uyarısı yok');
    dogru($var($seviyeler(['install_var' => true] + $temel), 'uyari', 'betikleri'), 'install.php uyarısı yok');
    dogru($var($seviyeler(['kurulum_token' => true] + $temel), 'uyari', 'KURULUM_TOKEN'), 'token uyarısı yok');
    dogru($var($seviyeler(['depolama_yazilabilir' => false] + $temel), 'kritik', 'storage'), 'yazılamayan storage kritik değil');
    dogru($var($seviyeler(['php_yukleme_bayt' => 2 * 1048576] + $temel), 'uyari', 'yükleme sınırı'), 'PHP sınırı uyarısı yok');
    dogru($var($seviyeler(['url' => 'https://baska.com'] + $temel), 'uyari', 'APP_URL'), 'APP_URL uyumsuzluğu uyarısı yok');
    dogru($var($seviyeler(['son_yedek' => 0] + $temel), 'uyari', 'yedek'), 'yedek uyarısı yok');

    // Yerel geliştirme: debug ve https yokluğu kritik sayılmaz
    $yerel = $seviyeler(['debug' => true, 'https' => false, 'url' => 'http://localhost:8000', 'istek_host' => 'localhost:8000', 'oturum_secure' => false, 'hsts' => false, 'totp_aktif' => false, 'son_yedek' => 0] + $temel);
    dogru(!$var($yerel, 'kritik', ''), 'yerel geliştirme kritik sayıldı: ' . implode(' | ', $yerel));
});
test('bayt(): php.ini boyut yazımları', function (): void {
    esit(64 * 1048576, App\Core\Saglik::bayt('64M'));
    esit(2 * 1073741824, App\Core\Saglik::bayt('2G'));
    esit(512 * 1024, App\Core\Saglik::bayt('512K'));
    esit(PHP_INT_MAX, App\Core\Saglik::bayt('-1'));
});

echo "\n== v1.3 Güvence\n";
test('v1.3 rotaları eşleşir; jeton/yedek adı tipleri sıkı', function (): void {
    $r = new Yonlendirici('');
    (require KOK . '/config/rotalar.php')($r);
    $jeton = str_repeat('ab', 32);
    esit(200, $r->eslestir('GET', '/admin/sifre-sifirla/' . $jeton)['durum']);
    esit(404, $r->eslestir('GET', '/admin/sifre-sifirla/' . substr($jeton, 1))['durum'], '63 hane');
    esit(404, $r->eslestir('GET', '/admin/sifre-sifirla/' . strtoupper($jeton))['durum'], 'büyük harf');
    foreach (['/admin/sifremi-unuttum', '/admin/yedekler', '/admin/guvenlik', '/admin/sayfalar', '/sayfa/gizlilik'] as $y) {
        esit(200, $r->eslestir('GET', $y)['durum'], $y);
    }
    esit(200, $r->eslestir('GET', '/admin/yedekler/portfolyo-20260101-030000.sql.gz/indir')['durum']);
    esit(404, $r->eslestir('GET', '/admin/yedekler/.env/indir')['durum'], 'noktayla başlayan ad');
    esit(404, $r->eslestir('GET', '/admin/yedekler/a/indir')['durum'], 'çok kısa ad');
    esit(404, $r->eslestir('GET', '/bakim/kisa')['durum'], 'kısa bakım anahtarı');
    esit(405, $r->eslestir('GET', '/admin/yedekler/al')['durum'], 'yedek alma yalnızca POST');
    esit(405, $r->eslestir('GET', '/admin/guvenlik/oturumlar/digerleri/kapat')['durum']);
});
test('SifreSifirlama: biçimsiz jeton veritabanına gitmeden reddedilir', function (): void {
    foreach (['', 'abc', str_repeat('g', 64), str_repeat('a', 63), str_repeat('a', 65), '../../etc/passwd'] as $j) {
        esit(null, App\Core\SifreSifirlama::dogrula($j), 'jeton: ' . $j);
    }
});
test('Yedek::dogrula: yedek olmayan ve zararlı SQL içeren dosyalar reddedilir', function (): void {
    $dizin = sys_get_temp_dir() . '/portfolyo-test-' . bin2hex(random_bytes(4));
    mkdir($dizin);
    $baslik = "-- Portfolyo yedeği · 2026-01-01 00:00:00 UTC\n-- sema_surumu: " . App\Core\Kurulum::SEMA_SURUMU . "\nSET NAMES utf8mb4;\n";
    $vakalar = [
        'baslik-yok'    => "SELECT 1;\n",
        'select'        => $baslik . "SELECT * FROM kullanicilar;\n",
        'drop-db'       => $baslik . "DROP DATABASE portfolyo;\n",
        'yabanci-tablo' => $baslik . "DROP TABLE IF EXISTS `mysql.user`;\n",
        'grant'         => $baslik . "GRANT ALL ON *.* TO 'x'@'%';\n",
        'altsorgu'      => $baslik . "INSERT INTO `ayarlar` VALUES ('a', (SELECT password FROM kullanicilar));\n",
        'dosya-yaz'     => $baslik . "SELECT 'x' INTO OUTFILE '/tmp/x';\n",
        'yigin'         => $baslik . "SET FOREIGN_KEY_CHECKS=0; DROP TABLE x;\n",
        'yeni-tablo'    => $baslik . "CREATE TABLE `evil` (id INT);\n",
        'yonetici-yok'  => $baslik . "DROP TABLE IF EXISTS `ayarlar`;\n",
    ];
    foreach ($vakalar as $ad => $icerik) {
        $yol = $dizin . '/' . $ad . '.sql';
        file_put_contents($yol, $icerik);
        $r = App\Core\Yedek::dogrula($yol);
        dogru($r['ok'] === false, 'kabul edildi: ' . $ad);
        dogru($r['hatalar'] !== [], 'hata mesajı yok: ' . $ad);
        unlink($yol);
    }
    rmdir($dizin);
});
test('Yedek::yol: yol gezdirme ve geçersiz adlar reddedilir', function (): void {
    foreach (['../.env', '..\\.env', '.env', 'a', 'x.php', 'portfolyo-1.sql.gz/../../.env', "portfolyo-1.sql.gz\0.php"] as $ad) {
        esit(null, App\Core\Yedek::yol($ad), 'ad: ' . $ad);
    }
});
test('SayfaSablonlari: tüm şablonlar site bilgisiyle dolar, yer tutucu kalmaz', function (): void {
    foreach (array_keys(App\Core\SayfaSablonlari::liste()) as $anahtar) {
        $m = App\Core\SayfaSablonlari::icerik($anahtar, ['site_baslik' => 'Örnek Site', 'site_eposta' => 'ben@ornek.test', 'mesaj_saklama_gun' => '90']);
        dogru(str_contains($m, 'Örnek Site') && str_contains($m, 'ben@ornek.test'), 'site bilgisi yok: ' . $anahtar);
        dogru(!str_contains($m, '{{') && !str_contains($m, '%s') && !str_contains($m, '$site'), 'yer tutucu kaldı: ' . $anahtar);
        dogru(mb_strlen($m) > 120, 'şablon çok kısa: ' . $anahtar);
    }
});
test('Denetim::etiket: bilinen kod Türkçe, bilinmeyen kod olduğu gibi', function (): void {
    esit('Giriş yapıldı', App\Core\Denetim::etiket('giris.basarili'));
    esit('bilinmeyen.olay', App\Core\Denetim::etiket('bilinmeyen.olay'));
});
test('ayar tanımları: secim seçenekleri ve sayı aralıkları geçerli', function (): void {
    foreach (App\Models\Ayar::GRUPLAR as $g) {
        foreach ($g['alanlar'] as $anahtar => $a) {
            if ($a[1] === 'secim') {
                dogru(is_array($a[5] ?? null) && $a[5] !== [], 'secim seçeneği yok: ' . $anahtar);
            }
            if ($a[1] === 'number' && isset($a[5])) {
                dogru(is_int($a[5][0]) && is_int($a[5][1]) && $a[5][0] <= $a[5][1], 'aralık hatalı: ' . $anahtar);
            }
        }
    }
    foreach (['kvkk_sayfa', 'mesaj_saklama_gun', 'ip_saklama', 'zorunlu_2fa', 'yeni_cihaz_bildirimi', 'otomatik_yedek_gun', 'yedek_eposta', 'yedek_ftp', 'yedek_webdav', 'yedek_medya_dahil'] as $k) {
        dogru(isset(App\Models\Ayar::alanlar()[$k]), 'ayar tanımı yok: ' . $k);
    }
});
test('şema: v1.3 tabloları ve varsayılan ayarlar schema.sql içinde', function (): void {
    $sql = (string) file_get_contents(KOK . '/database/schema.sql');
    foreach (['denetim_gunlugu', 'sifre_sifirlama', 'oturumlar', 'sayfalar'] as $t) {
        dogru(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `' . $t . '`'), 'tablo yok: ' . $t);
    }
    foreach (array_keys(App\Models\Ayar::alanlar()) as $k) {
        dogru(str_contains($sql, "('" . $k . "'"), 'varsayılan ayar yok: ' . $k);
    }
});

echo "\n== v1.4 Tanıtım ve özgeçmiş\n";
test('CvYardimci: ay/yıl biçimi, aralık ve nokta seviyesi', function (): void {
    esit('Mart 2021', App\Core\CvYardimci::ayYil('2021-03'));
    esit('', App\Core\CvYardimci::ayYil('2021-13'), 'geçersiz ay');
    esit('', App\Core\CvYardimci::ayYil(null));
    esit('Ocak 2020 – Aralık 2022', App\Core\CvYardimci::aralik('2020-01', '2022-12', false));
    esit('Ocak 2020 – Devam ediyor', App\Core\CvYardimci::aralik('2020-01', '2022-12', true), 'devam eden bitişi yok sayar');
    esit('Ocak 2020', App\Core\CvYardimci::aralik('2020-01', null, false));
    esit(0, App\Core\CvYardimci::nokta(null));
    esit(1, App\Core\CvYardimci::nokta(1));
    esit(5, App\Core\CvYardimci::nokta(100));
    esit(3, App\Core\CvYardimci::nokta(41));
});
test('CvYardimci::vcard: kaçışlar, CRLF enjeksiyonu yok, 75 bayt katlama', function (): void {
    $v = App\Core\CvYardimci::vcard([
        'ad' => "Ayşe Yılmaz\r\nEND:VCARD", 'unvan' => 'Dev; Ops, Lead', 'eposta' => 'a@b.test',
        'telefon' => '+90 555 000 00 00', 'url' => 'https://ornek.test/', 'konum' => 'İstanbul, TR',
    ]);
    dogru(str_starts_with($v, "BEGIN:VCARD\r\nVERSION:3.0\r\n"), 'başlık');
    dogru(str_ends_with($v, "END:VCARD\r\n"), 'bitiş');
    esit(1, substr_count($v, "\r\nEND:VCARD"), 'ad içindeki END:VCARD ayrı satır olarak enjekte edilemez');
    dogru(str_contains($v, 'TITLE:Dev\\; Ops\\, Lead'), 'noktalı virgül ve virgül kaçışlı');
    dogru(str_contains($v, 'ADR;TYPE=WORK:;;İstanbul\\, TR;;;;'), 'adres ayırıcıları korunur');
    $uzun = App\Core\CvYardimci::vcard(['ad' => 'X', 'not' => str_repeat('ş', 200)]);
    foreach (explode("\r\n", $uzun) as $satir) {
        dogru(strlen($satir) <= 75, 'satır 75 bayttan uzun: ' . strlen($satir));
        dogru(mb_check_encoding($satir, 'UTF-8'), 'katlama UTF-8 karakterini bölmemeli');
    }
});
test('CvYardimci::icindekiler: yalnızca h2/h3 ve kimlikli başlıklar', function (): void {
    $html = '<h2 id="giris">Giriş</h2><p>x</p><h3 id="alt-baslik">Alt <em>başlık</em></h3><h4 id="d">Dört</h4><h2>Kimliksiz</h2><h2 id="son">A &amp; B</h2>';
    $l = App\Core\CvYardimci::icindekiler($html);
    esit(3, count($l));
    esit('Alt başlık', $l[1]['metin']);
    esit(3, $l[1]['seviye']);
    esit('A & B', $l[2]['metin'], 'varlıklar çözülür; çıktıda e() ile yeniden kaçışlanır');
    esit([], App\Core\CvYardimci::icindekiler(App\Core\Markdown::cevir('Sadece paragraf')));
    $md = App\Core\CvYardimci::icindekiler(App\Core\Markdown::cevir("# Bir\n\nmetin\n\n## İki\n\nmetin\n\n# Üç"));
    esit(3, count($md), 'Markdown # → h2, ## → h3');
});
test('CvYardimci::teknolojiler: tekilleştirir, sınırlar, etiketi ayıklar', function (): void {
    esit(['PHP', 'MySQL'], App\Core\CvYardimci::teknolojiler('PHP, MySQL ,php,, '));
    esit([], App\Core\CvYardimci::teknolojiler(null));
    esit([], App\Core\CvYardimci::teknolojiler('   '));
    esit(['alert(1)'], App\Core\CvYardimci::teknolojiler('<b>alert(1)</b>'), 'etiket temizlenir');
    esit([], App\Core\CvYardimci::teknolojiler(str_repeat('x', 31)), '30 karakterden uzun atılır');
    esit(12, count(App\Core\CvYardimci::teknolojiler(implode(',', range(1, 40)))), 'en çok 12');
    esit(null, App\Core\CvYardimci::teknolojiMetni(''));
    esit('Go, Rust', App\Core\CvYardimci::teknolojiMetni('Go,Rust'));
});
test('CvOgesi: türler tutarlı; geçersiz tür reddedilir', function (): void {
    foreach (App\Models\CvOgesi::TURLER as $k => $t) {
        dogru(isset($t['ad'], $t['tekil'], $t['baslik'], $t['alanlar']), 'eksik tanım: ' . $k);
    }
    dogru(App\Models\CvOgesi::turGecerli('deneyim'), 'deneyim geçerli');
    dogru(!App\Models\CvOgesi::turGecerli("deneyim' OR 1=1"), 'enjeksiyon dizgisi geçersiz');
    dogru(!App\Models\CvOgesi::turGecerli(''), 'boş geçersiz');
    esit([], App\Models\CvOgesi::yayinda('olmayan'), 'geçersiz tür sorguya girmez');
});
test('v1.4 rotaları eşleşir; ayarlar ve şema hazır', function (): void {
    $r = new Yonlendirici('');
    (require KOK . '/config/rotalar.php')($r);
    foreach (['/cv', '/cv/kartvizit', '/admin/cv', '/admin/cv/yeni', '/admin/cv/5/duzenle'] as $y) {
        esit(200, $r->eslestir('GET', $y)['durum'], $y);
    }
    foreach (['/admin/cv/5/guncelle', '/admin/cv/5/sil', '/admin/cv/5/gorunur', '/admin/cv/yeni'] as $y) {
        esit(200, $r->eslestir('POST', $y)['durum'], 'POST ' . $y);
    }
    esit(405, $r->eslestir('GET', '/admin/cv/5/sil')['durum'], 'silme yalnızca POST');
    esit(404, $r->eslestir('GET', '/admin/cv/abc/duzenle')['durum'], 'sayısal olmayan kimlik');
    foreach (['cv_yayin', 'cv_ad', 'cv_unvan', 'cv_ozet', 'cv_eposta', 'cv_konum', 'cv_telefon', 'cv_vcard', 'musait', 'musait_metin'] as $k) {
        dogru(isset(App\Models\Ayar::alanlar()[$k]), 'ayar tanımı yok: ' . $k);
    }
    $sql = (string) file_get_contents(KOK . '/database/schema.sql');
    $mig = (string) file_get_contents(KOK . '/database/migrasyon.php');
    dogru(str_contains($sql, 'CREATE TABLE IF NOT EXISTS `cv_ogeleri`'), 'cv_ogeleri tablosu');
    foreach (['teknolojiler', 'problem', 'cozum', 'sonuc'] as $c) {
        dogru(str_contains($sql, '`' . $c . '`'), 'projeler.' . $c . ' schema.sql içinde yok');
        dogru(str_contains($mig, "'" . $c . "'"), 'migrasyonda yok: ' . $c);
    }
    dogru(App\Core\Kurulum::SEMA_SURUMU >= 4, 'şema sürümü artırılmalı');
});

echo "\n";
if ($kalan === []) {
    echo "TÜM TESTLER GEÇTİ ({$gecen})\n";
    exit(0);
}
echo count($kalan) . " TEST BAŞARISIZ, {$gecen} geçti:\n";
foreach ($kalan as $k) {
    echo " - {$k}\n";
}
exit(1);
