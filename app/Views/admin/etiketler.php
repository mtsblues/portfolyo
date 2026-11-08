<div class="iki-kolon">
    <form class="form kart" method="post" action="<?= e(url('/admin/etiket/yeni')) ?>">
        <div class="kart-baslik"><h2>Yeni etiket</h2></div>
        <?= csrf_field() ?>
        <div class="alan"><label for="e-ad">Ad</label><input type="text" id="e-ad" name="ad" required minlength="2" maxlength="80"></div>
        <button class="btn btn-primary" type="submit">Ekle</button>
    </form>

    <div class="kart">
        <div class="kart-baslik"><h2>Etiketler</h2></div>
        <?php if ($etiketler === []): ?><p class="bos">Henüz etiket yok. Makale formundan da etiket oluşturabilirsiniz.</p><?php else: ?>
        <ul class="liste liste-duzenle">
            <?php foreach ($etiketler as $t): ?>
                <li>
                    <details>
                        <summary><strong>#<?= e($t['ad']) ?></strong> <span class="soluk"><?= (int) $t['makale_sayisi'] ?> makale</span></summary>
                        <form class="form satir-form" method="post" action="<?= e(url('/admin/etiket/' . $t['id'] . '/guncelle')) ?>">
                            <?= csrf_field() ?>
                            <input type="text" name="ad" required minlength="2" maxlength="80" value="<?= e($t['ad']) ?>" aria-label="Etiket adı">
                            <button class="btn btn-primary btn-kucuk" type="submit">Kaydet</button>
                        </form>
                        <form method="post" action="<?= e(url('/admin/etiket/' . $t['id'] . '/sil')) ?>" data-onay="Bu etiket silinsin mi?">
                            <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                        </form>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
