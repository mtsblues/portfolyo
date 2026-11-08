<?php
/** @var bool $anahtarTamam */
/** @var list<string> $gecis */
?>
<main class="giris-kutu">
    <div class="kart giris-kart">
        <div class="giris-ust">
            <span class="logo-isaret" aria-hidden="true"></span>
            <h1>Kurulum tamamlandı</h1>
            <p class="soluk">Veritabanı ve yönetici hesabınız hazır.</p>
        </div>
        <div class="uyari uyari-basari" role="status">Şimdi aşağıdaki güvenlik adımlarını yapın.</div>
        <ol class="kurulum-adimlar">
            <li><strong>.env dosyasından <code>KURULUM_TOKEN</code> satırını silin.</strong> (Sihirbaz bundan sonra zaten kapalıdır; yine de silin.)</li>
            <li><code>.env</code> içinde <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code>, <code>SESSION_SECURE=true</code> (HTTPS), <code>APP_URL=https://alanadiniz.com</code> olmalı.</li>
            <?php if (!$anahtarTamam): ?>
                <li><strong>UYGULAMA_ANAHTARI</strong> boş: .env dosyasına rastgele 64 karakterlik bir değer yazın (<code>.env</code> yazılabilir değildi).</li>
            <?php endif; ?>
            <li>Yönetim → kullanıcı adınız → <strong>Profil</strong> sayfasından iki adımlı doğrulamayı açın.</li>
            <li><code>database/install.php</code> ve <code>database/seed.php</code> dosyalarını sunucudan silin.</li>
        </ol>
        <?php if ($gecis !== []): ?><p class="soluk">Uygulanan geçişler: <?= e(implode('; ', $gecis)) ?></p><?php endif; ?>
        <p><a class="btn btn-primary btn-tam" href="<?= e(url('/admin/giris')) ?>">Yönetim paneline git</a></p>
    </div>
</main>
