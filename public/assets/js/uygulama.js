/* Genel site betiği: tema, mobil menü, kod blokları (kopyala + vurgulama), AJAX iletişim formu. */
(function () {
    'use strict';

    // ---- Tema değiştirici ----
    var kok = document.documentElement;
    var temaDugme = document.getElementById('tema-dugme');
    if (temaDugme) {
        temaDugme.addEventListener('click', function () {
            var yeni = kok.getAttribute('data-tema') === 'koyu' ? 'acik' : 'koyu';
            kok.setAttribute('data-tema', yeni);
            try { localStorage.setItem('tema', yeni); } catch (e) { /* özel mod */ }
        });
    }

    // ---- Mobil menü ----
    var menuDugme = document.getElementById('menu-dugme');
    var menu = document.getElementById('ana-menu');
    if (menuDugme && menu) {
        menuDugme.addEventListener('click', function () {
            var acik = menu.getAttribute('data-acik') !== 'true';
            menu.setAttribute('data-acik', acik ? 'true' : 'false');
            menuDugme.setAttribute('aria-expanded', acik ? 'true' : 'false');
        });
    }

    // ---- Basit, bağımlılıksız sözdizimi vurgulama (highlight.js yüklü değilse) ----
    var ANAHTAR = ('abstract as async await break case catch class const continue default do echo else elseif ' +
        'enum export extends false final finally fn for foreach from function global if implements import in ' +
        'include instanceof interface let match namespace new null private protected public readonly require return ' +
        'self static switch this throw trait true try type use var while yield def elif lambda pass print with ' +
        'select insert update delete where join group order by limit create table param begin end').split(' ');
    var anahtarKumesi = {};
    ANAHTAR.forEach(function (k) { anahtarKumesi[k] = true; });

    var TOKEN = /(\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*|--[^\n]*)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`(?:\\.|[^`\\])*`)|(\b\d+(?:\.\d+)?\b)|([A-Za-z_$][\w$-]*)(?=\s*\()|([A-Za-z_$][\w$]*)/g;

    function vurgula(kod) {
        var metin = kod.textContent;
        var parca = document.createDocumentFragment();
        var son = 0;
        var m;
        TOKEN.lastIndex = 0;
        while ((m = TOKEN.exec(metin)) !== null) {
            if (m.index > son) { parca.appendChild(document.createTextNode(metin.slice(son, m.index))); }
            var sinif = null;
            if (m[1]) { sinif = 'tok-c'; }
            else if (m[2]) { sinif = 'tok-s'; }
            else if (m[3]) { sinif = 'tok-n'; }
            else if (m[4]) { sinif = anahtarKumesi[m[4].toLowerCase()] ? 'tok-k' : 'tok-f'; }
            else if (m[5] && anahtarKumesi[m[5].toLowerCase()]) { sinif = 'tok-k'; }
            if (sinif) {
                var span = document.createElement('span');
                span.className = sinif;
                span.textContent = m[0];
                parca.appendChild(span);
            } else {
                parca.appendChild(document.createTextNode(m[0]));
            }
            son = m.index + m[0].length;
            if (m[0].length === 0) { TOKEN.lastIndex++; }
        }
        if (son < metin.length) { parca.appendChild(document.createTextNode(metin.slice(son))); }
        kod.textContent = '';
        kod.appendChild(parca);
    }

    // ---- Kod blokları: sarmalayıcı + kopyala butonu ----
    function kopyalaMetin(metin) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(metin);
        }
        return new Promise(function (coz, reddet) {
            var ta = document.createElement('textarea');
            ta.value = metin;
            ta.setAttribute('readonly', '');
            ta.className = 'gorunmez';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            ok ? coz() : reddet(new Error('kopyalanamadı'));
        });
    }

    document.querySelectorAll('.icerik pre').forEach(function (pre) {
        var kod = pre.querySelector('code');
        if (!kod) { return; }
        var dil = (/language-([\w+#.-]+)/.exec(kod.className) || [])[1] || 'kod';

        if (window.hljs && typeof window.hljs.highlightElement === 'function') {
            window.hljs.highlightElement(kod);
        } else {
            vurgula(kod);
        }

        var kap = document.createElement('div');
        kap.className = 'kod-blok';
        var ust = document.createElement('div');
        ust.className = 'kod-ust';
        var etiket = document.createElement('span');
        etiket.textContent = dil;
        var dugme = document.createElement('button');
        dugme.type = 'button';
        dugme.className = 'kopyala';
        dugme.textContent = 'Kopyala';
        dugme.setAttribute('aria-label', 'Kodu panoya kopyala');
        dugme.addEventListener('click', function () {
            kopyalaMetin(kod.textContent || '').then(function () {
                dugme.textContent = 'Kopyalandı ✓';
                dugme.setAttribute('data-durum', 'tamam');
            }).catch(function () {
                dugme.textContent = 'Kopyalanamadı';
            }).then(function () {
                setTimeout(function () {
                    dugme.textContent = 'Kopyala';
                    dugme.removeAttribute('data-durum');
                }, 1800);
            });
        });
        ust.appendChild(etiket);
        ust.appendChild(dugme);
        pre.parentNode.insertBefore(kap, pre);
        kap.appendChild(ust);
        kap.appendChild(pre);
    });

    // ---- AJAX iletişim formu ----
    var form = document.getElementById('iletisim-form');
    if (form) {
        var sonuc = document.getElementById('form-sonuc');
        var gonder = document.getElementById('gonder-dugme');
        var csrf = document.querySelector('meta[name="csrf-token"]');

        var temizle = function () {
            form.querySelectorAll('[data-hata-icin]').forEach(function (p) { p.textContent = ''; });
            sonuc.className = 'uyari gizli';
            sonuc.textContent = '';
        };
        var goster = function (tur, mesaj) {
            sonuc.className = 'uyari uyari-' + tur;
            sonuc.textContent = mesaj;
        };

        form.addEventListener('submit', function (olay) {
            olay.preventDefault();
            temizle();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            gonder.disabled = true;
            var eskiMetin = gonder.textContent;
            gonder.textContent = 'Gönderiliyor…';

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf ? csrf.getAttribute('content') : ''
                }
            }).then(function (yanit) {
                return yanit.json().catch(function () { return {}; }).then(function (veri) {
                    return { durum: yanit.status, veri: veri };
                });
            }).then(function (r) {
                if (r.veri && r.veri.basarili) {
                    goster('basari', r.veri.mesaj || 'Mesajınız iletildi.');
                    form.reset();
                    return;
                }
                if (r.veri && r.veri.alanlar) {
                    Object.keys(r.veri.alanlar).forEach(function (alan) {
                        var p = form.querySelector('[data-hata-icin="' + alan + '"]');
                        if (p) { p.textContent = r.veri.alanlar[alan]; }
                    });
                }
                goster('hata', (r.veri && r.veri.hata) || 'Mesaj gönderilemedi. Sayfayı yenileyip tekrar deneyin.');
            }).catch(function () {
                goster('hata', 'Bağlantı hatası. Lütfen tekrar deneyin.');
            }).then(function () {
                gonder.disabled = false;
                gonder.textContent = eskiMetin;
            });
        });
    }

    // ---- İçerikteki görselleri büyütme (lightbox) ----
    var kutu = null;
    document.addEventListener('click', function (olay) {
        var hedef = olay.target;
        var img = hedef && hedef.closest ? hedef.closest('.icerik img') : null;
        if (!img || !img.src || img.closest('a') || typeof HTMLDialogElement === 'undefined') { return; }
        if (!kutu) {
            kutu = document.createElement('dialog');
            kutu.className = 'medya-kutu';
            var ic = document.createElement('div');
            ic.className = 'medya-kutu-ic';
            ic.appendChild(document.createElement('img'));
            kutu.appendChild(ic);
            kutu.addEventListener('click', function () { kutu.close(); });
            document.body.appendChild(kutu);
        }
        var buyuk = kutu.querySelector('img');
        buyuk.src = img.currentSrc || img.src;
        buyuk.alt = img.alt || '';
        kutu.showModal();
    });
})();
