<?php if ($mesajlar === []): ?>
    <div class="kart"><p class="bos">Henüz mesaj yok.</p></div>
<?php else: ?>
    <div class="mesaj-listesi">
        <?php foreach ($mesajlar as $m): ?>
            <article class="kart mesaj<?= (int) $m['okundu'] === 0 ? ' mesaj-yeni' : '' ?>">
                <header>
                    <div>
                        <strong><?= e($m['ad_soyad']) ?></strong>
                        <a href="mailto:<?= e($m['eposta']) ?>"><?= e($m['eposta']) ?></a>
                    </div>
                    <span class="soluk"><?= e(tarih($m['olusturulma'], true)) ?> · <?= e(ip_metin((string) $m['ip_adresi'])) ?></span>
                </header>
                <h3><?= e($m['konu']) ?></h3>
                <p class="mesaj-govde"><?= nl2br(e($m['mesaj'])) ?></p>
                <footer class="eylemler">
                    <?php if ((int) $m['okundu'] === 0): ?>
                        <form method="post" action="<?= e(url('/admin/mesaj/' . $m['id'] . '/okundu')) ?>">
                            <?= csrf_field() ?><button class="btn btn-ghost btn-kucuk" type="submit">Okundu işaretle</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('/admin/mesaj/' . $m['id'] . '/sil')) ?>" data-onay="Bu mesaj silinsin mi?">
                        <?= csrf_field() ?><button class="btn btn-tehlike btn-kucuk" type="submit">Sil</button>
                    </form>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php partial('pagination', ['sayfalama' => $sayfalama, 'sayfalamaYol' => $sayfalamaYol]); ?>
