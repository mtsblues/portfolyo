<?php
/** @var bool $aktif */
/** @var ?string $kurulumGizli */
/** @var ?string $kurulumUri */
/** @var mixed $yedekKodlar */
/** @var int $kalanYedek */
$alanHata = static function (string $alan): void {
    $h = hata_mesaji($alan);
    if ($h !== '') {
        echo '<p class="alan-hata" role="alert">' . e($h) . '</p>';
    }
};
?>
<section class="kart ayar-grup dar-form">
    <h2>Parolayı değiştir</h2>
    <p class="soluk"><?= e($kullanici['eposta']) ?> hesabı için. En az 12 karakter; büyük/küçük harf, rakam ve özel karakter içermelidir.</p>
    <form class="form" method="post" action="<?= e(url('/admin/profil/parola')) ?>">
        <?= csrf_field() ?>
        <div class="alan">
            <label for="mevcut_parola">Mevcut parola</label>
            <input type="password" id="mevcut_parola" name="mevcut_parola" required maxlength="1024" autocomplete="current-password">
            <?php $alanHata('mevcut_parola'); ?>
        </div>
        <div class="form-satir">
            <div class="alan">
                <label for="parola">Yeni parola</label>
                <input type="password" id="parola" name="parola" required maxlength="72" autocomplete="new-password">
                <?php $alanHata('parola'); ?>
            </div>
            <div class="alan">
                <label for="parola_tekrar">Yeni parola (tekrar)</label>
                <input type="password" id="parola_tekrar" name="parola_tekrar" required maxlength="72" autocomplete="new-password">
                <?php $alanHata('parola_tekrar'); ?>
            </div>
        </div>
        <div class="form-eylem"><button class="btn btn-primary" type="submit">Parolayı güncelle</button></div>
    </form>
</section>

<section class="kart ayar-grup dar-form">
    <h2>İki adımlı doğrulama (2FA)</h2>

    <?php if (is_array($yedekKodlar) && $yedekKodlar !== []): ?>
        <div class="uyari uyari-basari" role="status">
            <strong>Yedek kodlarınız — yalnızca şimdi gösterilir.</strong>
            Telefonunuza erişemediğinizde her biri bir kez kullanılabilir. Güvenli bir yere (parola yöneticisi) kaydedin.
        </div>
        <ul class="yedek-kodlar">
            <?php foreach ($yedekKodlar as $kod): ?><li><?= e((string) $kod) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($aktif): ?>
        <p>Durum: <span class="rozet rozet-yayinda">Açık</span> · Kalan yedek kod: <strong><?= (int) $kalanYedek ?></strong></p>
        <p class="soluk">Girişte parolanın ardından doğrulayıcı uygulamanızdaki kod istenir.</p>
        <form class="form" method="post" action="<?= e(url('/admin/profil/2fa/kapat')) ?>" data-onay="İki adımlı doğrulama kapatılsın mı? Hesabınızın güvenliği azalır.">
            <?= csrf_field() ?>
            <div class="alan">
                <label for="kapat_parola">Kapatmak için parolanızı girin</label>
                <input type="password" id="kapat_parola" name="mevcut_parola" required maxlength="1024" autocomplete="current-password">
            </div>
            <div class="form-eylem"><button class="btn btn-tehlike" type="submit">2FA'yı kapat</button></div>
        </form>
    <?php elseif ($kurulumGizli !== null): ?>
        <ol>
            <li>Telefonunuzda bir doğrulayıcı uygulaması açın (Google Authenticator, Microsoft Authenticator, Authy, Bitwarden, 1Password…).</li>
            <li>“Anahtar girin / Kurulum anahtarı” seçeneğiyle aşağıdaki anahtarı ekleyin (zaman tabanlı):</li>
        </ol>
        <p class="gizli-anahtar" aria-label="Kurulum anahtarı"><?= e(trim(chunk_split((string) $kurulumGizli, 4, ' '))) ?></p>
        <p class="soluk">Telefonunuzdan tek dokunuşla eklemek için: <a href="<?= e((string) $kurulumUri) ?>">otpauth bağlantısı</a></p>
        <form class="form" method="post" action="<?= e(url('/admin/profil/2fa/etkinlestir')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <div class="alan">
                <label for="kod">Uygulamadaki 6 haneli kod</label>
                <input type="text" id="kod" name="kod" class="kod-girdi" required inputmode="numeric" maxlength="7" pattern="[0-9 ]{6,7}" autocomplete="one-time-code">
            </div>
            <div class="form-eylem"><button class="btn btn-primary" type="submit">Doğrula ve etkinleştir</button></div>
        </form>
    <?php else: ?>
        <p>Durum: <span class="rozet">Kapalı</span></p>
        <p class="soluk">Parolanıza ek olarak telefonunuzdaki tek kullanımlık kodu ister. Yönetici hesapları için şiddetle önerilir.</p>
        <form method="post" action="<?= e(url('/admin/profil/2fa/baslat')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Kurulumu başlat</button>
        </form>
    <?php endif; ?>
</section>
