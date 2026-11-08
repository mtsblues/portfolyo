<?php /** @var string $icerik */ ?>
<!doctype html>
<html lang="tr" data-tema="acik">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($sayfaBaslik ?? 'Yönetim') ?></title>
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
    <link rel="stylesheet" href="<?= e(asset('css/tema.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="giris-sayfasi">
<?= $icerik ?>
<script src="<?= e(asset('js/uygulama.js')) ?>" defer></script>
</body>
</html>
