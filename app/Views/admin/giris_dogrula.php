<?php
$hata = \App\Core\Oturum::flashGelen('hata');
?>
<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>İki adımlı doğrulama</h1>
            <p class="soluk">Doğrulayıcı uygulamanızdaki 6 haneli kodu girin. Telefonunuza erişemiyorsanız bir yedek kod kullanın.</p>
        </div>
        <?php if (!empty($kilit)): ?><div class="uyari uyari-hata" role="alert"><?= e($kilit) ?></div><?php endif; ?>
        <?php if (is_string($hata) && $hata !== ''): ?><div class="uyari uyari-hata" role="alert"><?= e($hata) ?></div><?php endif; ?>
        <form class="form" method="post" action="<?= e(url('/admin/giris/dogrula')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="alan">
                <label for="kod">Doğrulama kodu veya yedek kod</label>
                <input type="text" id="kod" name="kod" class="kod-girdi" required maxlength="12" inputmode="text" autocomplete="one-time-code" autofocus>
            </div>
            <button class="btn btn-primary btn-tam" type="submit">Doğrula</button>
        </form>
        <p class="giris-alt"><a href="<?= e(url('/admin/giris')) ?>">← Girişe dön</a></p>
    </div>
</main>
