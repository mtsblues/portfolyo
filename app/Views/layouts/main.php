<?php
/** @var string $icerik */
$baslikMetni = $sayfaBaslik ?? ayar('site_baslik', 'Portfolyo');
$aciklamaMetni = $sayfaAciklama ?? ayar('site_aciklama');
$hljs = is_file(KOK . '/public/assets/vendor/highlight/highlight.min.js');
?>
<!doctype html>
<html lang="tr" data-tema="acik">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($baslikMetni) ?></title>
    <meta name="description" content="<?= e(ozet_kes((string) $aciklamaMetni, 160)) ?>">
    <?php if (!empty($sayfaAnahtar)): ?><meta name="keywords" content="<?= e($sayfaAnahtar) ?>"><?php endif; ?>
    <?php if (!empty($kanonik)): ?><link rel="canonical" href="<?= e($kanonik) ?>"><?php endif; ?>
    <?php
    $ogGorselUrl = !empty($ogGorsel) ? (string) $ogGorsel : mutlak_gorsel(ayar('og_gorsel'));
    ?>
    <meta property="og:site_name" content="<?= e(ayar('site_baslik', 'Portfolyo')) ?>">
    <meta property="og:title" content="<?= e($baslikMetni) ?>">
    <meta property="og:description" content="<?= e(ozet_kes((string) $aciklamaMetni, 200)) ?>">
    <meta property="og:type" content="<?= e($ogTur ?? 'website') ?>">
    <meta property="og:locale" content="tr_TR">
    <?php if (!empty($kanonik)): ?><meta property="og:url" content="<?= e($kanonik) ?>"><?php endif; ?>
    <?php if ($ogGorselUrl !== ''): ?><meta property="og:image" content="<?= e($ogGorselUrl) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $ogGorselUrl !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($baslikMetni) ?>">
    <?php if ($ogGorselUrl !== ''): ?><meta name="twitter:image" content="<?= e($ogGorselUrl) ?>"><?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= e(ayar('site_baslik', 'Portfolyo')) ?>" href="<?= e(url('/rss.xml')) ?>">
    <?php if (!empty($jsonld) && is_array($jsonld)): ?>
    <script type="application/ld+json" nonce="<?= e(nonce()) ?>"><?= jsonld_json($jsonld) ?></script>
    <?php endif; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="color-scheme" content="light dark">
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
    <link rel="preload" href="<?= e(strtok(asset('fonts/inter-latin-wght-normal.woff2'), '?')) ?>" as="font" type="font/woff2" crossorigin>
    <?php if ($hljs): ?><link rel="stylesheet" href="<?= e(asset('vendor/highlight/github-dark.min.css')) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/tema.css')) ?>">
</head>
<body>
<a class="atla" href="#ana-icerik">İçeriğe geç</a>
<?php partial('nav'); ?>
<main id="ana-icerik">
    <?php partial('mesajlar'); ?>
    <?= $icerik ?>
</main>
<?php partial('footer'); ?>
<?php if ($hljs): ?><script src="<?= e(asset('vendor/highlight/highlight.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/dil-ek.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('js/uygulama.js')) ?>" defer></script>
</body>
</html>
