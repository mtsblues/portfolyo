<?php
$hata = \App\Core\Oturum::flashGelen('hata');
$basari = \App\Core\Oturum::flashGelen('basari');
?>
<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>Parolamı unuttum</h1>
            <p class="soluk">Yönetici e-posta adresinizi girin; kayıtlıysa tek kullanımlık bir sıfırlama bağlantısı göndeririz.</p>
        </div>
        <?php if (is_string($hata) && $hata !== ''): ?><div class="uyari uyari-hata" role="alert"><?= e($hata) ?></div><?php endif; ?>
        <?php if (is_string($basari) && $basari !== ''): ?><div class="uyari uyari-basari" role="status"><?= e($basari) ?></div><?php endif; ?>
        <?php if (empty($epostaVar)): ?>
            <div class="uyari uyari-hata" role="alert">
                Bu sitede e-posta ile sıfırlama kapalı (SMTP ayarı veya <code>APP_URL</code> tanımlı değil).
                Sunucudan şu komutla parolayı sıfırlayabilirsiniz: <code>php bin/parola-sifirla.php eposta@ornek.com</code>
            </div>
        <?php else: ?>
        <form class="form" method="post" action="<?= e(url('/admin/sifremi-unuttum')) ?>">
            <?= csrf_field() ?>
            <div class="alan">
                <label for="eposta">E-posta</label>
                <input type="email" id="eposta" name="eposta" required maxlength="180" autocomplete="username" autofocus>
            </div>
            <button class="btn btn-primary btn-tam" type="submit">Sıfırlama bağlantısı gönder</button>
        </form>
        <?php endif; ?>
        <p class="giris-alt"><a href="<?= e(url('/admin/giris')) ?>">← Girişe dön</a></p>
    </div>
</main>
