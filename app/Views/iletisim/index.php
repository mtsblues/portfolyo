<section class="bolum kap dar">
    <header class="sayfa-ust">
        <h1>İletişim</h1>
        <p class="soluk">Bir proje, soru ya da iş birliği için yazın. Mesajınız güvenle iletilir.</p>
    </header>

    <form id="iletisim-form" class="form kart" method="post" action="<?= e(url('/iletisim/gonder')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="gorunmez" aria-hidden="true">
            <label for="web_sitesi">Bu alanı boş bırakın</label>
            <input type="text" id="web_sitesi" name="web_sitesi" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-satir">
            <div class="alan">
                <label for="ad_soyad">Ad Soyad</label>
                <input type="text" id="ad_soyad" name="ad_soyad" required minlength="2" maxlength="120" autocomplete="name">
                <p class="alan-hata" data-hata-icin="ad_soyad" role="alert"></p>
            </div>
            <div class="alan">
                <label for="eposta">E-posta</label>
                <input type="email" id="eposta" name="eposta" required maxlength="180" autocomplete="email">
                <p class="alan-hata" data-hata-icin="eposta" role="alert"></p>
            </div>
        </div>
        <div class="alan">
            <label for="konu">Konu</label>
            <input type="text" id="konu" name="konu" required minlength="3" maxlength="180">
            <p class="alan-hata" data-hata-icin="konu" role="alert"></p>
        </div>
        <div class="alan">
            <label for="mesaj">Mesajınız</label>
            <textarea id="mesaj" name="mesaj" rows="7" required minlength="10" maxlength="5000"></textarea>
            <p class="alan-hata" data-hata-icin="mesaj" role="alert"></p>
        </div>
        <?php $kvkk = trim(ayar('kvkk_sayfa')); if ($kvkk !== '' && \App\Models\Sayfa::yayindaMi($kvkk)): ?>
        <div class="alan">
            <label class="onay">
                <input type="checkbox" id="kvkk_onay" name="kvkk_onay" value="1" required>
                <span><a href="<?= e(url('/sayfa/' . $kvkk)) ?>" target="_blank" rel="noopener">Aydınlatma metnini</a> okudum; verilerimin mesajıma yanıt vermek amacıyla işlenmesini kabul ediyorum.</span>
            </label>
            <p class="alan-hata" data-hata-icin="kvkk_onay" role="alert"></p>
        </div>
        <?php endif; ?>
        <div id="form-sonuc" class="uyari gizli" role="status" aria-live="polite"></div>
        <button class="btn btn-primary" type="submit" id="gonder-dugme">Mesajı gönder</button>
    </form>
</section>
