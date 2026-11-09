<?php
/** @var string $tur */
/** @var list<array> $ogeler */
/** @var array<string,int> $sayilar */
use App\Core\CvYardimci;
use App\Models\CvOgesi;

$t = CvOgesi::TURLER[$tur];
$yayinda = ayar('cv_yayin') === '1';
?>
<?php if (!$yayinda): ?>
    <div class="uyari uyari-bilgi" role="status">Özgeçmiş sayfası şu an <strong>kapalı</strong>. Deneyim, eğitim ve yetenekler yalnızca <a href="<?= e(url('/admin/ayarlar')) ?>">Ayarlar → Özgeçmiş ve müsaitlik</a> bölümünden “Özgeçmiş sayfasını yayınla” seçilince /cv adresinde görünür. Hizmetler ve referanslar anasayfada kendi bölümlerinde görünür.</div>
<?php endif; ?>

<div class="arac-cubugu">
    <?php foreach (CvOgesi::TURLER as $k => $ts): ?>
        <a class="btn btn-kucuk <?= $k === $tur ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/cv?tur=' . $k)) ?>"><?= e($ts['ad']) ?> <span class="soluk"><?= (int) ($sayilar[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>

<section class="kart tablo-kap">
    <div class="kart-baslik kart-baslik-ic">
        <h2><?= e($t['ad']) ?></h2>
        <a class="btn btn-primary btn-kucuk" href="<?= e(url('/admin/cv/yeni?tur=' . $tur)) ?>">+ Yeni <?= e($t['tekil']) ?></a>
    </div>
    <p class="soluk kart-not"><?= e($t['ipucu']) ?></p>
    <?php if ($ogeler === []): ?>
        <p class="soluk bos-ic">Henüz <?= e($t['tekil']) ?> eklenmedi.</p>
    <?php else: ?>
    <table class="tablo">
        <thead><tr>
            <th><?= e($t['baslik']) ?></th>
            <?php if (isset($t['alanlar']['kurum'])): ?><th><?= e($tur === 'yetenek' ? 'Grup' : 'Kurum') ?></th><?php endif; ?>
            <?php if (isset($t['alanlar']['tarih']) || isset($t['alanlar']['baslangic'])): ?><th>Tarih</th><?php endif; ?>
            <?php if (isset($t['alanlar']['seviye'])): ?><th>Seviye</th><?php endif; ?>
            <th>Sıra</th><th>Durum</th><th class="sag">İşlem</th>
        </tr></thead>
        <tbody>
        <?php foreach ($ogeler as $o): ?>
            <tr>
                <td data-etiket="<?= e($t['baslik']) ?>"><a href="<?= e(url('/admin/cv/' . $o['id'] . '/duzenle')) ?>"><?= e((string) $o['baslik']) ?></a></td>
                <?php if (isset($t['alanlar']['kurum'])): ?><td data-etiket="Kurum"><?= e((string) ($o['kurum'] ?? '')) ?></td><?php endif; ?>
                <?php if (isset($t['alanlar']['tarih']) || isset($t['alanlar']['baslangic'])): ?>
                    <td data-etiket="Tarih" class="zaman"><?= e(CvYardimci::aralik($o['baslangic'], $o['bitis'], (int) $o['devam'] === 1)) ?></td>
                <?php endif; ?>
                <?php if (isset($t['alanlar']['seviye'])): ?><td data-etiket="Seviye"><?= e($o['seviye'] !== null ? CvYardimci::nokta((int) $o['seviye']) . '/5' : '—') ?></td><?php endif; ?>
                <td data-etiket="Sıra"><?= (int) $o['sira'] ?></td>
                <td data-etiket="Durum"><span class="rozet rozet-<?= (int) $o['gorunur'] === 1 ? 'yayinda' : 'taslak' ?>"><?= (int) $o['gorunur'] === 1 ? 'Yayında' : 'Gizli' ?></span></td>
                <td class="sag"><div class="eylemler">
                    <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/cv/' . $o['id'] . '/duzenle')) ?>">Düzenle</a>
                    <form method="post" action="<?= e(url('/admin/cv/' . $o['id'] . '/gorunur')) ?>">
                        <?= csrf_field() ?><button class="btn btn-ghost btn-kucuk" type="submit"><?= (int) $o['gorunur'] === 1 ? 'Gizle' : 'Yayınla' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/cv/' . $o['id'] . '/sil')) ?>" data-onay="Bu kayıt kalıcı olarak silinsin mi?">
                        <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                    </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
