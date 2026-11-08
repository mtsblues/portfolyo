<?php
$hata = \App\Core\Oturum::flashGelen('hata');
$basari = \App\Core\Oturum::flashGelen('basari');
?>
<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>Yönetici girişi</h1>
            <p class="soluk">Devam etmek için oturum açın.</p>
        </div>
        <?php if (!empty($kilit)): ?><div class="uyari uyari-hata" role="alert"><?= e($kilit) ?></div><?php endif; ?>
        <?php if (is_string($hata) && $hata !== ''): ?><div class="uyari uyari-hata" role="alert"><?= e($hata) ?></div><?php endif; ?>
        <?php if (is_string($basari) && $basari !== ''): ?><div class="uyari uyari-basari" role="status"><?= e($basari) ?></div><?php endif; ?>
        <form class="form" method="post" action="<?= e(url('/admin/giris')) ?>" autocomplete="on">
            <?= csrf_field() ?>
            <div class="alan">
                <label for="eposta">E-posta</label>
                <input type="email" id="eposta" name="eposta" required maxlength="180" autocomplete="username" value="<?= e(eski('eposta')) ?>" autofocus>
            </div>
            <div class="alan">
                <label for="parola">Parola</label>
                <input type="password" id="parola" name="parola" required maxlength="1024" autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-tam" type="submit">Giriş yap</button>
        </form>
        <p class="giris-alt"><a href="<?= e(url('/admin/sifremi-unuttum')) ?>">Parolamı unuttum</a></p>
        <p class="giris-alt"><a href="<?= e(url('/')) ?>">← Siteye dön</a>
            <button type="button" class="ikon-dugme" id="tema-dugme" aria-label="Temayı değiştir">◐</button></p>
    </div>
</main>
