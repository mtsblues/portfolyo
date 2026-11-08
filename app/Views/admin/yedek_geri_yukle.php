<?php
/** @var string $yedek */
/** @var ?array $rapor */
$bicim = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 1, ',', '.') . ' KB';
$uygun = $medya || ($rapor !== null && $rapor['ok']);
?>
<section class="kart dar-form">
    <div class="kart-baslik"><h2>Geri yükleme</h2></div>
    <p><code><?= e($yedek) ?></code> · <?= e($bicim($boyut)) ?> · <?= e(tarih(gmdate('Y-m-d H:i:s', $zaman), true)) ?></p>

    <?php if ($medya): ?>
        <div class="uyari uyari-basari" role="status">Medya arşivi: yalnızca geçerli yükleme adlı dosyalar <code>storage/yuklemeler</code> içine açılır; aynı adlı dosyaların üzerine yazılır, başka hiçbir şey silinmez.</div>
    <?php elseif ($rapor !== null && !$rapor['ok']): ?>
        <div class="uyari uyari-hata" role="alert">
            <strong>Bu yedek geri yüklenemez:</strong>
            <ul><?php foreach (array_slice($rapor['hatalar'], 0, 8) as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
        </div>
    <?php else: ?>
        <div class="uyari uyari-basari" role="status">
            <strong>Kuru çalıştırma başarılı</strong> — yedek doğrulandı, henüz hiçbir şey değiştirilmedi.
            <ul>
                <li>Yedek tarihi: <?= e((string) ($rapor['tarih'] ?? 'bilinmiyor')) ?> UTC · şema sürümü: <?= e((string) ($rapor['sema'] ?? 'eski')) ?></li>
                <li><?= count($rapor['tablolar']) ?> tablo, <?= (int) $rapor['ifade'] ?> ifade</li>
            </ul>
        </div>
        <details>
            <summary>Tablo ayrıntıları</summary>
            <ul>
                <?php foreach ($rapor['tablolar'] as $t => $n): ?><li><code><?= e((string) $t) ?></code>: <?= (int) $n ?> satır</li><?php endforeach; ?>
            </ul>
        </details>
        <div class="uyari uyari-hata" role="alert">
            <strong>Dikkat:</strong> Şu anki tüm veriler (makaleler, projeler, mesajlar, kullanıcılar, ayarlar) bu yedeğin içeriğiyle <strong>değiştirilecek</strong>.
            İşlemden hemen önce mevcut durum otomatik olarak yedeklenir; bir hata olursa kendiliğinden geri alınır. Başarıdan sonra oturumunuz kapanır.
        </div>
    <?php endif; ?>

    <?php if ($uygun): ?>
    <form class="form" method="post" action="<?= e(url('/admin/yedekler/' . $yedek . '/geri-yukle')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="alan">
            <label for="mevcut_parola">Parolanız</label>
            <input type="password" id="mevcut_parola" name="mevcut_parola" required maxlength="1024" autocomplete="current-password">
        </div>
        <?php if ($iki2fa): ?>
        <div class="alan">
            <label for="kod">İki adımlı doğrulama kodu</label>
            <input type="text" id="kod" name="kod" class="kod-girdi" required maxlength="12" autocomplete="one-time-code">
        </div>
        <?php endif; ?>
        <div class="alan">
            <label for="onay">Onay için <strong>GERI YUKLE</strong> yazın</label>
            <input type="text" id="onay" name="onay" required maxlength="20" autocomplete="off">
        </div>
        <div class="form-eylem">
            <button class="btn btn-tehlike" type="submit">Geri yükle</button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/yedekler')) ?>">Vazgeç</a>
        </div>
    </form>
    <?php else: ?>
        <p><a class="btn btn-ghost" href="<?= e(url('/admin/yedekler')) ?>">← Yedeklere dön</a></p>
    <?php endif; ?>
</section>
