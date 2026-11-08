<?php
/** @var array $projeler */
/** @var array $makaleler */
/** @var array $kategoriler */
?>
<section class="hero">
    <div class="kap hero-ic">
        <?php
        $heroBas = trim(ayar('hero_baslik_bas'));
        $heroVurgu = trim(ayar('hero_baslik_vurgu'));
        $heroSon = trim(ayar('hero_baslik_son'));
        $heroAciklama = trim(ayar('hero_aciklama')) !== '' ? trim(ayar('hero_aciklama')) : trim(ayar('site_aciklama'));
        ?>
        <?php if (trim(ayar('hero_rozet')) !== ''): ?><span class="rozet rozet-vurgu"><?= e(ayar('hero_rozet')) ?></span><?php endif; ?>
        <h1>
            <?php if ($heroBas === '' && $heroVurgu === '' && $heroSon === ''): ?>
                <?= e(ayar('site_baslik', 'Portfolyo')) ?>
            <?php else: ?>
                <?= e($heroBas) ?><?= $heroVurgu !== '' ? ' <span class="vurgu-metin">' . e($heroVurgu) . '</span>' : '' ?><?= $heroSon !== '' ? ' ' . e($heroSon) : '' ?>
            <?php endif; ?>
        </h1>
        <?php if ($heroAciklama !== ''): ?><p class="hero-alt"><?= e($heroAciklama) ?></p><?php endif; ?>
        <div class="hero-eylem">
            <a class="btn btn-primary" href="<?= e(url('/projeler')) ?>">Projelerimi gör</a>
            <a class="btn btn-ghost" href="<?= e(url('/blog')) ?>">Bilgi kütüphanesi</a>
        </div>
    </div>
</section>

<section class="bolum kap">
    <div class="bolum-baslik">
        <h2>Son projeler</h2>
        <a class="baglanti-ok" href="<?= e(url('/projeler')) ?>">Tümü →</a>
    </div>
    <?php if ($projeler === []): ?>
        <p class="bos">Henüz proje eklenmedi.</p>
    <?php else: ?>
        <div class="izgara">
            <?php foreach ($projeler as $proje): ?>
                <?php partial('proje_karti', ['proje' => $proje]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="bolum kap">
    <div class="bolum-baslik">
        <h2>Son çözüm makaleleri</h2>
        <a class="baglanti-ok" href="<?= e(url('/blog')) ?>">Tümü →</a>
    </div>
    <?php if ($kategoriler !== []): ?>
        <div class="cip-listesi" aria-label="Kategoriler">
            <?php foreach ($kategoriler as $k): ?>
                <a class="cip" href="<?= e(url('/kategori/' . $k['slug'])) ?>"><?= e($k['ad']) ?> <span class="soluk"><?= (int) $k['makale_sayisi'] ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($makaleler === []): ?>
        <p class="bos">Henüz makale yayımlanmadı.</p>
    <?php else: ?>
        <div class="izgara">
            <?php foreach ($makaleler as $makale): ?>
                <?php partial('makale_karti', ['makale' => $makale]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
