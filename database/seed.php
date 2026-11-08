<?php
declare(strict_types=1);

/**
 * Ornek (seed) veri olusturucu — YALNIZCA komut satirindan calisir.
 *
 *   php database/seed.php            Anasayfa, projeler, blog akislarini gosterecek ornek veriyi ekler
 *   php database/seed.php --temizlik once kullanici/ayar haric icerigi silip yeniden ekler
 *
 * GUVENLIK: Yalnizca GELISTIRME/sunum icindir. Canlida calistirmayin.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnizca komut satirindan calistirilabilir.\n");
}

define('KOK', dirname(__DIR__));
require KOK . '/app/bootstrap.php';

use App\Core\Dogrulayici;
use App\Core\Veritabani;
use App\Models\Etiket;
use App\Models\Kategori;
use App\Models\Makale;
use App\Models\Mesaj;
use App\Models\Proje;
use App\Core\Yapilandirma;

function seed_yaz(string $m): void
{
    fwrite(STDOUT, $m . PHP_EOL);
}

function seed_hata(string $m): never
{
    fwrite(STDERR, 'HATA: ' . $m . PHP_EOL);
    exit(1);
}

$secenek = [];
foreach (array_slice($argv ?? [], 1) as $a) {
    if ($a === '--temizlik') {
        $secenek['temizlik'] = true;
    } else {
        seed_hata('Bilinmeyen arguman: ' . $a);
    }
}

$envDosya = KOK . '/.env';
if (!is_file($envDosya) && is_file(KOK . '/config/.env.example')) {
    copy(KOK . '/config/.env.example', $envDosya);
}
Yapilandirma::yukle($envDosya);
Yapilandirma::configAyarla(require KOK . '/config/config.php');

try {
    Veritabani::baglanti();
} catch (Throwable $e) {
    seed_hata($e->getMessage() . ' — once `php database/install.php` calistirin.');
}

try {
    if (!empty($secenek['temizlik'])) {
        Veritabani::calistir('SET FOREIGN_KEY_CHECKS=0');
        foreach (['makale_etiket', 'makaleler', 'etiketler', 'kategoriler', 'projeler', 'mesajlar'] as $tablo) {
            Veritabani::calistir("TRUNCATE `$tablo`");
        }
        Veritabani::calistir('SET FOREIGN_KEY_CHECKS=1');
        seed_yaz('• Icerik tablolari temizlendi (kullanici/ayar sakli).');
    }

    $toplamKullanici = (int) Veritabani::skaler('SELECT COUNT(*) FROM kullanicilar');
    if ($toplamKullanici < 1) {
        seed_yaz('NOT: kullanici tablosu bos. Once php database/install.php ile yonetici olusturun.');
    }
    $yazid = (int) Veritabani::skaler('SELECT COALESCE(MIN(id),1) FROM kullanicilar');

    $katIds = [];
    foreach ([
        ['Docker ve Sunucular', 'docker-sunucular', 'Konteynerler, Linux sunucu yonetimi ve altyapi notlari.'],
        ['PHP ve Laravel', 'php-laravel', 'Kok PHP, Laravel ve genel backend dersleri.'],
        ['Siber Guvenlik', 'guvenlik', 'Savunma, denetim, sertifikasyon ve gunluk guvenlik pratikleri.'],
        ['Otomasyon ve PowerShell', 'otomasyon-powershell', 'Scriptler, zaman kazandiran otomasyonlar, Windows/Linux araclari.'],
    ] as $kategori) {
        $ad = $kategori[0];
        $slug = $kategori[1];
        $aciklama = $kategori[2];
        $var = Kategori::slugIle($slug);
        $katIds[$slug] = $var !== null ? (int) $var['id'] : Kategori::olustur($ad, $slug, $aciklama);
    }

    $etiketId = static function (string $ad): int {
        $slug = Dogrulayici::slugOlustur($ad);
        $var = Etiket::slugIle($slug);
        return $var !== null ? (int) $var['id'] : Etiket::olustur($ad, $slug);
    };

    $makaleler = [
        [
            'kat' => $katIds['php-laravel'],
            'baslik' => 'Root PHP ile guvenli bir MVC iskeleti kurmak',
            'etiketler' => ['php', 'mvc', 'guvenlik'],
            'ozet' => 'Kok PHP ile Front Controller, PDO prepared statement ve guvenli oturum yonetimini adim adim kuruyoruz.',
            'icerik' => "Yalin PHP ile modern MVC kurmak icin once public/index.php noktasini tek giris yapmak gerekir.\n\n## Front Controller\n\n```text\npublic/index.php\n```\n\n> Onemli: Uygulama kodu web kokunun DISINDA kalmalidir.\n\n### PDO ve prepared statement\n\nSorgulari asla birlestirerek degil, hazir kalip ile calistirin:\n\n```php\n\$stmt = \$pdo->prepare('SELECT * FROM makaleler WHERE slug = ?');\n\$stmt->execute([\$slug]);\n```\n\nKurulum adimlari:\n\n1. .env dosyasini olustur\n2. Veritabanina baglan\n3. Rotalari tanimla\n",
            'yayin' => gmdate('Y-m-d H:i:s', time() - 3600),
        ],
        [
            'kat' => $katIds['guvenlik'],
            'baslik' => 'XSS saldirilarini onlemenin pratik yollari',
            'etiketler' => ['xss', 'guvenlik', 'html'],
            'ozet' => 'htmlspecialchars, CSP ve Markdown guvenligiyle XSS riskini en aza indirin.',
            'icerik' => "Cikti kacislama, XSS savunmasinin temelidir.\n\n## Kacislama\n\n```php\nfunction e(mixed \$deger): string {\n    return htmlspecialchars((string) \$deger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');\n}\n```\n\n| Teknik | Etki |\n|---|---|\n| e() her ciktida | Yansiyan XSS'i onler |\n| CSP script-src 'self' | Inline betigi engeller |\n| Markdown'da once kacislama | Ham HTML'i guvenle sinirlar |\n\n- Girdi degil CIKTI kacislanir.\n- HTML context'e gore kacislama secilir.\n",
            'yayin' => gmdate('Y-m-d H:i:s', time() - 7200),
        ],
        [
            'kat' => $katIds['docker-sunucular'],
            'baslik' => 'Dockerfile Iyi Pratikleri: imaj boyutunu kucultun',
            'etiketler' => ['docker', 'devops', 'server'],
            'ozet' => 'Cok asamali build, .dockerignore ve katman onbelleginden yararlanin.',
            'icerik' => "Imaj boyutunu kucultmek dagitimi hizlandirir.\n\n## Cok asamali build\n\nOrnek olarak bagimliliklari ayri asamada derleyin ve yalnizca uretim gereksinimlerini kopyalayin.\n\n1. Bagimliliklari ayri asamada derleyin\n2. Yalnizca uretim gereksinimlerini kopyalayin\n3. .dockerignore ile gereksiz dosyalari disarda birakin\n\n**Onemli:** katman onbellegini bozmamak icin COPY sirasina dikkat edin.\n",
            'yayin' => gmdate('Y-m-d H:i:s', time() - 10800),
        ],
        [
            'kat' => $katIds['otomasyon-powershell'],
            'baslik' => 'PowerShell ile tek satirda toplu sunucu raporu',
            'etiketler' => ['powershell', 'otomasyon'],
            'ozet' => 'Uzak sunuculara baglanip disk, RAM ve servis durumunu ceken pratik betik.',
            'icerik' => "PowerShell ile hizli bir saglik raporu almak icin basit komutlar kullanabilirsiniz.\n\n```powershell\nGet-ComputerInfo | Select-Object CsName, OsArchitecture, TotalPhysicalMemory\n```\n\nKullanim onerileri:\n\n- Ciktiyi JSON olarak sakla\n- Zamanlanmis gorevle periyodik calistir\n\n**Uyari:** Uretimde degisiklik yapan komutlari once test ortaminda dene.\n",
            'yayin' => gmdate('Y-m-d H:i:s', time() - 14400),
        ],
    ];

    foreach ($makaleler as $v) {
        $slug = Makale::benzersizSlug(Dogrulayici::slugOlustur($v['baslik']));
        $makaleId = Makale::olustur([
            'kategori_id'       => $v['kat'],
            'kullanici_id'      => $yazid,
            'baslik'            => $v['baslik'],
            'slug'              => $slug,
            'ozet'              => $v['ozet'],
            'icerik'            => $v['icerik'],
            'durum'             => 'yayinda',
            'one_cikan'         => 1,
            'okunma_suresi'     => Makale::okunmaSuresi($v['icerik']),
            'kapak_gorsel'      => null,
            'anahtar_kelimeler' => implode(', ', $v['etiketler']),
            'yayin_tarihi'      => $v['yayin'],
        ]);
        Makale::etiketleriSenkronla($makaleId, array_map($etiketId, $v['etiketler']));
    }
    seed_yaz('• ' . count($makaleler) . ' ornek makale eklendi.');

    $projeler = [
        ['aktif', 'Sunucu Izleme Paneli', 'Hafif izleme araci', 'PHP + Swoole', 1, 'https://github.com/ornek/panel'],
        ['devam_ediyor', 'APM Entegrasyon SDK', 'Cok dilli izleme kutuphanesi', 'Go + C', 2, 'https://github.com/ornek/sdk'],
        ['tamamlandi', 'Otomasyon Cercevesi', 'CI/CD icin betik koleksiyonu', 'Python + Bash', 3, 'https://github.com/ornek/otomasyon'],
    ];
    foreach ($projeler as $pr) {
        $durum = $pr[0];
        $baslik = $pr[1];
        $aciklama = $pr[2];
        $teknolojiler = $pr[3];
        $sira = $pr[4];
        $github = $pr[5];
        $slug = Proje::benzersizSlug(Dogrulayici::slugOlustur($baslik));
        Proje::olustur([
            'baslik'            => $baslik,
            'slug'              => $slug,
            'aciklama'          => $aciklama,
            'icerik'            => "## " . $baslik . "\n\n" . $aciklama . "\n\nKullanilan teknolojiler: `" . $teknolojiler . "`.\n\nProje kaynak koduna ve dokumantasyona yukaridaki baglantilardan ulasabilirsiniz.\n",
            'gorsel'            => null,
            'proje_url'         => null,
            'github_url'        => $github,
            'one_cikan'         => 1,
            'durum'             => $durum,
            'sira'              => $sira,
            'tamamlanma_tarihi' => $durum === 'tamamlandi' ? gmdate('Y-m-d') : null,
        ]);
    }
    seed_yaz('• ' . count($projeler) . ' ornek proje eklendi.');

    foreach ([
        ['Deneme Ziyaretci', 'ziyaretci@ornek.com', 'Is birligi teklifi'],
        ['Arastirmaci', 'arastirmaci@ornek.com', 'Makale hakkinda soru'],
    ] as $msj) {
        Mesaj::olustur($msj[0], $msj[1], $msj[2], 'Bu bir ornek mesajdir. Site calisiyor ve mesajlar admin paneline dusuyor.', ip_ikili('127.0.0.1'));
    }
    seed_yaz('• 2 ornek iletisim mesaji eklendi.');

    seed_yaz('✓ Seed tamamlandi.');
} catch (Throwable $e) {
    seed_hata('Seed basarisiz: ' . $e->getMessage());
}
