<?php
/** @var array|null $oge */
/** @var string $tur */
use App\Models\CvOgesi;

$t = CvOgesi::TURLER[$tur];
$a = $t['alanlar'];
$yeni = $oge === null;
$aksiyon = $yeni ? '/admin/cv/yeni' : '/admin/cv/' . (int) $oge['id'] . '/guncelle';
$v = static fn(string $k, mixed $varsayilan = '') => eski($k, $oge[$k] ?? $varsayilan);
$eskiVar = \App\Core\Oturum::flashGelen('eski') !== null;
$isaret = static function (string $k, int $varsayilan) use ($oge, $eskiVar): bool {
    if ($eskiVar) {
        return isset(\App\Core\Oturum::flashGelen('eski')[$k]);
    }
    return (int) ($oge[$k] ?? $varsayilan) === 1;
};
$hataGoster = static function (string $alan): void {
    $h = hata_mesaji($alan);
    if ($h !== '') {
        echo '<p class="alan-hata" role="alert">' . e($h) . '</p>';
    }
};
?>
<form class="form kart" method="post" action="<?= e(url($aksiyon)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="tur" value="<?= e($tur) ?>">
    <div class="alan">
        <label for="baslik"><?= e($t['baslik']) ?></label>
        <input type="text" id="baslik" name="baslik" required minlength="2" maxlength="200" value="<?= e((string) $v('baslik')) ?>">
        <?php $hataGoster('baslik'); ?>
    </div>

    <?php if (isset($a['kurum']) || isset($a['konum'])): ?>
    <div class="form-satir">
        <?php if (isset($a['kurum'])): ?>
        <div class="alan">
            <label for="kurum"><?= e($a['kurum']) ?></label>
            <input type="text" id="kurum" name="kurum" maxlength="200" value="<?= e((string) $v('kurum')) ?>">
            <?php $hataGoster('kurum'); ?>
        </div>
        <?php endif; ?>
        <?php if (isset($a['konum'])): ?>
        <div class="alan">
            <label for="konum"><?= e($a['konum']) ?></label>
            <input type="text" id="konum" name="konum" maxlength="120" value="<?= e((string) $v('konum')) ?>">
            <?php $hataGoster('konum'); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($a['tarih'])): ?>
    <div class="form-satir">
        <div class="alan">
            <label for="baslangic">Başlangıç</label>
            <input type="month" id="baslangic" name="baslangic" value="<?= e((string) $v('baslangic')) ?>">
            <?php $hataGoster('baslangic'); ?>
        </div>
        <div class="alan">
            <label for="bitis">Bitiş</label>
            <input type="month" id="bitis" name="bitis" value="<?= e((string) $v('bitis')) ?>">
            <?php $hataGoster('bitis'); ?>
            <label class="onay"><input type="checkbox" name="devam" value="1"<?= $isaret('devam', 0) ? ' checked' : '' ?>> Devam ediyor (bitiş tarihini yok say)</label>
        </div>
    </div>
    <?php elseif (isset($a['baslangic'])): ?>
    <div class="form-satir">
        <div class="alan">
            <label for="baslangic"><?= e($a['baslangic']) ?></label>
            <input type="month" id="baslangic" name="baslangic" value="<?= e((string) $v('baslangic')) ?>">
            <?php $hataGoster('baslangic'); ?>
        </div>
        <?php if (isset($a['bitis'])): ?>
        <div class="alan">
            <label for="bitis"><?= e($a['bitis']) ?></label>
            <input type="month" id="bitis" name="bitis" value="<?= e((string) $v('bitis')) ?>">
            <?php $hataGoster('bitis'); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($a['seviye'])): ?>
    <div class="alan">
        <label for="seviye">Seviye <span class="soluk">(0–100; 1–20 = 1 nokta … 81–100 = 5 nokta)</span></label>
        <input type="number" id="seviye" name="seviye" min="0" max="100" step="1" value="<?= e((string) $v('seviye')) ?>">
        <?php $hataGoster('seviye'); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($a['aciklama'])): ?>
    <div class="alan">
        <label for="aciklama"><?= e($a['aciklama']) ?></label>
        <textarea id="aciklama" name="aciklama" rows="6" maxlength="3000" class="mono"<?= in_array($tur, ['hizmet', 'referans'], true) ? ' required' : '' ?>><?= e((string) $v('aciklama')) ?></textarea>
        <?php $hataGoster('aciklama'); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($a['url'])): ?>
    <div class="alan">
        <label for="url"><?= e($a['url']) ?></label>
        <input type="url" id="url" name="url" maxlength="255" placeholder="https://" value="<?= e((string) $v('url')) ?>">
        <?php $hataGoster('url'); ?>
    </div>
    <?php endif; ?>

    <div class="form-satir">
        <div class="alan">
            <label for="sira">Sıra <span class="soluk">(küçük önce<?= in_array($tur, ['deneyim', 'egitim', 'sertifika'], true) ? '; aynı tarihte geçerli' : '' ?>)</span></label>
            <input type="number" id="sira" name="sira" min="0" max="100000" value="<?= e((string) $v('sira', '0')) ?>">
            <?php $hataGoster('sira'); ?>
        </div>
        <div class="alan">
            <label class="onay"><input type="checkbox" name="gorunur" value="1"<?= $isaret('gorunur', 1) ? ' checked' : '' ?>> Sitede göster</label>
        </div>
    </div>
    <div class="form-eylem">
        <button class="btn btn-primary" type="submit"><?= $yeni ? 'Ekle' : 'Kaydet' ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/cv?tur=' . $tur)) ?>">Vazgeç</a>
    </div>
</form>
