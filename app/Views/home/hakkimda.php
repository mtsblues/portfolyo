<?php
/** @var string $icerikHtml */
$foto = gorsel_url(ayar('hakkimda_foto'));
$sosyal = sosyal_baglantilar();
?>
<article class="bolum kap dar">
    <header class="sayfa-ust">
        <h1>Hakkımda</h1>
    </header>
    <div class="hakkimda<?= $foto !== '' ? ' foto-var' : '' ?>">
        <?php if ($foto !== ''): ?>
            <img class="hakkimda-foto" src="<?= e($foto) ?>" alt="<?= e(ayar('site_baslik', 'Portfolyo')) ?>" width="200" height="200">
        <?php endif; ?>
        <div>
            <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
            <?php if ($sosyal !== []): ?>
                <nav class="sosyal" aria-label="Sosyal bağlantılar">
                    <?php foreach ($sosyal as $etiket => $adres): ?>
                        <a href="<?= e($adres) ?>" rel="me noopener noreferrer" target="_blank"><?= e($etiket) ?> ↗</a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <p><a class="btn btn-primary" href="<?= e(url('/iletisim')) ?>">İletişime geç</a></p>
        </div>
    </div>
</article>
