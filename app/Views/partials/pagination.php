<?php
/** @var array{sayfa:int,toplam:int} $sayfalama */
/** @var string $sayfalamaYol */
$ek = $sayfalamaEk ?? [];
$baglanti = static function (int $n) use ($sayfalamaYol, $ek): string {
    $sorgu = $ek;
    if ($n > 1) {
        $sorgu['sayfa'] = $n;
    }
    return url($sayfalamaYol) . ($sorgu !== [] ? '?' . http_build_query($sorgu) : '');
};
?>
<?php if ($sayfalama['toplam'] > 1): ?>
    <nav class="sayfalama" aria-label="Sayfalama">
        <?php if ($sayfalama['sayfa'] > 1): ?>
            <a href="<?= e($baglanti($sayfalama['sayfa'] - 1)) ?>" rel="prev">← Önceki</a>
        <?php endif; ?>
        <span class="soluk"><?= (int) $sayfalama['sayfa'] ?> / <?= (int) $sayfalama['toplam'] ?></span>
        <?php if ($sayfalama['sayfa'] < $sayfalama['toplam']): ?>
            <a href="<?= e($baglanti($sayfalama['sayfa'] + 1)) ?>" rel="next">Sonraki →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
