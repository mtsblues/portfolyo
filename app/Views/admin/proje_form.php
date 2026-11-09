<?php
/** @var array|null $proje */
$yeni = $proje === null;
$aksiyon = $yeni ? '/admin/proje/yeni' : '/admin/proje/' . (int) $proje['id'] . '/guncelle';
$v = static fn(string $k, mixed $varsayilan = '') => eski($k, $proje[$k] ?? $varsayilan);
$durum = $v('durum', 'aktif');
$hataGoster = static function (string $alan): void {
    $h = hata_mesaji($alan);
    if ($h !== '') {
        echo '<p class="alan-hata" role="alert">' . e($h) . '</p>';
    }
};
?>
<form class="form kart" method="post" action="<?= e(url($aksiyon)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="alan">
        <label for="baslik">Başlık</label>
        <input type="text" id="baslik" name="baslik" required minlength="3" maxlength="180" value="<?= e($v('baslik')) ?>">
        <?php $hataGoster('baslik'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="slug">Slug <span class="soluk">(boşsa başlıktan üretilir)</span></label>
            <input type="text" id="slug" name="slug" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($v('slug')) ?>">
            <?php $hataGoster('slug'); ?>
        </div>
        <div class="alan">
            <label for="durum">Durum</label>
            <select id="durum" name="durum">
                <?php foreach (\App\Models\Proje::DURUMLAR as $d => $ad): ?>
                    <option value="<?= e($d) ?>"<?= $durum === $d ? ' selected' : '' ?>><?= e($ad) ?></option>
                <?php endforeach; ?>
            </select>
            <?php $hataGoster('durum'); ?>
        </div>
    </div>
    <div class="alan">
        <label for="aciklama">Kısa açıklama</label>
        <textarea id="aciklama" name="aciklama" rows="2" required minlength="10" maxlength="500"><?= e($v('aciklama')) ?></textarea>
        <?php $hataGoster('aciklama'); ?>
    </div>
    <div class="alan">
        <label for="icerik">Ayrıntılı içerik <span class="soluk">(Markdown)</span></label>
        <?php partial('editor_arac', ['hedef' => 'icerik']); ?>
        <textarea id="icerik" name="icerik" rows="14" required class="mono"><?= e($v('icerik')) ?></textarea>
        <?php $hataGoster('icerik'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="proje_url">Proje adresi</label>
            <input type="url" id="proje_url" name="proje_url" maxlength="255" value="<?= e($v('proje_url')) ?>" placeholder="https://">
            <?php $hataGoster('proje_url'); ?>
        </div>
        <div class="alan">
            <label for="github_url">Kaynak kod adresi</label>
            <input type="url" id="github_url" name="github_url" maxlength="255" value="<?= e($v('github_url')) ?>" placeholder="https://github.com/...">
            <?php $hataGoster('github_url'); ?>
        </div>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="sira">Sıra <span class="soluk">(küçük önce)</span></label>
            <input type="number" id="sira" name="sira" step="1" min="-999999" max="999999" value="<?= e($v('sira', 0)) ?>">
            <?php $hataGoster('sira'); ?>
        </div>
        <div class="alan">
            <label for="tamamlanma_tarihi">Tamamlanma tarihi</label>
            <input type="date" id="tamamlanma_tarihi" name="tamamlanma_tarihi" value="<?= e($v('tamamlanma_tarihi')) ?>">
            <?php $hataGoster('tamamlanma_tarihi'); ?>
        </div>
    </div>
    <div class="alan">
        <label for="teknolojiler">Teknolojiler <span class="soluk">(virgülle ayırın; en çok 12, ziyaretçiler bunlara göre filtreleyebilir)</span></label>
        <input type="text" id="teknolojiler" name="teknolojiler" maxlength="400" placeholder="PHP, MySQL, Docker" value="<?= e($v('teknolojiler')) ?>">
        <?php $hataGoster('teknolojiler'); ?>
    </div>
    <fieldset class="alan-grup">
        <legend>Vaka çalışması <span class="soluk">(isteğe bağlı — doluysa proje sayfasında Problem → Çözüm → Sonuç olarak görünür)</span></legend>
        <div class="alan">
            <label for="problem">Problem</label>
            <textarea id="problem" name="problem" rows="3" maxlength="3000"><?= e($v('problem')) ?></textarea>
            <?php $hataGoster('problem'); ?>
        </div>
        <div class="alan">
            <label for="cozum">Çözüm</label>
            <textarea id="cozum" name="cozum" rows="3" maxlength="3000"><?= e($v('cozum')) ?></textarea>
            <?php $hataGoster('cozum'); ?>
        </div>
        <div class="alan">
            <label for="sonuc">Sonuç <span class="soluk">(ölçülebilir olursa daha etkili: “yükleme süresi %40 azaldı”)</span></label>
            <textarea id="sonuc" name="sonuc" rows="3" maxlength="3000"><?= e($v('sonuc')) ?></textarea>
            <?php $hataGoster('sonuc'); ?>
        </div>
    </fieldset>
    <div class="alan">
        <label for="gorsel">Görsel <span class="soluk">(JPEG/PNG/WebP)</span></label>
        <input type="file" id="gorsel" name="gorsel" accept="image/jpeg,image/png,image/webp">
        <?php $hataGoster('gorsel'); ?>
        <?php if (!$yeni && !empty($proje['gorsel'])): ?>
            <p><img class="onizleme" src="<?= e(url('/yuklemeler/' . $proje['gorsel'])) ?>" alt="Mevcut görsel">
                <label class="onay"><input type="checkbox" name="gorsel_sil" value="1"> Görseli kaldır</label></p>
        <?php endif; ?>
    </div>
    <label class="onay"><input type="checkbox" name="one_cikan" value="1"<?= (string) $v('one_cikan', '0') === '1' ? ' checked' : '' ?>> Öne çıkan proje</label>
    <div class="form-eylem">
        <button class="btn btn-primary" type="submit"><?= $yeni ? 'Oluştur' : 'Kaydet' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/projeler')) ?>">Vazgeç</a>
    </div>
</form>
