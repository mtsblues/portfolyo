<?php
/** @var array $projeler */
/** @var array $makaleler */
/** @var array $kategoriler */
/** @var list<array> $hizmetler */
/** @var list<array> $referanslar */
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
        <?php if (musait_mi()): ?><span class="rozet rozet-musait"><?= e(ayar('musait_metin')) ?></span><?php endif; ?>
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
            <?php if (cv_acik()): ?><a class="btn btn-ghost" href="<?= e(url('/cv')) ?>">Özgeçmişim</a><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($hizmetler !== []): ?>
<section class="bolum kap" aria-labelledby="hizmetler-baslik">
    <div class="bolum-baslik"><h2 id="hizmetler-baslik">Neler yapıyorum</h2></div>
    <div class="izgara">
        <?php foreach ($hizmetler as $h): ?>
            <article class="kart hizmet-karti">
                <div class="kart-govde">
                    <h3><?php if (!empty($h['url'])): ?><a href="<?= e((string) $h['url']) ?>"><?= e((string) $h['baslik']) ?></a><?php else: ?><?= e((string) $h['baslik']) ?><?php endif; ?></h3>
                    <?php $icerikHizmet = \App\Core\Markdown::cevir((string) ($h['aciklama'] ?? '')); ?>
                    <div class="icerik"><?= $icerikHizmet /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="bolum-eylem"><a class="btn btn-primary" href="<?= e(url('/iletisim')) ?>">Birlikte çalışalım</a></p>
</section>
<?php endif; ?>

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

<?php if ($referanslar !== []): ?>
<section class="bolum kap" aria-labelledby="referanslar-baslik">
    <div class="bolum-baslik"><h2 id="referanslar-baslik">Referanslar</h2></div>
    <div class="izgara">
        <?php foreach ($referanslar as $r): ?>
            <figure class="kart referans-karti">
                <blockquote><?= nl2br(e((string) ($r['aciklama'] ?? ''))) ?></blockquote>
                <figcaption>
                    <strong><?php if (!empty($r['url'])): ?><a href="<?= e((string) $r['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $r['baslik']) ?></a><?php else: ?><?= e((string) $r['baslik']) ?><?php endif; ?></strong>
                    <?php if (!empty($r['kurum'])): ?><span class="soluk"><?= e((string) $r['kurum']) ?></span><?php endif; ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
