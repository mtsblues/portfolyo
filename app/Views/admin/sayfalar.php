<?php /** @var list<array> $sayfalar */ ?>
<div class="arac-cubugu">
    <a class="btn btn-primary" href="<?= e(url('/admin/sayfa/yeni')) ?>">+ Yeni sayfa</a>
</div>

<?php if ($sablonlar !== []): ?>
<section class="kart">
    <div class="kart-baslik"><h2>Hazır şablonlar</h2></div>
    <p class="soluk">Genel bir başlangıç metnidir, <strong>hukuki danışmanlık değildir</strong>; yayımlamadan önce kendi durumunuza göre gözden geçirin. Site adı, e-posta, saklama süresi ve IP politikası ayarlarınızdan doldurulur; taslak olarak oluşturulur.</p>
    <ul class="liste">
        <?php foreach ($sablonlar as $anahtar => $s): ?>
            <li>
                <form method="post" action="<?= e(url('/admin/sayfa/sablondan')) ?>" class="satir-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="sablon" value="<?= e((string) $anahtar) ?>">
                    <strong><?= e($s['baslik']) ?></strong> <span class="soluk">— <?= e($s['aciklama']) ?></span>
                    <button class="btn btn-ghost btn-kucuk" type="submit">Oluştur</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="kart tablo-kap">
    <?php if ($sayfalar === []): ?><p class="bos">Henüz sayfa yok.</p><?php else: ?>
    <table class="tablo">
        <thead><tr><th>Başlık</th><th>Adres</th><th>Durum</th><th>Konum</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($sayfalar as $s): ?>
            <tr>
                <td data-etiket="Başlık"><a href="<?= e(url('/admin/sayfa/' . $s['id'] . '/duzenle')) ?>"><?= e($s['baslik']) ?></a>
                    <?php if ($kvkkSlug === $s['slug']): ?><span class="rozet rozet-vurgu">İletişim formu KVKK metni</span><?php endif; ?></td>
                <td data-etiket="Adres"><code>/sayfa/<?= e($s['slug']) ?></code></td>
                <td data-etiket="Durum"><span class="rozet rozet-<?= e($s['durum']) ?>"><?= e($s['durum']) ?></span></td>
                <td data-etiket="Konum" class="soluk"><?= (int) $s['menude'] === 1 ? 'Menü ' : '' ?><?= (int) $s['altbilgide'] === 1 ? 'Altbilgi' : '' ?></td>
                <td class="sag eylemler">
                    <?php if ($s['durum'] === 'yayinda'): ?><a class="btn btn-ghost btn-kucuk" href="<?= e(url('/sayfa/' . $s['slug'])) ?>" target="_blank" rel="noopener">Gör</a><?php endif; ?>
                    <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/sayfa/' . $s['id'] . '/duzenle')) ?>">Düzenle</a>
                    <form method="post" action="<?= e(url('/admin/sayfa/' . $s['id'] . '/sil')) ?>" data-onay="Bu sayfa kalıcı olarak silinsin mi?">
                        <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
