<?php
/** @var array|null $sayfa */
$yeni = $sayfa === null;
$aksiyon = $yeni ? '/admin/sayfa/yeni' : '/admin/sayfa/' . (int) $sayfa['id'] . '/guncelle';
$v = static fn(string $k, mixed $varsayilan = '') => eski($k, $sayfa[$k] ?? $varsayilan);
$eskiVar = \App\Core\Oturum::flashGelen('eski') !== null;
$isaret = static function (string $k, int $varsayilan) use ($sayfa, $eskiVar): bool {
    if ($eskiVar) {
        return isset(\App\Core\Oturum::flashGelen('eski')[$k]);
    }
    return (int) ($sayfa[$k] ?? $varsayilan) === 1;
};
$hataGoster = static function (string $alan): void {
    $h = hata_mesaji($alan);
    if ($h !== '') {
        echo '<p class="alan-hata" role="alert">' . e($h) . '</p>';
    }
};
$durum = (string) $v('durum', 'taslak');
?>
<form class="form kart" method="post" action="<?= e(url($aksiyon)) ?>">
    <?= csrf_field() ?>
    <div class="alan">
        <label for="baslik">Başlık</label>
        <input type="text" id="baslik" name="baslik" required minlength="2" maxlength="200" value="<?= e((string) $v('baslik')) ?>">
        <?php $hataGoster('baslik'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label for="slug">Adres (slug) <span class="soluk">(boşsa başlıktan üretilir) — /sayfa/…</span></label>
            <input type="text" id="slug" name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= e((string) $v('slug')) ?>">
            <?php $hataGoster('slug'); ?>
        </div>
        <div class="alan">
            <label for="durum">Durum</label>
            <select id="durum" name="durum">
                <option value="taslak"<?= $durum === 'taslak' ? ' selected' : '' ?>>Taslak</option>
                <option value="yayinda"<?= $durum === 'yayinda' ? ' selected' : '' ?>>Yayında</option>
            </select>
            <?php $hataGoster('durum'); ?>
        </div>
    </div>
    <div class="alan">
        <label for="icerik">İçerik <span class="soluk">(Markdown)</span></label>
        <?php partial('editor_arac', ['hedef' => 'icerik']); ?>
        <textarea id="icerik" name="icerik" rows="18" required class="mono"><?= e((string) $v('icerik')) ?></textarea>
        <?php $hataGoster('icerik'); ?>
    </div>
    <div class="alan">
        <label for="meta_aciklama">Arama motoru açıklaması</label>
        <input type="text" id="meta_aciklama" name="meta_aciklama" maxlength="300" value="<?= e((string) $v('meta_aciklama')) ?>">
        <?php $hataGoster('meta_aciklama'); ?>
    </div>
    <div class="form-satir">
        <div class="alan">
            <label class="onay"><input type="checkbox" name="menude" value="1"<?= $isaret('menude', 0) ? ' checked' : '' ?>> Üst menüde göster</label>
            <label class="onay"><input type="checkbox" name="altbilgide" value="1"<?= $isaret('altbilgide', 1) ? ' checked' : '' ?>> Altbilgide göster</label>
        </div>
        <div class="alan">
            <label for="sira">Sıra <span class="soluk">(küçük önce)</span></label>
            <input type="number" id="sira" name="sira" min="0" max="1000" value="<?= e((string) $v('sira', '10')) ?>">
            <?php $hataGoster('sira'); ?>
        </div>
    </div>
    <div class="form-eylem">
        <button class="btn btn-primary" type="submit"><?= $yeni ? 'Oluştur' : 'Kaydet' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/sayfalar')) ?>">Vazgeç</a>
    </div>
</form>
