<?php
/** @var list<array> $yedekler */
/** @var list<string> $hedefler */
$bicim = static fn(int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 1, ',', '.') . ' KB';
$turEtiket = ['sql' => 'Veritabanı', 'medya' => 'Medya', 'eski_zip' => 'Eski medya (zip)'];
$hedefEtiket = ['eposta' => 'E-posta', 'ftp' => 'FTP/FTPS', 'webdav' => 'WebDAV'];
$yasi = $sonYedek > 0 ? (int) floor((time() - $sonYedek) / 86400) : null;
?>
<section class="kart">
    <div class="kart-baslik"><h2>Durum</h2></div>
    <?php if ($sonYedek === 0): ?>
        <div class="uyari uyari-hata" role="alert">Henüz hiç veritabanı yedeği alınmamış. Aşağıdan şimdi alın; veriniz yalnızca bu sunucuda duruyor.</div>
    <?php elseif ($yasi !== null && $yasi >= 7): ?>
        <div class="uyari uyari-hata" role="alert">Son yedek <?= (int) $yasi ?> gün önce alınmış. Düzenli yedek için Ayarlar → Yedekleme bölümünden otomatik yedeği açın.</div>
    <?php else: ?>
        <p>Son veritabanı yedeği: <strong><?= e(tarih(gmdate('Y-m-d H:i:s', $sonYedek), true)) ?></strong>
            · Otomatik yedek: <strong><?= $otoGun > 0 ? 'her ' . (int) $otoGun . ' günde bir' : 'kapalı' ?></strong></p>
    <?php endif; ?>
    <p class="soluk">Dış hedefler (e-posta, FTP, WebDAV): <?= $hedefler === [] ? 'yapılandırılmamış — sunucudaki yedek, sunucu bozulursa kaybolur. Ayarlar → Yedekleme sayfasına bakın.' : e(implode(', ', array_map(static fn($h) => $hedefEtiket[$h] ?? $h, $hedefler))) ?></p>
    <form class="form" method="post" action="<?= e(url('/admin/yedekler/al')) ?>">
        <?= csrf_field() ?>
        <?php if ($medyaDestek): ?>
            <label class="onay"><input type="checkbox" name="medya" value="1"> Yüklenen medyayı da yedekle (görseller/videolar; ayrı .tar.gz dosyası)</label>
        <?php endif; ?>
        <div class="form-eylem"><button class="btn btn-primary" type="submit">Şimdi yedek al</button></div>
    </form>
</section>

<section class="kart tablo-kap">
    <div class="kart-baslik kart-baslik-ic"><h2>Yedekler</h2></div>
    <?php if ($yedekler === []): ?>
        <p class="soluk bos-ic">Henüz yedek yok.</p>
    <?php else: ?>
    <table class="tablo">
        <thead><tr><th>Dosya</th><th>Tür</th><th>Boyut</th><th>Tarih</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($yedekler as $y): ?>
            <tr>
                <td><code><?= e($y['ad']) ?></code></td>
                <td><span class="rozet"><?= e($turEtiket[$y['tur']] ?? $y['tur']) ?></span></td>
                <td><?= e($bicim($y['boyut'])) ?></td>
                <td><?= e(tarih(gmdate('Y-m-d H:i:s', $y['zaman']), true)) ?></td>
                <td>
                    <div class="eylemler">
                    <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/yedekler/' . $y['ad'] . '/indir')) ?>">İndir</a>
                    <?php if ($y['geri_yuklenebilir']): ?>
                        <a class="btn btn-ghost btn-kucuk" href="<?= e(url('/admin/yedekler/' . $y['ad'] . '/geri-yukle')) ?>">Geri yükle</a>
                    <?php endif; ?>
                    <?php if ($hedefler !== []): ?>
                        <details class="satir-ic">
                            <summary class="btn btn-ghost btn-kucuk">Gönder</summary>
                            <form class="form" method="post" action="<?= e(url('/admin/yedekler/' . $y['ad'] . '/gonder')) ?>">
                                <?= csrf_field() ?>
                                <?php foreach ($hedefler as $h): ?>
                                    <label class="onay"><input type="checkbox" name="hedef[]" value="<?= e($h) ?>"> <?= e($hedefEtiket[$h] ?? $h) ?></label>
                                <?php endforeach; ?>
                                <button class="btn btn-primary btn-kucuk" type="submit">Gönder</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/yedekler/' . $y['ad'] . '/sil')) ?>" data-onay="Bu yedek kalıcı olarak silinsin mi?">
                        <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<section class="kart">
    <div class="kart-baslik"><h2>Başka yerden yedek yükle</h2></div>
    <p class="soluk">Başka bir sunucudan veya bilgisayarınızdan aldığınız <code>.sql</code> / <code>.sql.gz</code> yedeğini yükleyin. Yüklenen dosya önce güvenlik denetiminden geçer (yalnızca bu uygulamanın tabloları ve veri satırları kabul edilir). Sınır: <?= e($bicim((int) $yuklemeLimit)) ?>.</p>
    <form class="form" method="post" action="<?= e(url('/admin/yedekler/yukle')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="alan"><label for="yedek-dosya">Yedek dosyası</label><input type="file" id="yedek-dosya" name="yedek" accept=".sql,.gz" required></div>
        <div class="form-eylem"><button class="btn btn-primary" type="submit">Yükle ve doğrula</button></div>
    </form>
</section>

<section class="kart">
    <div class="kart-baslik"><h2>Güvenli saklama önerisi</h2></div>
    <ul>
        <li>Yedekler parola özetleri ve ziyaretçi mesajlarını içerir; kimseyle paylaşmayın.</li>
        <li><strong>3-2-1:</strong> 3 kopya, 2 farklı ortam, 1 tanesi sunucudan uzakta. Sunucudaki yedek tek başına yeterli değildir.</li>
        <li>Ayda bir yedeği geri yükleme provasıyla (yeni bir kurulumda) sınayın.</li>
    </ul>
</section>
