<?php /** @var array $makale */ ?>
<article class="kart makale-karti">
    <div class="kart-govde">
        <div class="kart-ust">
            <a class="rozet" href="<?= e(url('/kategori/' . $makale['kategori_slug'])) ?>"><?= e($makale['kategori_ad']) ?></a>
            <time class="soluk" datetime="<?= e(str_replace(' ', 'T', (string) $makale['yayin_tarihi'])) ?>Z"><?= e(tarih($makale['yayin_tarihi'])) ?></time>
        </div>
        <h3><a href="<?= e(url('/makale/' . $makale['slug'])) ?>"><?= e($makale['baslik']) ?></a></h3>
        <?php if (!empty($makale['ozet'])): ?><p><?= e(ozet_kes((string) $makale['ozet'], 150)) ?></p><?php endif; ?>
        <div class="kart-alt">
            <span class="soluk"><?= (int) $makale['okunma_suresi'] ?> dk okuma</span>
            <a class="baglanti-ok" href="<?= e(url('/makale/' . $makale['slug'])) ?>">Oku →</a>
        </div>
    </div>
</article>
