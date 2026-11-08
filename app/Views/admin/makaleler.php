<div class="arac-cubugu">
    <a class="btn btn-primary" href="<?= e(url('/admin/makale/yeni')) ?>">+ Yeni makale</a>
</div>
<div class="kart tablo-kap">
    <?php if ($makaleler === []): ?><p class="bos">Henüz makale yok.</p><?php else: ?>
    <table class="tablo">
        <thead><tr><th>Başlık</th><th>Kategori</th><th>Durum</th><th>Yayın</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($makaleler as $m): ?>
            <tr>
                <td data-etiket="Başlık"><a href="<?= e(url('/admin/makale/' . $m['id'] . '/duzenle')) ?>"><?= e($m['baslik']) ?></a>
                    <?php if ((int) $m['one_cikan'] === 1): ?><span class="rozet rozet-vurgu">Öne çıkan</span><?php endif; ?></td>
                <td data-etiket="Kategori"><?= e($m['kategori_ad']) ?></td>
                <td data-etiket="Durum"><span class="rozet rozet-<?= e($m['durum']) ?>"><?= e($m['durum']) ?></span></td>
                <td data-etiket="Yayın" class="soluk"><?= e(tarih($m['yayin_tarihi'], true)) ?></td>
                <td class="sag eylemler">
                    <?php if ($m['durum'] === 'yayinda'): ?><a class="btn btn-ghost btn-kucuk" href="<?= e(url('/makale/' . $m['slug'])) ?>" target="_blank" rel="noopener">Gör</a><?php endif; ?>
                    <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/makale/' . $m['id'] . '/duzenle')) ?>">Düzenle</a>
                    <form method="post" action="<?= e(url('/admin/makale/' . $m['id'] . '/sil')) ?>" data-onay="Bu makale kalıcı olarak silinsin mi?">
                        <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol]); ?>
