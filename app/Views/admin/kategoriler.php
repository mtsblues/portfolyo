<div class="iki-kolon">
    <form class="form kart" method="post" action="<?= e(url('/admin/kategori/yeni')) ?>">
        <div class="kart-baslik"><h2>Yeni kategori</h2></div>
        <?= csrf_field() ?>
        <div class="alan"><label for="k-ad">Ad</label><input type="text" id="k-ad" name="ad" required minlength="2" maxlength="120"></div>
        <div class="alan"><label for="k-slug">Slug <span class="soluk">(opsiyonel)</span></label><input type="text" id="k-slug" name="slug" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*"></div>
        <div class="alan"><label for="k-aciklama">Açıklama</label><textarea id="k-aciklama" name="aciklama" rows="2" maxlength="500"></textarea></div>
        <button class="btn btn-primary" type="submit">Ekle</button>
    </form>

    <div class="kart">
        <div class="kart-baslik"><h2>Kategoriler</h2></div>
        <?php if ($kategoriler === []): ?><p class="bos">Henüz kategori yok.</p><?php else: ?>
        <ul class="liste liste-duzenle">
            <?php foreach ($kategoriler as $k): ?>
                <li>
                    <details>
                        <summary><strong><?= e($k['ad']) ?></strong> <span class="soluk">/<?= e($k['slug']) ?> · <?= (int) $k['makale_sayisi'] ?> makale</span></summary>
                        <form class="form" method="post" action="<?= e(url('/admin/kategori/' . $k['id'] . '/guncelle')) ?>">
                            <?= csrf_field() ?>
                            <div class="alan"><label>Ad<input type="text" name="ad" required minlength="2" maxlength="120" value="<?= e($k['ad']) ?>"></label></div>
                            <div class="alan"><label>Slug<input type="text" name="slug" required maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($k['slug']) ?>"></label></div>
                            <div class="alan"><label>Açıklama<textarea name="aciklama" rows="2" maxlength="500"><?= e($k['aciklama'] ?? '') ?></textarea></label></div>
                            <button class="btn btn-primary btn-kucuk" type="submit">Kaydet</button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/kategori/' . $k['id'] . '/sil')) ?>" data-onay="Bu kategori silinsin mi?">
                            <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                        </form>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
