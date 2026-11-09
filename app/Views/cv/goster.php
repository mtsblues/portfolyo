<?php
/** @var string $cvAd */
/** @var string $ozetHtml */
/** @var list<array> $deneyimler */
/** @var list<array> $egitimler */
/** @var array<string,list<array>> $yetenekGrup */
/** @var list<array> $sertifikalar */
/** @var list<array> $projeler */
use App\Core\CvYardimci;
use App\Core\Markdown;

$unvan = trim(ayar('cv_unvan'));
$eposta = trim(ayar('cv_eposta'));
$telefon = trim(ayar('cv_telefon'));
$konum = trim(ayar('cv_konum'));
$sosyal = sosyal_baglantilar();
$bos = $deneyimler === [] && $egitimler === [] && $yetenekGrup === [] && $sertifikalar === [] && $ozetHtml === '';
?>
<article class="bolum kap cv">
    <header class="cv-ust">
        <div>
            <h1><?= e($cvAd) ?></h1>
            <?php if ($unvan !== ''): ?><p class="cv-unvan"><?= e($unvan) ?></p><?php endif; ?>
            <?php if (musait_mi()): ?><p><span class="rozet rozet-musait"><?= e(ayar('musait_metin')) ?></span></p><?php endif; ?>
        </div>
        <ul class="cv-iletisim" aria-label="İletişim">
            <?php if ($eposta !== ''): ?><li><a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a></li><?php endif; ?>
            <?php if ($telefon !== ''): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $telefon)) ?>"><?= e($telefon) ?></a></li><?php endif; ?>
            <?php if ($konum !== ''): ?><li><?= e($konum) ?></li><?php endif; ?>
            <li><a href="<?= e(url('/')) ?>"><?= e(preg_replace('#^https?://#', '', mutlak_url('/'))) ?></a></li>
            <?php foreach ($sosyal as $etiket => $adres): ?>
                <li><a href="<?= e($adres) ?>" rel="me noopener noreferrer" target="_blank"><?= e($etiket) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </header>

    <div class="cv-araclar yazdirma-gizle">
        <button type="button" class="btn btn-primary" data-yazdir>Yazdır / PDF olarak kaydet</button>
        <?php if (ayar('cv_vcard') === '1'): ?>
            <a class="btn btn-ghost" href="<?= e(url('/cv/kartvizit')) ?>">Kartvizit indir (.vcf)</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/iletisim')) ?>">İletişime geç</a>
    </div>

    <?php if ($bos): ?>
        <p class="bos">Özgeçmiş henüz doldurulmadı.</p>
    <?php endif; ?>

    <?php if ($ozetHtml !== ''): ?>
        <section class="cv-bolum" aria-labelledby="cv-ozet">
            <h2 id="cv-ozet">Özet</h2>
            <?php $icerikOzet = $ozetHtml; ?>
            <div class="icerik"><?= $icerikOzet /* Markdown::cevir çıktısı: kaçışlı ve beyaz listeli */ ?></div>
        </section>
    <?php endif; ?>

    <?php if ($deneyimler !== []): ?>
        <section class="cv-bolum" aria-labelledby="cv-deneyim">
            <h2 id="cv-deneyim">Deneyim</h2>
            <?php foreach ($deneyimler as $o): ?>
                <div class="cv-oge">
                    <div class="cv-oge-ust">
                        <h3><?= e((string) $o['baslik']) ?><?php if (!empty($o['kurum'])): ?> <span class="cv-kurum">· <?php if (!empty($o['url'])): ?><a href="<?= e((string) $o['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $o['kurum']) ?></a><?php else: ?><?= e((string) $o['kurum']) ?><?php endif; ?></span><?php endif; ?></h3>
                        <span class="cv-tarih"><?= e(CvYardimci::aralik($o['baslangic'], $o['bitis'], (int) $o['devam'] === 1)) ?><?= !empty($o['konum']) ? ' · ' . e((string) $o['konum']) : '' ?></span>
                    </div>
                    <?php if (!empty($o['aciklama'])): $icerikOge = Markdown::cevir((string) $o['aciklama']); ?><div class="icerik"><?= $icerikOge /* kaçışlı ve beyaz listeli */ ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($egitimler !== []): ?>
        <section class="cv-bolum" aria-labelledby="cv-egitim">
            <h2 id="cv-egitim">Eğitim</h2>
            <?php foreach ($egitimler as $o): ?>
                <div class="cv-oge">
                    <div class="cv-oge-ust">
                        <h3><?= e((string) $o['baslik']) ?><?php if (!empty($o['kurum'])): ?> <span class="cv-kurum">· <?= e((string) $o['kurum']) ?></span><?php endif; ?></h3>
                        <span class="cv-tarih"><?= e(CvYardimci::aralik($o['baslangic'], $o['bitis'], (int) $o['devam'] === 1)) ?><?= !empty($o['konum']) ? ' · ' . e((string) $o['konum']) : '' ?></span>
                    </div>
                    <?php if (!empty($o['aciklama'])): $icerikOge = Markdown::cevir((string) $o['aciklama']); ?><div class="icerik"><?= $icerikOge /* kaçışlı ve beyaz listeli */ ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($yetenekGrup !== []): ?>
        <section class="cv-bolum" aria-labelledby="cv-yetenek">
            <h2 id="cv-yetenek">Yetenekler</h2>
            <div class="cv-yetenekler">
                <?php foreach ($yetenekGrup as $grup => $liste): ?>
                    <div class="cv-grup">
                        <h3><?= e((string) $grup) ?></h3>
                        <ul>
                            <?php foreach ($liste as $y): $n = CvYardimci::nokta($y['seviye'] !== null ? (int) $y['seviye'] : null); ?>
                                <li>
                                    <span><?= e((string) $y['baslik']) ?></span>
                                    <?php if ($n > 0): ?>
                                        <span class="noktalar" role="img" aria-label="Seviye: 5 üzerinden <?= (int) $n ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $n ? 'dolu' : '' ?>"></i><?php endfor; ?>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($sertifikalar !== []): ?>
        <section class="cv-bolum" aria-labelledby="cv-sertifika">
            <h2 id="cv-sertifika">Sertifikalar</h2>
            <ul class="cv-liste">
                <?php foreach ($sertifikalar as $o): ?>
                    <li>
                        <strong><?php if (!empty($o['url'])): ?><a href="<?= e((string) $o['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $o['baslik']) ?></a><?php else: ?><?= e((string) $o['baslik']) ?><?php endif; ?></strong>
                        <?php if (!empty($o['kurum'])): ?> · <?= e((string) $o['kurum']) ?><?php endif; ?>
                        <span class="cv-tarih"><?= e(CvYardimci::ayYil($o['baslangic'])) ?><?= !empty($o['bitis']) ? ' – ' . e(CvYardimci::ayYil($o['bitis'])) : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($projeler !== []): ?>
        <section class="cv-bolum" aria-labelledby="cv-proje">
            <h2 id="cv-proje">Seçili projeler</h2>
            <ul class="cv-liste">
                <?php foreach ($projeler as $p): $tek = CvYardimci::teknolojiler($p['teknolojiler'] ?? null); ?>
                    <li>
                        <strong><a href="<?= e(url('/proje/' . $p['slug'])) ?>"><?= e((string) $p['baslik']) ?></a></strong>
                        — <?= e(ozet_kes((string) $p['aciklama'], 140)) ?>
                        <?php if ($tek !== []): ?><span class="cv-tarih"><?= e(implode(' · ', array_slice($tek, 0, 6))) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>
