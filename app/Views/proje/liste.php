<section class="bolum kap">
    <header class="sayfa-ust">
        <h1>Projelerim</h1>
        <p class="soluk">Geliştirdiğim sistemler, yazılımlar ve altyapı çalışmaları.</p>
    </header>
    <?php if ($projeler === []): ?>
        <p class="bos">Henüz proje eklenmedi.</p>
    <?php else: ?>
        <div class="izgara">
            <?php foreach ($projeler as $proje): ?>
                <?php partial('proje_karti', ['proje' => $proje]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol]); ?>
</section>
