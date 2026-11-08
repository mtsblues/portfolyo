<article class="bolum kap dar">
    <a class="baglanti-ok geri" href="<?= e(url('/blog')) ?>">← Bilgi kütüphanesi</a>
    <header class="sayfa-ust">
        <a class="rozet" href="<?= e(url('/kategori/' . $makale['kategori_slug'])) ?>"><?= e($makale['kategori_ad']) ?></a>
        <h1><?= e($makale['baslik']) ?></h1>
        <p class="soluk">
            <?= e($makale['yazar']) ?> · <time datetime="<?= e(str_replace(' ', 'T', (string) $makale['yayin_tarihi'])) ?>Z"><?= e(tarih($makale['yayin_tarihi'])) ?></time>
            · <?= (int) $makale['okunma_suresi'] ?> dk okuma
        </p>
        <?php if (!empty($makale['ozet'])): ?><p class="ozet"><?= e($makale['ozet']) ?></p><?php endif; ?>
    </header>
    <?php if (!empty($makale['kapak_gorsel'])): ?>
        <img class="kapak" src="<?= e(url('/yuklemeler/' . $makale['kapak_gorsel'])) ?>" alt="<?= e($makale['baslik']) ?>">
    <?php endif; ?>
    <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>

    <?php if ($etiketler !== []): ?>
        <div class="cip-listesi etiketler" aria-label="Etiketler">
            <?php foreach ($etiketler as $e_): ?>
                <a class="cip" href="<?= e(url('/etiket/' . $e_['slug'])) ?>">#<?= e($e_['ad']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>

<?php if ($ilgili !== []): ?>
    <section class="bolum kap">
        <div class="bolum-baslik"><h2>İlgili makaleler</h2></div>
        <div class="izgara">
            <?php foreach ($ilgili as $m): ?>
                <?php partial('makale_karti', ['makale' => $m]); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
