<?php
/** @var list<array{ok:bool,ad:string,not:string}> $kontrol */
$hazir = true;
foreach ($kontrol as $k) {
    $hazir = $hazir && $k['ok'];
}
$hatalar = $hatalar ?? [];
$eski = $eski ?? [];
?>
<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>Kurulum sihirbazı</h1>
            <p class="soluk">Veritabanı tablolarını oluşturur ve ilk yönetici hesabını açar.</p>
        </div>

        <ul class="kontrol-listesi" aria-label="Ön kontrol">
            <?php foreach ($kontrol as $k): ?>
                <li class="<?= $k['ok'] ? 'kontrol-ok' : 'kontrol-hata' ?>">
                    <span aria-hidden="true"><?= $k['ok'] ? '✓' : '✗' ?></span>
                    <?php $ipucu = $k['ok'] ? ($k['bilgi'] ?? '') : $k['not']; ?>
                    <span><?= e($k['ad']) ?><?php if ($ipucu !== ''): ?><small class="soluk"> — <?= e($ipucu) ?></small><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!empty($hata)): ?><div class="uyari uyari-hata" role="alert"><?= e((string) $hata) ?></div><?php endif; ?>

        <?php if (!$hazir): ?>
            <div class="uyari uyari-hata" role="alert">Yukarıdaki ✗ işaretli maddeleri düzeltip sayfayı yenileyin.</div>
        <?php else: ?>
            <form class="form" method="post" action="<?= e(url('/kurulum')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="alan">
                    <label for="kurulum_anahtari">Kurulum anahtarı</label>
                    <input type="password" id="kurulum_anahtari" name="kurulum_anahtari" required maxlength="200" autocomplete="off">
                    <p class="soluk">.env dosyanızdaki <code>KURULUM_TOKEN</code> değeri.</p>
                </div>
                <div class="alan">
                    <label for="ad_soyad">Adınız soyadınız</label>
                    <input type="text" id="ad_soyad" name="ad_soyad" required maxlength="120" value="<?= e($eski['ad_soyad'] ?? '') ?>">
                    <?php if (!empty($hatalar['ad_soyad'])): ?><p class="alan-hata" role="alert"><?= e($hatalar['ad_soyad']) ?></p><?php endif; ?>
                </div>
                <div class="alan">
                    <label for="eposta">E-posta (giriş adınız)</label>
                    <input type="email" id="eposta" name="eposta" required maxlength="180" value="<?= e($eski['eposta'] ?? '') ?>">
                    <?php if (!empty($hatalar['eposta'])): ?><p class="alan-hata" role="alert"><?= e($hatalar['eposta']) ?></p><?php endif; ?>
                </div>
                <div class="alan">
                    <label for="parola">Parola</label>
                    <input type="password" id="parola" name="parola" required maxlength="72" autocomplete="new-password">
                    <p class="soluk">En az 12 karakter; büyük/küçük harf, rakam ve özel karakter.</p>
                    <?php if (!empty($hatalar['parola'])): ?><p class="alan-hata" role="alert"><?= e($hatalar['parola']) ?></p><?php endif; ?>
                </div>
                <div class="alan">
                    <label for="parola_tekrar">Parola (tekrar)</label>
                    <input type="password" id="parola_tekrar" name="parola_tekrar" required maxlength="72" autocomplete="new-password">
                    <?php if (!empty($hatalar['parola_tekrar'])): ?><p class="alan-hata" role="alert"><?= e($hatalar['parola_tekrar']) ?></p><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-tam" type="submit">Kurulumu tamamla</button>
            </form>
        <?php endif; ?>
    </div>
</main>
