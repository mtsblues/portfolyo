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
    <?php if (count($icindekiler) >= 3): ?>
        <nav class="icindekiler" aria-label="İçindekiler">
            <details open>
                <summary>İçindekiler</summary>
                <ul>
                    <?php foreach ($icindekiler as $b): ?>
                        <li class="seviye-<?= (int) $b['seviye'] ?>"><a href="#<?= e($b['id']) ?>"><?= e($b['metin']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </nav>
    <?php endif; ?>
    <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>

    <?php if ($etiketler !== []): ?>
        <div class="cip-listesi etiketler" aria-label="Etiketler">
            <?php foreach ($etiketler as $e_): ?>
                <a class="cip" href="<?= e(url('/etiket/' . $e_['slug'])) ?>">#<?= e($e_['ad']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $paylasUrl = mutlak_url('/makale/' . $makale['slug']);
    $paylasBaslik = (string) $makale['baslik'];
    ?>
    <div class="paylas yazdirma-gizle" aria-label="Paylaş">
        <span class="soluk">Paylaş:</span>
        <a href="<?= e('https://twitter.com/intent/tweet?' . http_build_query(['url' => $paylasUrl, 'text' => $paylasBaslik])) ?>" target="_blank" rel="noopener noreferrer">X</a>
        <a href="<?= e('https://www.linkedin.com/sharing/share-offsite/?' . http_build_query(['url' => $paylasUrl])) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
        <a href="<?= e('https://wa.me/?' . http_build_query(['text' => $paylasBaslik . ' ' . $paylasUrl])) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
        <a href="<?= e('mailto:?' . http_build_query(['subject' => $paylasBaslik, 'body' => $paylasUrl], '', '&', PHP_QUERY_RFC3986)) ?>">E-posta</a>
        <button type="button" class="btn btn-ghost btn-kucuk" data-kopyala="<?= e($paylasUrl) ?>">Bağlantıyı kopyala</button>
    </div>

    <?php if ($onceki !== null || $sonraki !== null): ?>
        <nav class="onceki-sonraki yazdirma-gizle" aria-label="Makaleler arasında gezin">
            <?php if ($onceki !== null): ?>
                <a class="on" href="<?= e(url('/makale/' . $onceki['slug'])) ?>" rel="prev"><span class="soluk">← Önceki</span><strong><?= e((string) $onceki['baslik']) ?></strong></a>
            <?php endif; ?>
            <?php if ($sonraki !== null): ?>
                <a class="son" href="<?= e(url('/makale/' . $sonraki['slug'])) ?>" rel="next"><span class="soluk">Sonraki →</span><strong><?= e((string) $sonraki['baslik']) ?></strong></a>
            <?php endif; ?>
        </nav>
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
