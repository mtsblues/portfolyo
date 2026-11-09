<?php
/** @var list<array> $oturumlar */
/** @var list<array> $kayitlar */
$ipGoster = static fn(?string $b): string => ($b === null || $b === '' || ip_metin($b) === '') ? '—' : (ip_metin($b) === '0.0.0.0' || ip_metin($b) === '::' ? 'kaydedilmedi' : ip_metin($b));
?>
<section class="kart tablo-kap">
    <div class="kart-baslik kart-baslik-ic">
        <h2>Açık oturumlarınız</h2>
        <?php if (count($oturumlar) > 1): ?>
            <form method="post" action="<?= e(url('/admin/guvenlik/oturumlar/digerleri/kapat')) ?>" data-onay="Bu oturum dışındaki tüm oturumlar kapatılsın mı?">
                <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Diğerlerini kapat</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="soluk kart-not">Tanımadığınız bir cihaz görürseniz oturumu kapatın ve parolanızı değiştirin.</p>
    <table class="tablo">
        <thead><tr><th>Cihaz</th><th>IP</th><th>Açılış</th><th>Son etkinlik</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($oturumlar as $o): $bu = $buOturum !== null && hash_equals((string) $buOturum, (string) $o['anahtar_ozeti']); ?>
            <tr>
                <td title="<?= e((string) $o['tarayici']) ?>"><?= e(\App\Models\OturumKaydi::tarayiciEtiketi((string) $o['tarayici'])) ?> <?php if ($bu): ?><span class="rozet rozet-yayinda">Bu oturum</span><?php endif; ?></td>
                <td><?= e($ipGoster($o['ip_adresi'])) ?></td>
                <td class="zaman"><?= e(tarih((string) $o['olusturulma'], true)) ?></td>
                <td class="zaman"><?= e(tarih((string) $o['son_aktivite'], true)) ?></td>
                <td>
                    <?php if (!$bu): ?>
                        <form method="post" action="<?= e(url('/admin/guvenlik/oturum/' . $o['anahtar_ozeti'] . '/kapat')) ?>">
                            <?= csrf_field() ?><button class="btn btn-ghost btn-kucuk" type="submit">Kapat</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($oturumlar === []): ?><tr><td colspan="5" class="soluk">Kayıt yok.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<section class="kart tablo-kap">
    <div class="kart-baslik kart-baslik-ic">
        <h2><?= $yonetici ? 'Denetim günlüğü' : 'Hesap etkinliğiniz' ?></h2>
    </div>
    <div class="arac-cubugu kart-not">
        <?php foreach ($filtreler as $k => $etiket): ?>
            <a class="btn btn-kucuk <?= $filtre === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/guvenlik') . ($k !== '' ? '?filtre=' . $k : '')) ?>"><?= e($etiket) ?></a>
        <?php endforeach; ?>
    </div>
    <table class="tablo">
        <thead><tr><th>Zaman</th><?php if ($yonetici): ?><th>Kullanıcı</th><?php endif; ?><th>Olay</th><th>Ayrıntı</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($kayitlar as $k): $ay = (string) ($k['ayrinti'] ?? ''); if (str_starts_with($ay, 'ua:')) { $ay = ''; } ?>
            <tr>
                <td class="zaman"><?= e(tarih((string) $k['olusturulma'], true)) ?></td>
                <?php if ($yonetici): ?><td><?= e((string) ($k['ad_soyad'] ?? '—')) ?></td><?php endif; ?>
                <td><?= e(\App\Core\Denetim::etiket((string) $k['eylem'])) ?></td>
                <td class="soluk"><?= e(trim((string) ($k['hedef'] ?? '') . ($ay !== '' ? ' · ' . $ay : ''), ' ·')) ?></td>
                <td><?= e($ipGoster($k['ip_adresi'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($kayitlar === []): ?><tr><td colspan="5" class="soluk">Kayıt yok.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
<?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol, 'sayfalamaEk' => $sayfalamaEk]); ?>
