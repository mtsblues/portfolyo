<article class="bolum kap dar">
    <a class="baglanti-ok geri" href="<?= e(url('/projeler')) ?>">← Tüm projeler</a>
    <header class="sayfa-ust">
        <span class="rozet"><?= e(\App\Models\Proje::DURUMLAR[$proje['durum']] ?? $proje['durum']) ?></span>
        <h1><?= e($proje['baslik']) ?></h1>
        <p class="soluk"><?= e($proje['aciklama']) ?></p>
        <div class="hero-eylem">
            <?php if (!empty($proje['proje_url'])): ?>
                <a class="btn btn-primary" href="<?= e($proje['proje_url']) ?>" target="_blank" rel="noopener noreferrer">Projeyi aç ↗</a>
            <?php endif; ?>
            <?php if (!empty($proje['github_url'])): ?>
                <a class="btn btn-ghost" href="<?= e($proje['github_url']) ?>" target="_blank" rel="noopener noreferrer">Kaynak kod ↗</a>
            <?php endif; ?>
        </div>
    </header>
    <?php if (!empty($proje['gorsel'])): ?>
        <img class="kapak" src="<?= e(url('/yuklemeler/' . $proje['gorsel'])) ?>" alt="<?= e($proje['baslik']) ?>">
    <?php endif; ?>
    <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
</article>
