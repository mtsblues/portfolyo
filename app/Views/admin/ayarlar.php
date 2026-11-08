<?php
/** @var array<string,string> $ayarlar */
$alanHtml = static function (string $anahtar, string $tip, int $maks, string $deger, array $ek = []): string {
    $id = 'a-' . e($anahtar);
    if ($tip === 'secim') {
        $o = '<select id="' . $id . '" name="' . e($anahtar) . '">';
        foreach ($ek as $deg => $etiket) {
            $o .= '<option value="' . e((string) $deg) . '"' . ((string) $deg === $deger ? ' selected' : '') . '>' . e((string) $etiket) . '</option>';
        }
        return $o . '</select>';
    }
    if ($tip === 'number' && isset($ek[0], $ek[1])) {
        return '<input type="number" id="' . $id . '" name="' . e($anahtar) . '" min="' . (int) $ek[0] . '" max="' . (int) $ek[1] . '" value="' . e($deger) . '">';
    }
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
            <?php foreach ($grup['alanlar'] as $anahtar => $tanim): [$etiket, $tip, $maks, $zorunlu, $ipucu] = $tanim; ?>
                <?php
                $ham = eski($anahtar, $ayarlar[$anahtar] ?? '');
                if ($tip === 'onay' && $eskiVar) {
                    $ham = isset(\App\Core\Oturum::flashGelen('eski')[$anahtar]) ? '1' : '0';
                }
                ?>
                <div class="alan">
                    <label for="a-<?= e($anahtar) ?>"><?= e($etiket) ?><?= $zorunlu ? ' *' : '' ?></label>
                    <?= $alanHtml($anahtar, $tip, $maks, $ham, (array) ($tanim[5] ?? [])) ?>
                    <?php if ($ipucu !== ''): ?><p class="soluk"><?= e($ipucu) ?></p><?php endif; ?>
                    <?php if (hata_mesaji($anahtar) !== ''): ?><p class="alan-hata" role="alert"><?= e(hata_mesaji($anahtar)) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
    <div class="form-eylem"><button class="btn btn-primary" type="submit">Kaydet</button></div>
</form>
