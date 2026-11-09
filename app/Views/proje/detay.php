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
    <?php $tek = \App\Core\CvYardimci::teknolojiler($proje['teknolojiler'] ?? null); ?>
    <?php if ($tek !== []): ?>
        <ul class="teknoloji-listesi" aria-label="Teknolojiler">
            <?php foreach ($tek as $t): ?><li><a href="<?= e(url('/projeler') . '?' . http_build_query(['teknoloji' => $t])) ?>"><?= e($t) ?></a></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!empty($proje['gorsel'])): ?>
        <img class="kapak" src="<?= e(url('/yuklemeler/' . $proje['gorsel'])) ?>" alt="<?= e($proje['baslik']) ?>">
    <?php endif; ?>
    <?php $vaka = ['Problem' => $proje['problem'] ?? '', 'Çözüm' => $proje['cozum'] ?? '', 'Sonuç' => $proje['sonuc'] ?? '']; ?>
    <?php if (trim(implode('', $vaka)) !== ''): ?>
        <div class="vaka" aria-label="Vaka çalışması">
            <?php foreach ($vaka as $baslik => $metin): ?>
                <?php if (trim((string) $metin) !== ''): ?>
                    <section class="vaka-kutu vaka-<?= e(strtolower(strtr($baslik, ['Ç' => 'c', 'ç' => 'c', 'ö' => 'o', 'Ö' => 'o', 'ü' => 'u']))) ?>">
                        <h2><?= e($baslik) ?></h2>
                        <?php $icerikVaka = \App\Core\Markdown::cevir((string) $metin); ?>
                        <?= $icerikVaka /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="icerik"><?= $icerikHtml /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
</article>
