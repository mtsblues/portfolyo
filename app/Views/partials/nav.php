<header class="site-ust">
    <div class="kap nav-ic">
        <a class="logo" href="<?= e(url('/')) ?>" aria-label="Anasayfa">
            <?php if (trim(ayar('site_logo')) !== ''): ?>
                <img src="<?= e(gorsel_url(ayar('site_logo'))) ?>" alt="" height="28">
            <?php else: ?>
                <span class="logo-isaret" aria-hidden="true"></span>
            <?php endif; ?>
            <?= e(ayar('site_baslik', 'Portfolyo')) ?>
        </a>
        <button type="button" class="ikon-dugme menu-ac" id="menu-dugme" aria-controls="ana-menu" aria-expanded="false" aria-label="Menüyü aç">☰</button>
        <nav class="ana-menu" id="ana-menu" aria-label="Ana menü">
            <a href="<?= e(url('/')) ?>"<?= menu_aktif('/') ?>>Anasayfa</a>
            <a href="<?= e(url('/projeler')) ?>"<?= menu_aktif('/projeler') ?>>Projeler</a>
            <a href="<?= e(url('/blog')) ?>"<?= menu_aktif('/blog') ?>>Bilgi Kütüphanesi</a>
            <?php if (hakkimda_var()): ?><a href="<?= e(url('/hakkimda')) ?>"<?= menu_aktif('/hakkimda') ?>>Hakkımda</a><?php endif; ?>
            <a href="<?= e(url('/iletisim')) ?>"<?= menu_aktif('/iletisim') ?>>İletişim</a>
            <button type="button" class="ikon-dugme" id="tema-dugme" aria-label="Temayı değiştir">◐</button>
        </nav>
    </div>
</header>
