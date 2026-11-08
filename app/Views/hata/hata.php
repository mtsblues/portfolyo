<section class="bolum kap dar hata-sayfasi">
    <p class="hata-kod"><?= (int) $kod ?></p>
    <h1><?= e($baslik) ?></h1>
    <?php if (($mesaj ?? '') !== ''): ?><p class="soluk"><?= e($mesaj) ?></p><?php endif; ?>
    <p><a class="btn btn-primary" href="<?= e(url('/')) ?>">Anasayfaya dön</a></p>
</section>
