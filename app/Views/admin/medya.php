<?php
/** @var list<array<string,mixed>> $ogeler */
/** @var string|null $tur */
$boyutYaz = static function (int $b): string {
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format(max(1, $b / 1024), 0, ',', '.') . ' KB';
};
?>
<form class="form kart" method="post" action="<?= e(url('/admin/medya/yukle')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="alan">
        <label for="dosyalar">Görsel veya video yükle</label>
        <input type="file" id="dosyalar" name="dosyalar[]" multiple required
               accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm">
        <p class="soluk">Görsel: JPEG, PNG, WebP, GIF (en çok <?= (int) $gorselSinir ?> MB). Video: MP4, WebM (en çok <?= (int) $videoSinir ?> MB).
            Görsellerdeki konum/EXIF bilgisi otomatik temizlenir. Tek seferde en çok 20 dosya.</p>
    </div>
    <div class="form-eylem"><button class="btn btn-primary" type="submit">Yükle</button></div>
</form>

<div class="cip-listesi medya-filtre" aria-label="Filtre">
    <a class="cip<?= $tur === null ? ' cip-aktif' : '' ?>" href="<?= e(url('/admin/medya')) ?>">Tümü</a>
    <a class="cip<?= $tur === 'gorsel' ? ' cip-aktif' : '' ?>" href="<?= e(url('/admin/medya?tur=gorsel')) ?>">Görseller</a>
    <a class="cip<?= $tur === 'video' ? ' cip-aktif' : '' ?>" href="<?= e(url('/admin/medya?tur=video')) ?>">Videolar</a>
</div>

<?php if ($ogeler === []): ?>
    <p class="bos">Henüz medya yüklenmedi.</p>
<?php else: ?>
    <div class="medya-izgara">
        <?php foreach ($ogeler as $m):
            $yol = url('/yuklemeler/' . $m['dosya']);
            $parca = \App\Models\Medya::parca($m);
            ?>
            <article class="kart medya-kart">
                <div class="medya-onizleme">
                    <?php if ($m['tur'] === 'video'): ?>
                        <video src="<?= e($yol) ?>" preload="metadata" muted playsinline controls></video>
                    <?php else: ?>
                        <img src="<?= e($yol) ?>" alt="<?= e((string) ($m['alt_metin'] ?? '')) ?>" loading="lazy">
                    <?php endif; ?>
                </div>
                <div class="medya-bilgi">
                    <p class="soluk"><?= e($m['tur'] === 'video' ? 'Video' : 'Görsel') ?> · <?= e($boyutYaz((int) $m['boyut'])) ?><?php if (!empty($m['genislik'])): ?> · <?= (int) $m['genislik'] ?>×<?= (int) $m['yukseklik'] ?><?php endif; ?></p>
                    <input type="text" class="medya-parca" readonly value="<?= e($parca) ?>" aria-label="Markdown kodu">
                    <button type="button" class="btn btn-ghost btn-kucuk" data-kopyala-deger="<?= e($parca) ?>">Kodu kopyala</button>
                    <?php if ($m['tur'] === 'gorsel'): ?>
                        <form method="post" action="<?= e(url('/admin/medya/' . (int) $m['id'] . '/guncelle')) ?>" class="medya-alt">
                            <?= csrf_field() ?>
                            <label class="gorunmez" for="alt-<?= (int) $m['id'] ?>">Alt metin</label>
                            <input type="text" id="alt-<?= (int) $m['id'] ?>" name="alt_metin" maxlength="255" placeholder="Alt metin / altyazı" value="<?= e((string) ($m['alt_metin'] ?? '')) ?>">
                            <button class="btn btn-ghost btn-kucuk" type="submit">Kaydet</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/medya/' . (int) $m['id'] . '/sil')) ?>" data-onay="Bu dosya kalıcı olarak silinsin mi?">
                        <?= csrf_field() ?>
                        <label class="onay"><input type="checkbox" name="zorla" value="1"> Kullanımdaysa da sil</label>
                        <button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol, 'sayfalamaEk' => $sayfalamaEk]); ?>
