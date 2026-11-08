<?php
/** @var string $icerik */
/** @var array $kullanici */
$menu = [
    ['/admin', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
    ['/admin/makaleler', 'Makaleler', 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h5'],
    ['/admin/projeler', 'Projeler', 'M3 7h18v13H3zM8 7V4h8v3'],
    ['/admin/kategoriler', 'Kategoriler', 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
    ['/admin/etiketler', 'Etiketler', 'M3 12l9-9h9v9l-9 9z'],
    ['/admin/medya', 'Medya', 'M3 5h18v14H3zM3 16l5-5 4 4 3-3 6 6M9 9.5a1.5 1.5 0 100-.01'],
    ['/admin/mesajlar', 'Mesajlar', 'M3 5h18v14H3zM3 7l9 7 9-7'],
    ['/admin/guvenlik', 'Güvenlik', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z'],
];
if (($kullanici['rol'] ?? '') === 'admin') {
    $menu[] = ['/admin/kullanicilar', 'Kullanıcılar', 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21c0-4 4-6 8-6s8 2 8 6'];
    $menu[] = ['/admin/sayfalar', 'Sayfalar', 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6'];
    $menu[] = ['/admin/yedekler', 'Yedekleme', 'M12 3v12M7 10l5 5 5-5M4 19h16'];
    $menu[] = ['/admin/ayarlar', 'Ayarlar', 'M12 8a4 4 0 100 8 4 4 0 000-8zM12 2v3M12 19v3M2 12h3M19 12h3'];
}
?>
<!doctype html>
<html lang="tr" data-tema="acik">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($sayfaBaslik ?? 'Yönetim') . ' · Yönetim') ?></title>
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script nonce="<?= e(nonce()) ?>">
        (function () {
            try {
                var t = localStorage.getItem('tema');
                if (t !== 'koyu' && t !== 'acik') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'koyu' : 'acik';
                }
                document.documentElement.setAttribute('data-tema', t);
            } catch (e) {}
        })();
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tema.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin" data-taban="<?= e(temel_yol()) ?>">
<div class="admin-kabuk">
    <aside class="yan-menu" id="yan-menu" aria-label="Yönetim menüsü">
        <a class="logo" href="<?= e(url('/admin')) ?>"><span class="logo-isaret" aria-hidden="true"></span>Yönetim</a>
        <nav>
            <?php foreach ($menu as [$yol, $etiket, $ikon]): ?>
                <a href="<?= e(url($yol)) ?>"<?= menu_aktif($yol === '/admin' ? '/__yok__' : $yol) ?><?= ($yol === '/admin' && gecerli_yol() === '/admin') ? ' aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($ikon) ?>"/></svg>
                    <span><?= e($etiket) ?></span>
                    <?php if ($yol === '/admin/mesajlar' && ($okunmamis ?? 0) > 0): ?><em class="sayac"><?= (int) $okunmamis ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <a class="siteye-don" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Siteyi görüntüle ↗</a>
    </aside>
    <div class="admin-ana">
        <header class="ust-cubuk">
            <button type="button" class="ikon-dugme menu-dugme" id="yan-menu-dugme" aria-controls="yan-menu" aria-expanded="false" aria-label="Menüyü aç">☰</button>
            <h1 class="sayfa-baslik"><?= e($sayfaBaslik ?? '') ?></h1>
            <div class="ust-sag">
                <button type="button" class="ikon-dugme" id="tema-dugme" aria-label="Temayı değiştir">◐</button>
                <a class="kullanici-adi" href="<?= e(url('/admin/profil')) ?>" title="Profil, parola ve iki adımlı doğrulama"><?= e($kullanici['ad_soyad'] ?? '') ?></a>
                <form method="post" action="<?= e(url('/admin/cikis')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-kucuk">Çıkış</button>
                </form>
            </div>
        </header>
        <main class="admin-icerik" id="ana-icerik">
            <?php partial('mesajlar'); ?>
            <?= $icerik ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/uygulama.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
