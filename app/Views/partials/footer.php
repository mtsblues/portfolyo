<footer class="site-alt">
    <div class="kap alt-ic">
        <?php if (trim(ayar('altbilgi_metin')) !== ''): ?>
            <p><?= e(ayar('altbilgi_metin')) ?></p>
        <?php else: ?>
            <p>© <?= e(date('Y')) ?> <?= e(ayar('site_baslik', 'Portfolyo')) ?>. Tüm hakları saklıdır.</p>
        <?php endif; ?>
        <nav aria-label="Alt menü">
            <a href="<?= e(url('/projeler')) ?>">Projeler</a>
            <a href="<?= e(url('/blog')) ?>">Blog</a>
            <?php if (hakkimda_var()): ?><a href="<?= e(url('/hakkimda')) ?>">Hakkımda</a><?php endif; ?>
            <a href="<?= e(url('/iletisim')) ?>">İletişim</a>
            <a href="<?= e(url('/rss.xml')) ?>">RSS</a>
            <a href="<?= e(url('/sitemap.xml')) ?>">Site haritası</a>
        </nav>
    </div>
    <?php $sosyal = sosyal_baglantilar(); ?>
    <?php if ($sosyal !== []): ?>
        <div class="kap alt-sosyal">
            <nav class="sosyal" aria-label="Sosyal bağlantılar">
                <?php foreach ($sosyal as $etiket => $adres): ?>
                    <a href="<?= e($adres) ?>" rel="me noopener noreferrer" target="_blank"><?= e($etiket) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    <?php endif; ?>
</footer>
