<?php
$basari = \App\Core\Oturum::flashGelen('basari');
$hata = \App\Core\Oturum::flashGelen('hata');
?>
<?php if (is_string($basari) && $basari !== ''): ?>
    <div class="uyari uyari-basari" role="status"><?= e($basari) ?></div>
<?php endif; ?>
<?php if (is_string($hata) && $hata !== ''): ?>
    <div class="uyari uyari-hata" role="alert"><?= e($hata) ?></div>
<?php endif; ?>
