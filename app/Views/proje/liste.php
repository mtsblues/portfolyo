<section class="bolum kap">
    <header class="sayfa-ust">
        <h1>Projelerim</h1>
        <p class="soluk">Geliştirdiğim sistemler, yazılımlar ve altyapı çalışmaları.</p>
    </header>
    <?php if ($teknolojiler !== []): ?>
        <div class="cip-listesi" aria-label="Teknolojiye göre filtrele">
            <a class="cip<?= $seciliTeknoloji === '' ? ' cip-aktif' : '' ?>" href="<?= e(url('/projeler')) ?>">Tümü</a>
            <?php foreach ($teknolojiler as $t): ?>
                <a class="cip<?= mb_strtolower($seciliTeknoloji) === mb_strtolower($t['ad']) ? ' cip-aktif' : '' ?>" href="<?= e(url('/projeler') . '?' . http_build_query(['teknoloji' => $t['ad']])) ?>"><?= e($t['ad']) ?> <span class="soluk"><?= (int) $t['sayi'] ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($projeler === []): ?>
        <p class="bos"><?= $seciliTeknoloji !== '' ? 'Bu teknolojiyle eşleşen proje yok.' : 'Henüz proje eklenmedi.' ?></p>
    <?php else: ?>
        <div class="izgara">
            <?php foreach ($projeler as $proje): ?>
                <?php partial('proje_karti', ['proje' => $proje]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol, 'sayfalamaEk' => $sayfalamaEk]); ?>
</section>
