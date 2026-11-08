<?php
/** @var array|null $makale */
$yeni = $makale === null;
$aksiyon = $yeni ? '/admin/makale/yeni' : '/admin/makale/' . (int) $makale['id'] . '/guncelle';
$v = static fn(string $k, mixed $varsayilan = '') => eski($k, $makale[$k] ?? $varsayilan);
$durum = $v('durum', 'taslak');
$secKat = (string) $v('kategori_id');
$yayinDegeri = \App\Core\Oturum::flashGelen('eski') !== null ? eski('yayin_tarihi') : utc_den_yerel($makale['yayin_tarihi'] ?? null);
$etiketDegeri = \App\Core\Oturum::flashGelen('eski') !== null ? eski('etiketler') : $etiketMetni;
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
        <input type="text" id="baslik" name="baslik" required minlength="3" maxlength="220" value="<?= e($v('baslik')) ?>">
        <?php $hataGoster('baslik'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="slug">Slug <span class="soluk">(boşsa başlıktan üretilir)</span></label>
            <input type="text" id="slug" name="slug" maxlength="220" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e($v('slug')) ?>">
            <?php $hataGoster('slug'); ?>
        </div>
        <div class="alan">
            <label for="kategori_id">Kategori</label>
            <select id="kategori_id" name="kategori_id" required>
                <option value="">Seçin…</option>
                <?php foreach ($kategoriler as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"<?= $secKat === (string) $k['id'] ? ' selected' : '' ?>><?= e($k['ad']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php $hataGoster('kategori_id'); ?>
        </div>
    </div>
    <div class="alan">
        <label for="ozet">Özet</label>
        <textarea id="ozet" name="ozet" rows="2" maxlength="500"><?= e($v('ozet')) ?></textarea>
        <?php $hataGoster('ozet'); ?>
    </div>
    <div class="alan">
        <label for="icerik">İçerik <span class="soluk">(Markdown; kod için ```php ... ```)</span></label>
        <?php partial('editor_arac', ['hedef' => 'icerik']); ?>
        <textarea id="icerik" name="icerik" rows="18" required class="mono"><?= e($v('icerik')) ?></textarea>
        <?php $hataGoster('icerik'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="durum">Durum</label>
            <select id="durum" name="durum">
                <?php foreach (['taslak' => 'Taslak', 'yayinda' => 'Yayında', 'arsiv' => 'Arşiv'] as $d => $ad): ?>
                    <option value="<?= e($d) ?>"<?= $durum === $d ? ' selected' : '' ?>><?= e($ad) ?></option>
                <?php endforeach; ?>
            </select>
            <?php $hataGoster('durum'); ?>
        </div>
        <div class="alan">
            <label for="yayin_tarihi">Yayın tarihi <span class="soluk">(boşsa şimdi)</span></label>
            <input type="datetime-local" id="yayin_tarihi" name="yayin_tarihi" value="<?= e($yayinDegeri) ?>">
            <?php $hataGoster('yayin_tarihi'); ?>
        </div>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="etiketler">Etiketler <span class="soluk">(virgülle ayırın)</span></label>
            <input type="text" id="etiketler" name="etiketler" maxlength="500" value="<?= e($etiketDegeri) ?>">
            <?php $hataGoster('etiketler'); ?>
        </div>
        <div class="alan">
            <label for="anahtar_kelimeler">Anahtar kelimeler (SEO)</label>
            <input type="text" id="anahtar_kelimeler" name="anahtar_kelimeler" maxlength="500" value="<?= e($v('anahtar_kelimeler')) ?>">
            <?php $hataGoster('anahtar_kelimeler'); ?>
        </div>
    </div>
    <div class="alan">
        <label for="kapak">Kapak görseli <span class="soluk">(JPEG/PNG/WebP, en çok <?= (int) (config('yukleme.maks_bayt', 2097152) / 1048576) ?> MB)</span></label>
        <input type="file" id="kapak" name="kapak" accept="image/jpeg,image/png,image/webp">
        <?php $hataGoster('kapak'); ?>
        <?php if (!$yeni && !empty($makale['kapak_gorsel'])): ?>
            <p><img class="onizleme" src="<?= e(url('/yuklemeler/' . $makale['kapak_gorsel'])) ?>" alt="Mevcut kapak">
                <label class="onay"><input type="checkbox" name="kapak_sil" value="1"> Kapağı kaldır</label></p>
        <?php endif; ?>
    </div>
    <label class="onay"><input type="checkbox" name="one_cikan" value="1"<?= ($v('one_cikan', '0') === '1' || $v('one_cikan', '0') === 1) ? ' checked' : '' ?>> Öne çıkan makale</label>
    <div class="form-eylem">
        <button class="btn btn-primary" type="submit"><?= $yeni ? 'Oluştur' : 'Kaydet' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/makaleler')) ?>">Vazgeç</a>
    </div>
</form>
