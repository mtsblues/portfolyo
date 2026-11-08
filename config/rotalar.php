<?php
declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\AnaSayfaController;
use App\Controllers\BlogController;
use App\Controllers\IletisimController;
use App\Controllers\KurulumController;
use App\Controllers\ProjeController;
use App\Controllers\YuklemeController;
use App\Core\Yonlendirici;

/** Tüm rota tanımları (docs/SPEC.md §8). */
return static function (Yonlendirici $r): void {
    // Genel site
    $r->get('/', [AnaSayfaController::class, 'index']);
    $r->get('/sitemap.xml', [AnaSayfaController::class, 'sitemap']);
    $r->get('/rss.xml', [AnaSayfaController::class, 'rss']);
    $r->get('/robots.txt', [AnaSayfaController::class, 'robots']);
    $r->get('/hakkimda', [AnaSayfaController::class, 'hakkimda']);
    $r->get('/projeler', [ProjeController::class, 'liste']);
    $r->get('/proje/{slug}', [ProjeController::class, 'detay']);
    $r->get('/blog', [BlogController::class, 'liste']);
    $r->get('/makale/{slug}', [BlogController::class, 'detay']);
    $r->get('/kategori/{slug}', [BlogController::class, 'kategori']);
    $r->get('/etiket/{slug}', [BlogController::class, 'etiket']);
    $r->get('/iletisim', [IletisimController::class, 'index']);
    $r->post('/iletisim/gonder', [IletisimController::class, 'gonder']);
    $r->get('/yuklemeler/{dosya}', [YuklemeController::class, 'goster']);
    $r->get('/sayfa/{slug}', [\App\Controllers\SayfaController::class, 'detay']);
    $r->get('/bakim/{anahtar}', [\App\Controllers\BakimController::class, 'calistir']);

    // Tarayıcıdan kurulum (varsayılan kapalı; KURULUM_TOKEN tanımlıysa ve kullanıcı yoksa açılır)
    $r->get('/kurulum', [KurulumController::class, 'index']);
    $r->post('/kurulum', [KurulumController::class, 'kur']);

    // Yönetim: kimlik doğrulama
    $r->get('/admin/giris', [Admin\GirisController::class, 'index']);
    $r->post('/admin/giris', [Admin\GirisController::class, 'giris']);
    $r->post('/admin/cikis', [Admin\GirisController::class, 'cikis']);
    $r->get('/admin/giris/dogrula', [Admin\GirisController::class, 'dogrulaForm']);
    $r->post('/admin/giris/dogrula', [Admin\GirisController::class, 'dogrula']);

    // Parola sıfırlama (e-posta ile, tek kullanımlık jeton)
    $r->get('/admin/sifremi-unuttum', [Admin\GirisController::class, 'unuttum']);
    $r->post('/admin/sifremi-unuttum', [Admin\GirisController::class, 'unuttumGonder']);
    $r->get('/admin/sifre-sifirla/{jeton}', [Admin\GirisController::class, 'sifirlaForm']);
    $r->post('/admin/sifre-sifirla/{jeton}', [Admin\GirisController::class, 'sifirla']);

    // Profil: parola değiştirme ve iki adımlı doğrulama
    $r->get('/admin/profil', [Admin\ProfilController::class, 'index']);
    $r->post('/admin/profil/parola', [Admin\ProfilController::class, 'parolaDegistir']);
    $r->post('/admin/profil/2fa/baslat', [Admin\ProfilController::class, 'ikiAdimBaslat']);
    $r->post('/admin/profil/2fa/etkinlestir', [Admin\ProfilController::class, 'ikiAdimEtkinlestir']);
    $r->post('/admin/profil/2fa/kapat', [Admin\ProfilController::class, 'ikiAdimKapat']);

    // Yönetim: panel
    $r->get('/admin', [Admin\PanelController::class, 'index']);

    // Makaleler
    $r->get('/admin/makaleler', [Admin\MakaleController::class, 'liste']);
    $r->get('/admin/makale/yeni', [Admin\MakaleController::class, 'yeni']);
    $r->post('/admin/makale/yeni', [Admin\MakaleController::class, 'kaydet']);
    $r->get('/admin/makale/{id}/duzenle', [Admin\MakaleController::class, 'duzenle']);
    $r->post('/admin/makale/{id}/guncelle', [Admin\MakaleController::class, 'guncelle']);
    $r->post('/admin/makale/{id}/sil', [Admin\MakaleController::class, 'sil']);

    // Projeler
    $r->get('/admin/projeler', [Admin\ProjeController::class, 'liste']);
    $r->get('/admin/proje/yeni', [Admin\ProjeController::class, 'yeni']);
    $r->post('/admin/proje/yeni', [Admin\ProjeController::class, 'kaydet']);
    $r->get('/admin/proje/{id}/duzenle', [Admin\ProjeController::class, 'duzenle']);
    $r->post('/admin/proje/{id}/guncelle', [Admin\ProjeController::class, 'guncelle']);
    $r->post('/admin/proje/{id}/sil', [Admin\ProjeController::class, 'sil']);

    // Kategoriler
    $r->get('/admin/kategoriler', [Admin\KategoriController::class, 'liste']);
    $r->post('/admin/kategori/yeni', [Admin\KategoriController::class, 'kaydet']);
    $r->post('/admin/kategori/{id}/guncelle', [Admin\KategoriController::class, 'guncelle']);
    $r->post('/admin/kategori/{id}/sil', [Admin\KategoriController::class, 'sil']);

    // Etiketler
    $r->get('/admin/etiketler', [Admin\EtiketController::class, 'liste']);
    $r->post('/admin/etiket/yeni', [Admin\EtiketController::class, 'kaydet']);
    $r->post('/admin/etiket/{id}/guncelle', [Admin\EtiketController::class, 'guncelle']);
    $r->post('/admin/etiket/{id}/sil', [Admin\EtiketController::class, 'sil']);

    // Mesajlar
    $r->get('/admin/mesajlar', [Admin\MesajController::class, 'liste']);
    $r->post('/admin/mesaj/{id}/okundu', [Admin\MesajController::class, 'okundu']);
    $r->post('/admin/mesaj/{id}/sil', [Admin\MesajController::class, 'sil']);

    // Medya kütüphanesi ve editör uçları
    $r->get('/admin/medya', [Admin\MedyaController::class, 'liste']);
    $r->get('/admin/medya/json', [Admin\MedyaController::class, 'kutuphaneJson']);
    $r->post('/admin/medya/yukle', [Admin\MedyaController::class, 'yukle']);
    $r->post('/admin/medya/{id}/guncelle', [Admin\MedyaController::class, 'guncelle']);
    $r->post('/admin/medya/{id}/sil', [Admin\MedyaController::class, 'sil']);
    $r->post('/admin/onizle', [Admin\MedyaController::class, 'onizle']);

    // Ayarlar
    $r->get('/admin/ayarlar', [Admin\AyarController::class, 'liste']);
    $r->post('/admin/ayarlar/guncelle', [Admin\AyarController::class, 'guncelle']);

    // Serbest sayfalar (KVKK aydınlatma, gizlilik vb.)
    $r->get('/admin/sayfalar', [Admin\SayfaController::class, 'liste']);
    $r->get('/admin/sayfa/yeni', [Admin\SayfaController::class, 'yeni']);
    $r->post('/admin/sayfa/yeni', [Admin\SayfaController::class, 'kaydet']);
    $r->post('/admin/sayfa/sablondan', [Admin\SayfaController::class, 'sablondan']);
    $r->get('/admin/sayfa/{id}/duzenle', [Admin\SayfaController::class, 'duzenle']);
    $r->post('/admin/sayfa/{id}/guncelle', [Admin\SayfaController::class, 'guncelle']);
    $r->post('/admin/sayfa/{id}/sil', [Admin\SayfaController::class, 'sil']);

    // Güvenlik merkezi: oturumlar ve denetim günlüğü
    $r->get('/admin/guvenlik', [Admin\GuvenlikController::class, 'index']);
    $r->post('/admin/guvenlik/oturum/{anahtar}/kapat', [Admin\GuvenlikController::class, 'oturumKapat']);
    $r->post('/admin/guvenlik/oturumlar/digerleri/kapat', [Admin\GuvenlikController::class, 'digerleriniKapat']);

    // Yedekleme ve geri yükleme (yalnızca yönetici)
    $r->get('/admin/yedekler', [Admin\YedekController::class, 'liste']);
    $r->post('/admin/yedekler/al', [Admin\YedekController::class, 'al']);
    $r->post('/admin/yedekler/yukle', [Admin\YedekController::class, 'yukle']);
    $r->get('/admin/yedekler/{yedek}/indir', [Admin\YedekController::class, 'indir']);
    $r->post('/admin/yedekler/{yedek}/sil', [Admin\YedekController::class, 'sil']);
    $r->post('/admin/yedekler/{yedek}/gonder', [Admin\YedekController::class, 'gonder']);
    $r->get('/admin/yedekler/{yedek}/geri-yukle', [Admin\YedekController::class, 'geriYuklemeForm']);
    $r->post('/admin/yedekler/{yedek}/geri-yukle', [Admin\YedekController::class, 'geriYukle']);

    // Kullanıcılar
    $r->get('/admin/kullanicilar', [Admin\KullaniciController::class, 'liste']);
    $r->post('/admin/kullanici/yeni', [Admin\KullaniciController::class, 'kaydet']);
    $r->post('/admin/kullanici/{id}/guncelle', [Admin\KullaniciController::class, 'guncelle']);
    $r->post('/admin/kullanici/{id}/sil', [Admin\KullaniciController::class, 'sil']);
};
