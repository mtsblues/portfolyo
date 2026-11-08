/* Yönetim paneli etkileşimleri: silme onayı, mobil yan menü, kopyalama ve Markdown editör araçları. */
(function () {
    'use strict';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    document.querySelectorAll('form[data-onay]').forEach(function (form) {
        form.addEventListener('submit', function (olay) {
            if (!window.confirm(form.getAttribute('data-onay'))) {
                olay.preventDefault();
            }
        });
    });

    var dugme = document.getElementById('yan-menu-dugme');
    var menu = document.getElementById('yan-menu');
    if (dugme && menu) {
        dugme.addEventListener('click', function () {
            var acik = menu.getAttribute('data-acik') !== 'true';
            menu.setAttribute('data-acik', acik ? 'true' : 'false');
            dugme.setAttribute('aria-expanded', acik ? 'true' : 'false');
        });
    }

    function panoyaKopyala(metin, bitti) {
        var tamam = function () { if (bitti) { bitti(true); } };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(metin).then(tamam, function () { yedekKopya(metin, bitti); });
        } else {
            yedekKopya(metin, bitti);
        }
    }
    function yedekKopya(metin, bitti) {
        var t = document.createElement('textarea');
        t.value = metin;
        t.className = 'gorunmez';
        document.body.appendChild(t);
        t.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(t);
        if (bitti) { bitti(ok); }
    }

    document.querySelectorAll('[data-kopyala-deger]').forEach(function (b) {
        b.addEventListener('click', function () {
            var eski = b.textContent;
            panoyaKopyala(b.getAttribute('data-kopyala-deger') || '', function (ok) {
                b.textContent = ok ? 'Kopyalandı ✓' : 'Kopyalanamadı';
                window.setTimeout(function () { b.textContent = eski; }, 1500);
            });
        });
    });

    // ---- Markdown editör araçları ----
    document.querySelectorAll('.arac-cubugu[data-editor]').forEach(function (cubuk) {
        var hedef = document.getElementById(cubuk.getAttribute('data-editor'));
        var onizleme = document.querySelector('[data-onizleme="' + cubuk.getAttribute('data-editor') + '"]');
        var dialog = document.querySelector('dialog[data-dialog="' + cubuk.getAttribute('data-editor') + '"]');
        var durum = cubuk.querySelector('.editor-durum');
        if (!hedef) { return; }

        function bildir(m) { if (durum) { durum.textContent = m; } }

        function ekle(metin) {
            var bas = hedef.selectionStart || 0;
            var son = hedef.selectionEnd || 0;
            var onceki = hedef.value.slice(0, bas);
            var sonraki = hedef.value.slice(son);
            var oncesi = onceki === '' || /\n\n$/.test(onceki) ? '' : (/\n$/.test(onceki) ? '\n' : '\n\n');
            var sonrasi = sonraki === '' || /^\n\n/.test(sonraki) ? '' : (/^\n/.test(sonraki) ? '\n' : '\n\n');
            var ekle = oncesi + metin + sonrasi;
            hedef.value = onceki + ekle + sonraki;
            var imlec = (onceki + ekle).length;
            hedef.focus();
            hedef.setSelectionRange(imlec, imlec);
        }

        function istek(url, govde, json) {
            var basliklar = { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
            return fetch(url, { method: govde ? 'POST' : 'GET', body: govde || undefined, credentials: 'same-origin', headers: basliklar })
                .then(function (y) { return y.json().catch(function () { return {}; }).then(function (v) { v._durum = y.status; return v; }); });
        }

        // Dosya yükleme
        var dosyaGirdi = cubuk.querySelector('[data-yukle]');
        if (dosyaGirdi) {
            dosyaGirdi.addEventListener('change', function () {
                if (!dosyaGirdi.files || dosyaGirdi.files.length === 0) { return; }
                var f = new FormData();
                Array.prototype.forEach.call(dosyaGirdi.files, function (d) { f.append('dosyalar[]', d); });
                bildir('Yükleniyor…');
                istek(cubuk.getAttribute('data-yukle-url') || (document.body.getAttribute('data-taban') || '') + '/admin/medya/yukle', f)
                    .then(function (v) {
                        (v.yuklenen || []).forEach(function (o) { ekle(o.parca); });
                        var hata = (v.hatalar || []).join(' | ');
                        bildir(hata !== '' ? hata : ((v.yuklenen || []).length + ' dosya eklendi.'));
                    })
                    .catch(function () { bildir('Yükleme başarısız.'); })
                    .then(function () { dosyaGirdi.value = ''; });
            });
        }

        // Kütüphane
        var kutuphane = cubuk.querySelector('[data-kutuphane]');
        if (kutuphane && dialog) {
            var liste = dialog.querySelector('[data-liste]');
            kutuphane.addEventListener('click', function () {
                liste.textContent = 'Yükleniyor…';
                dialog.showModal();
                istek((document.body.getAttribute('data-taban') || '') + '/admin/medya/json')
                    .then(function (v) {
                        liste.textContent = '';
                        if (!v.ogeler || v.ogeler.length === 0) { liste.textContent = 'Kütüphane boş.'; return; }
                        v.ogeler.forEach(function (o) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'medya-sec';
                            var el;
                            if (o.tur === 'video') {
                                el = document.createElement('video');
                                el.src = o.url; el.muted = true; el.preload = 'metadata';
                            } else {
                                el = document.createElement('img');
                                el.src = o.url; el.alt = o.alt || ''; el.loading = 'lazy';
                            }
                            b.appendChild(el);
                            b.addEventListener('click', function () { ekle(o.parca); dialog.close(); });
                            liste.appendChild(b);
                        });
                    })
                    .catch(function () { liste.textContent = 'Kütüphane yüklenemedi.'; });
            });
            dialog.querySelector('[data-kapat]').addEventListener('click', function () { dialog.close(); });
            dialog.addEventListener('click', function (o) { if (o.target === dialog) { dialog.close(); } });
        }

        // Hazır şablonlar
        var sablonlar = {
            youtube: function () {
                var u = window.prompt('YouTube adresi veya video kimliği:');
                return u ? '@[youtube](' + u.trim() + ')' : null;
            },
            vimeo: function () {
                var u = window.prompt('Vimeo adresi veya video numarası:');
                return u ? '@[vimeo](' + u.trim() + ')' : null;
            },
            galeri: function () { return ':::galeri\n![Altyazı 1](/yuklemeler/dosya1.jpg)\n![Altyazı 2](/yuklemeler/dosya2.jpg)\n:::'; },
            kod: function () { return '```php\n// kodunuz\n```'; }
        };
        cubuk.querySelectorAll('[data-sablon]').forEach(function (b) {
            b.addEventListener('click', function () {
                var m = sablonlar[b.getAttribute('data-sablon')]();
                if (m) { ekle(m); }
            });
        });

        // Önizleme (sunucudaki güvenli Markdown çeviricisi)
        var onizleDugme = cubuk.querySelector('[data-onizle]');
        if (onizleDugme && onizleme) {
            onizleDugme.addEventListener('click', function () {
                var acik = !onizleme.hidden;
                if (acik) {
                    onizleme.hidden = true;
                    hedef.hidden = false;
                    onizleDugme.setAttribute('aria-pressed', 'false');
                    onizleDugme.textContent = 'Önizleme';
                    return;
                }
                var f = new URLSearchParams();
                f.set('metin', hedef.value);
                bildir('Hazırlanıyor…');
                istek((document.body.getAttribute('data-taban') || '') + '/admin/onizle', f)
                    .then(function (v) {
                        // Çıktı sunucuda kaçışlanmış/beyaz listeli Markdown'dan üretilir.
                        onizleme.innerHTML = v.html || '';
                        onizleme.hidden = false;
                        hedef.hidden = true;
                        onizleDugme.setAttribute('aria-pressed', 'true');
                        onizleDugme.textContent = 'Düzenle';
                        bildir('');
                    })
                    .catch(function () { bildir('Önizleme alınamadı.'); });
            });
        }
    });
})();
