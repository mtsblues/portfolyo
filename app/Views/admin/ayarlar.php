<?php
/** @var array<string,string> $ayarlar */
$alanHtml = static function (string $anahtar, string $tip, int $maks, string $deger): string {
    $id = 'a-' . e($anahtar);
    if ($tip === 'metin') {
        return '<textarea id="' . $id . '" name="' . e($anahtar) . '" rows="10" maxlength="' . $maks . '" class="mono">' . e($deger) . '</textarea>';
    }
    if ($tip === 'onay') {
        return '<label class="onay"><input type="checkbox" id="' . $id . '" name="' . e($anahtar) . '" value="1"'
            . ($deger === '1' ? ' checked' : '') . '> Etkin</label>';
    }
    $html = ['email' => 'email', 'number' => 'number', 'url' => 'url'][$tip] ?? 'text';
    return '<input type="' . $html . '" id="' . $id . '" name="' . e($anahtar) . '" maxlength="' . $maks . '" value="' . e($deger) . '">';
};
$eskiVar = \App\Core\Oturum::flashGelen('eski') !== null;
?>
<form class="form dar-form" method="post" action="<?= e(url('/admin/ayarlar/guncelle')) ?>">
    <?= csrf_field() ?>
    <?php foreach (\App\Models\Ayar::GRUPLAR as $grup): ?>
        <section class="kart ayar-grup">
            <h2><?= e($grup['baslik']) ?></h2>
            <p class="soluk"><?= e($grup['aciklama']) ?></p>
            <?php foreach ($grup['alanlar'] as $anahtar => [$etiket, $tip, $maks, $zorunlu, $ipucu]): ?>
                <?php
                $ham = eski($anahtar, $ayarlar[$anahtar] ?? '');
                if ($tip === 'onay' && $eskiVar) {
                    $ham = isset(\App\Core\Oturum::flashGelen('eski')[$anahtar]) ? '1' : '0';
                }
                ?>
                <div class="alan">
                    <label for="a-<?= e($anahtar) ?>"><?= e($etiket) ?><?= $zorunlu ? ' *' : '' ?></label>
                    <?= $alanHtml($anahtar, $tip, $maks, $ham) ?>
                    <?php if ($ipucu !== ''): ?><p class="soluk"><?= e($ipucu) ?></p><?php endif; ?>
                    <?php if (hata_mesaji($anahtar) !== ''): ?><p class="alan-hata" role="alert"><?= e(hata_mesaji($anahtar)) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    <div class="form-eylem"><button class="btn btn-primary" type="submit">Kaydet</button></div>
</form>
