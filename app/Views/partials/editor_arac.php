<?php
/**
 * Markdown editörü için araç çubuğu (admin.js tarafından bağlanır).
 * @var string $hedef metin alanının id değeri
 */
?>
<div class="arac-cubugu" data-editor="<?= e($hedef) ?>">
    <label class="btn btn-ghost btn-kucuk" title="Görsel veya video yükle ve içeriğe ekle">
        Dosya yükle
        <input type="file" class="gorunmez" multiple data-yukle accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm">
    </label>
    <button type="button" class="btn btn-ghost btn-kucuk" data-kutuphane>Kütüphane</button>
    <button type="button" class="btn btn-ghost btn-kucuk" data-sablon="youtube">YouTube</button>
    <button type="button" class="btn btn-ghost btn-kucuk" data-sablon="vimeo">Vimeo</button>
    <button type="button" class="btn btn-ghost btn-kucuk" data-sablon="galeri">Galeri</button>
    <button type="button" class="btn btn-ghost btn-kucuk" data-sablon="kod">Kod bloğu</button>
    <button type="button" class="btn btn-ghost btn-kucuk" data-onizle aria-pressed="false">Önizleme</button>
    <span class="soluk editor-durum" role="status" aria-live="polite"></span>
</div>
<div class="icerik onizleme-alani" data-onizleme="<?= e($hedef) ?>" hidden></div>
<dialog class="medya-dialog" data-dialog="<?= e($hedef) ?>" aria-label="Medya kütüphanesi">
    <div class="dialog-ust">
        <strong>Medya kütüphanesi</strong>
        <button type="button" class="btn btn-ghost btn-kucuk" data-kapat>Kapat</button>
    </div>
    <div class="medya-izgara medya-secici" data-liste></div>
</dialog>
