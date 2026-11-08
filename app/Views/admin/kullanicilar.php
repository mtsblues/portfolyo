<div class="iki-kolon">
    <form class="form kart" method="post" action="<?= e(url('/admin/kullanici/yeni')) ?>" autocomplete="off">
        <div class="kart-baslik"><h2>Yeni kullanıcı</h2></div>
        <?= csrf_field() ?>
        <div class="alan"><label for="u-ad">Ad Soyad</label><input type="text" id="u-ad" name="ad_soyad" required minlength="2" maxlength="120"></div>
        <div class="alan"><label for="u-eposta">E-posta</label><input type="email" id="u-eposta" name="eposta" required maxlength="180"></div>
        <div class="alan"><label for="u-rol">Rol</label>
            <select id="u-rol" name="rol"><option value="editor">Editör</option><option value="admin">Yönetici</option></select></div>
        <div class="alan"><label for="u-parola">Parola</label><input type="password" id="u-parola" name="parola" required minlength="12" maxlength="72" autocomplete="new-password"></div>
        <div class="alan"><label for="u-parola2">Parola (tekrar)</label><input type="password" id="u-parola2" name="parola_tekrar" required minlength="12" maxlength="72" autocomplete="new-password"></div>
        <p class="soluk">En az 12 karakter; büyük/küçük harf, rakam ve özel karakter içermeli.</p>
        <button class="btn btn-primary" type="submit">Oluştur</button>
    </form>

    <div class="kart">
        <div class="kart-baslik"><h2>Kullanıcılar</h2></div>
        <ul class="liste liste-duzenle">
            <?php foreach ($kullanicilar as $u): ?>
                <li>
                    <details>
                        <summary>
                            <strong><?= e($u['ad_soyad']) ?></strong>
                            <span class="soluk"><?= e($u['eposta']) ?> · <?= e($u['rol']) ?><?= (int) $u['aktif'] === 1 ? '' : ' · pasif' ?></span>
                        </summary>
                        <p class="soluk">Son giriş: <?= e($u['son_giris'] !== null ? tarih((string) $u['son_giris'], true) : '—') ?></p>
                        <form class="form" method="post" action="<?= e(url('/admin/kullanici/' . $u['id'] . '/guncelle')) ?>" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="alan"><label>Ad Soyad<input type="text" name="ad_soyad" required minlength="2" maxlength="120" value="<?= e($u['ad_soyad']) ?>"></label></div>
                            <div class="alan"><label>Rol
                                <select name="rol">
                                    <option value="editor"<?= $u['rol'] === 'editor' ? ' selected' : '' ?>>Editör</option>
                                    <option value="admin"<?= $u['rol'] === 'admin' ? ' selected' : '' ?>>Yönetici</option>
                                </select></label></div>
                            <label class="onay"><input type="checkbox" name="aktif" value="1"<?= (int) $u['aktif'] === 1 ? ' checked' : '' ?>> Aktif</label>
                            <div class="alan"><label>Yeni parola <span class="soluk">(değiştirmek için)</span><input type="password" name="parola" maxlength="72" autocomplete="new-password"></label></div>
                            <div class="alan"><label>Yeni parola (tekrar)<input type="password" name="parola_tekrar" maxlength="72" autocomplete="new-password"></label></div>
                            <button class="btn btn-primary btn-kucuk" type="submit">Kaydet</button>
                        </form>
                        <?php if ((int) $u['id'] !== (int) $kullanici['id']): ?>
                            <form method="post" action="<?= e(url('/admin/kullanici/' . $u['id'] . '/sil')) ?>" data-onay="Bu kullanıcı silinsin mi?">
                                <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                            </form>
                        <?php endif; ?>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
