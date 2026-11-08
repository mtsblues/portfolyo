<div class="arac-cubugu">
    <a class="btn btn-primary" href="<?= e(url('/admin/proje/yeni')) ?>">+ Yeni proje</a>
</div>
<div class="kart tablo-kap">
    <?php if ($projeler === []): ?><p class="bos">Henüz proje yok.</p><?php else: ?>
    <table class="tablo">
        <thead><tr><th>Başlık</th><th>Durum</th><th>Sıra</th><th class="sag">İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($projeler as $p): ?>
            <tr>
                <td data-etiket="Başlık"><a href="<?= e(url('/admin/proje/' . $p['id'] . '/duzenle')) ?>"><?= e($p['baslik']) ?></a>
                    <?php if ((int) $p['one_cikan'] === 1): ?><span class="rozet rozet-vurgu">Öne çıkan</span><?php endif; ?></td>
                <td data-etiket="Durum"><span class="rozet"><?= e(\App\Models\Proje::DURUMLAR[$p['durum']] ?? $p['durum']) ?></span></td>
                <td data-etiket="Sıra" class="soluk"><?= (int) $p['sira'] ?></td>
                <td class="sag eylemler">
                    <?php if ($p['durum'] !== 'arsiv'): ?><a class="btn btn-ghost btn-kucuk" href="<?= e(url('/proje/' . $p['slug'])) ?>" target="_blank" rel="noopener">Gör</a><?php endif; ?>
                    <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/proje/' . $p['id'] . '/duzenle')) ?>">Düzenle</a>
                    <form method="post" action="<?= e(url('/admin/proje/' . $p['id'] . '/sil')) ?>" data-onay="Bu proje kalıcı olarak silinsin mi?">
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
