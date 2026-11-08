<section class="bolum kap">
    <header class="sayfa-ust">
        <h1><?= e($listeBaslik) ?></h1>
        <?php if ($listeAciklama !== ''): ?><p class="soluk"><?= e($listeAciklama) ?></p><?php endif; ?>
    </header>

    <form class="arama" method="get" action="<?= e(url('/blog')) ?>" role="search">
        <label class="gorunmez" for="arama-q">Ara</label>
        <input id="arama-q" type="search" name="q" value="<?= e($arama) ?>" maxlength="100" placeholder="Makalelerde ara…">
        <button class="btn btn-ghost" type="submit">Ara</button>
    </form>

    <?php if ($kategoriler !== []): ?>
        <div class="cip-listesi" aria-label="Kategoriler">
            <a class="cip<?= $aktifKategori === null ? ' cip-aktif' : '' ?>" href="<?= e(url('/blog')) ?>">Tümü</a>
            <?php foreach ($kategoriler as $k): ?>
                <a class="cip<?= $aktifKategori === $k['slug'] ? ' cip-aktif' : '' ?>" href="<?= e(url('/kategori/' . $k['slug'])) ?>"><?= e($k['ad']) ?> <span class="soluk"><?= (int) $k['makale_sayisi'] ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($makaleler === []): ?>
        <p class="bos">Bu kriterlere uygun makale bulunamadı.</p>
    <?php else: ?>
        <div class="izgara">
            <?php foreach ($makaleler as $makale): ?>
                <?php partial('makale_karti', ['makale' => $makale]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol, 'sayfalamaEk' => $sayfalamaEk]); ?>
</section>
