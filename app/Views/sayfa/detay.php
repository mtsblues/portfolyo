<?php /** @var array $sayfa */ /** @var string $icerikHtml */ ?>
<article class="bolum kap dar">
    <header class="sayfa-ust">
        <h1><?= e((string) $sayfa['baslik']) ?></h1>
        <p class="soluk">Son güncelleme: <?= e(tarih((string) $sayfa['guncellenme'])) ?></p>
    </header>
    <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
</article>
