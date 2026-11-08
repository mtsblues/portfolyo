<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>Yeni parola belirle</h1>
        </div>
        <?php if (!empty($hata)): ?><div class="uyari uyari-hata" role="alert"><?= e((string) $hata) ?></div><?php endif; ?>
        <?php if (!empty($gecersiz)): ?>
            <div class="uyari uyari-hata" role="alert">Bu bağlantı geçersiz, süresi dolmuş veya daha önce kullanılmış. Yeni bir sıfırlama isteyin.</div>
            <p class="giris-alt"><a href="<?= e(url('/admin/sifremi-unuttum')) ?>">Yeni bağlantı iste</a></p>
        <?php else: ?>
            <p class="soluk"><?= e((string) ($eposta ?? '')) ?> hesabı için yeni parola girin (en az 12 karakter; büyük/küçük harf, rakam ve özel karakter).</p>
            <form class="form" method="post" action="<?= e(url('/admin/sifre-sifirla/' . $jeton)) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="alan">
                    <label for="parola">Yeni parola</label>
                    <input type="password" id="parola" name="parola" required minlength="12" maxlength="72" autocomplete="new-password" autofocus>
                </div>
                <div class="alan">
                    <label for="parola_tekrar">Yeni parola (tekrar)</label>
                    <input type="password" id="parola_tekrar" name="parola_tekrar" required minlength="12" maxlength="72" autocomplete="new-password">
                </div>
                <button class="btn btn-primary btn-tam" type="submit">Parolayı güncelle</button>
            </form>
        <?php endif; ?>
    </div>
</main>
