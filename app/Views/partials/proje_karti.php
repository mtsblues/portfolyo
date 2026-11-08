<?php /** @var array $proje */ ?>
<article class="kart proje-karti">
    <?php if (!empty($proje['gorsel'])): ?>
        <a class="kart-gorsel" href="<?= e(url('/proje/' . $proje['slug'])) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e(url('/yuklemeler/' . $proje['gorsel'])) ?>" alt="" loading="lazy">
        </a>
    <?php endif; ?>
    <div class="kart-govde">
        <div class="kart-ust">
            <span class="rozet"><?= e(\App\Models\Proje::DURUMLAR[$proje['durum']] ?? $proje['durum']) ?></span>
            <?php if ((int) $proje['one_cikan'] === 1): ?><span class="rozet rozet-vurgu">Öne çıkan</span><?php endif; ?>
        </div>
        <h3><a href="<?= e(url('/proje/' . $proje['slug'])) ?>"><?= e($proje['baslik']) ?></a></h3>
        <p><?= e(ozet_kes((string) $proje['aciklama'], 140)) ?></p>
        <div class="kart-alt">
            <a class="baglanti-ok" href="<?= e(url('/proje/' . $proje['slug'])) ?>">Detaylar →</a>
        </div>
    </div>
</article>
