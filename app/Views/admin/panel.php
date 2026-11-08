<?php
/** @var array $istatistik */
$kartlar = [
    ['Makale', $istatistik['makale_toplam'], $istatistik['makale_yayinda'] . ' yayında · ' . $istatistik['makale_taslak'] . ' taslak', '/admin/makaleler'],
    ['Proje', $istatistik['proje'], 'Portfolyo projeleri', '/admin/projeler'],
    ['Kategori', $istatistik['kategori'], 'Bilgi kütüphanesi', '/admin/kategoriler'],
    ['Mesaj', $istatistik['mesaj_toplam'], ($okunmamis ?? 0) . ' okunmamış', '/admin/mesajlar'],
];
?>
<div class="istatistikler">
    <?php foreach ($kartlar as [$etiket, $sayi, $alt, $yol]): ?>
        <a class="kart istatistik" href="<?= e(url($yol)) ?>">
            <span class="soluk"><?= e($etiket) ?></span>
            <strong><?= (int) $sayi ?></strong>
            <small class="soluk"><?= e($alt) ?></small>
        </a>
    <?php endforeach; ?>
    <div class="kart istatistik">
        <span class="soluk">Başarısız giriş (24 sa)</span>
        <strong><?= (int) $istatistik['basarisiz_giris'] ?></strong>
        <small class="soluk">Güvenlik izleme</small>
    </div>
</div>

<?php if (!empty($saglik)):
    $sorunlar = array_filter($saglik, static fn(array $s): bool => in_array($s['seviye'], ['kritik', 'uyari'], true));
    $isaretler = ['kritik' => '✗', 'uyari' => '!', 'bilgi' => 'i', 'ok' => '✓'];
    ?>
    <section class="kart saglik-kart" aria-labelledby="saglik-baslik">
        <div class="kart-baslik">
            <h2 id="saglik-baslik">Canlı ortam kontrolü</h2>
            <span class="soluk"><?= $sorunlar === [] ? 'Her şey yolunda' : count($sorunlar) . ' madde dikkat istiyor' ?></span>
        </div>
        <ul class="saglik-listesi">
            <?php foreach ($saglik as $s): ?>
                <li class="saglik-<?= e($s['seviye']) ?>">
                    <span class="saglik-isaret" aria-hidden="true"><?= e($isaretler[$s['seviye']] ?? '') ?></span>
                    <span><strong><?= e($s['baslik']) ?></strong><?php if ($s['not'] !== ''): ?><br><small class="soluk"><?= e($s['not']) ?></small><?php endif; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<div class="iki-kolon">
    <section class="kart">
        <div class="kart-baslik"><h2>Son makaleler</h2><a class="baglanti-ok" href="<?= e(url('/admin/makale/yeni')) ?>">+ Yeni</a></div>
        <?php if ($sonMakaleler === []): ?><p class="bos">Henüz makale yok.</p><?php else: ?>
            <ul class="liste">
                <?php foreach ($sonMakaleler as $m): ?>
                    <li>
                        <a href="<?= e(url('/admin/makale/' . $m['id'] . '/duzenle')) ?>"><?= e($m['baslik']) ?></a>
                        <span class="rozet rozet-<?= e($m['durum']) ?>"><?= e($m['durum']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section class="kart">
        <div class="kart-baslik"><h2>Son mesajlar</h2><a class="baglanti-ok" href="<?= e(url('/admin/mesajlar')) ?>">Tümü</a></div>
        <?php if ($sonMesajlar === []): ?><p class="bos">Henüz mesaj yok.</p><?php else: ?>
            <ul class="liste">
                <?php foreach ($sonMesajlar as $m): ?>
                    <li>
                        <span><strong><?= e($m['ad_soyad']) ?></strong> — <?= e(ozet_kes((string) $m['konu'], 50)) ?></span>
                        <span class="soluk"><?= e(tarih($m['olusturulma'], true)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
