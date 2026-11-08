"""PHP kurulu olmayan ortamlar için statik tutarlılık denetimi.

Çalıştırma:  python -I tests/statik_kontrol.py
Denetler: parantez/süslü/köşeli denge, strict_types, kapanış etiketi yokluğu, rota -> denetleyici
metodu eşleşmesi, denetleyicilerin kullandığı görünüm dosyaları, partial'lar, `use` ile içe alınan sınıflar.
Bu, `php -l` ve tests/calistir.php yerine GEÇMEZ; yalnızca PHP yokken ilk savunma hattıdır.
"""
import re
import sys
from pathlib import Path

KOK = Path(__file__).resolve().parent.parent
hatalar = []


def hata(m):
    hatalar.append(m)


def temizle(kod):
    """Dizge ve yorumları boşlukla değiştirir (satır sayısı korunur)."""
    cikti = []
    i, n = 0, len(kod)
    heredoc = re.compile(r"<<<[ \t]*(['\"]?)([A-Za-z_][A-Za-z0-9_]*)\1[ \t]*\n")
    while i < n:
        c = kod[i]
        two = kod[i:i + 2]
        m = heredoc.match(kod, i) if c == '<' else None
        if m:
            # heredoc/nowdoc gövdesi: kapanış etiketine kadar tamamı dizge sayılır
            kapanis = re.compile(r'^[ \t]*' + re.escape(m.group(2)) + r'\b', re.M).search(kod, m.end())
            j = kapanis.end() if kapanis else n
            cikti.append('S' + '\n' * kod[i:j].count('\n'))
            i = j
        elif two == '//' or c == '#' and kod[i:i + 2] != '#[':
            j = kod.find('\n', i)
            j = n if j == -1 else j
            i = j
        elif two == '/*':
            j = kod.find('*/', i + 2)
            j = n if j == -1 else j + 2
            cikti.append('\n' * kod[i:j].count('\n'))
            i = j
        elif c in '\'"':
            j = i + 1
            while j < n and kod[j] != c:
                j += 2 if kod[j] == '\\' else 1
            cikti.append('S' + '\n' * kod[i:j + 1].count('\n'))
            i = j + 1
        else:
            cikti.append(c)
            i += 1
    return ''.join(cikti)


def denge(yol, kod):
    yigin = []
    eslesen = {')': '(', '}': '{', ']': '['}
    satir = 1
    for c in kod:
        if c == '\n':
            satir += 1
        elif c in '({[':
            yigin.append((c, satir))
        elif c in ')}]':
            if not yigin or yigin[-1][0] != eslesen[c]:
                hata(f'{yol}:{satir}: dengesiz {c}')
                return
            yigin.pop()
    if yigin:
        hata(f'{yol}:{yigin[-1][1]}: kapanmamış {yigin[-1][0]}')


php_dosyalari = [p for p in KOK.rglob('*.php') if not any(x in p.parts for x in ('.venv', '.git', 'Views'))]
for p in php_dosyalari:
    yol = p.relative_to(KOK).as_posix()
    kod = p.read_text(encoding='utf-8')
    if not kod.startswith('<?php'):
        hata(f'{yol}: <?php ile başlamıyor')
    if not re.search(r'^declare\(strict_types=1\);', kod, re.M):
        hata(f'{yol}: declare(strict_types=1) yok')
    if kod.rstrip().endswith('?>'):
        hata(f'{yol}: kapanış etiketi var')
    if '\r\n' in kod:
        hata(f'{yol}: CRLF satır sonu')
    denge(yol, temizle(kod))
    if re.search(r'\b(TODO|FIXME|XXX)\b', kod):
        hata(f'{yol}: TODO/FIXME bırakılmış')

# --- rotalar -> denetleyici metotları
rota_kod = (KOK / 'config/rotalar.php').read_text(encoding='utf-8')
for sinif, metot in re.findall(r"\[((?:Admin\\)?\w+)::class, '(\w+)'\]", rota_kod):
    yol = KOK / 'app/Controllers' / (sinif.replace('\\', '/') + '.php')
    if not yol.exists():
        hata(f'rota: {sinif} dosyası yok')
        continue
    if not re.search(rf'function\s+{metot}\s*\(', yol.read_text(encoding='utf-8')):
        # AdminTemel soyundan gelen sınıflar kendi metodunu tanımlamalı
        hata(f'rota: {sinif}::{metot} tanımlı değil')

# --- denetleyici -> görünüm dosyaları
gorunumler = set()
for p in (KOK / 'app/Controllers').rglob('*.php'):
    kod = p.read_text(encoding='utf-8')
    gorunumler.update(re.findall(r"gorunum\(\s*'([\w/]+)'", kod))
    gorunumler.update('admin/' + g for g in re.findall(r"admin\(\s*'(\w+)'", kod))
gorunumler.update(['hata/hata', 'layouts/main', 'layouts/admin', 'layouts/bos'])
for g in sorted(g for g in gorunumler if not g.endswith('/')):
    if not (KOK / 'app/Views' / (g + '.php')).exists():
        hata(f'görünüm yok: {g}')

# --- görünümlerde partial() çağrıları ve asset dosyaları
for p in (KOK / 'app/Views').rglob('*.php'):
    kod = p.read_text(encoding='utf-8')
    yol = p.relative_to(KOK).as_posix()
    for ad in re.findall(r"partial\('(\w+)'", kod):
        if not (KOK / 'app/Views/partials' / (ad + '.php')).exists():
            hata(f'{yol}: partial yok: {ad}')
    for a in re.findall(r"asset\('([\w./-]+)'\)", kod):
        if a.startswith('vendor/'):
            continue  # isteğe bağlı, varlığı çalışma anında denetlenir
        if not (KOK / 'public/assets' / a).exists():
            hata(f'{yol}: asset yok: {a}')
    # Çıktıların kaçışlanması: <?= ... ?> içinde e()/csrf_field()/partial vb. olmayan ifadeler
    for m in re.finditer(r'<\?=\s*(.+?)\s*\?>', kod, re.S):
        ifade = m.group(1)
        guvenli = ('e(', 'csrf_field()', '$icerik', '$icerikHtml', 'menu_aktif(', '(int)', 'nl2br(e(', 'count(', 'jsonld_json(', '$alanHtml(')
        if not ifade.lstrip('(').startswith(guvenli) and not re.match(r"^(\$\w+\s*===|\(\$|\(int\)|\(string\))", ifade):
            satir = kod[:m.start()].count('\n') + 1
            if not re.search(r"^\(?\s*\(int\)|^\$[a-z]+ === |\? ' |\? '|' : ''", ifade):
                hata(f'{yol}:{satir}: kaçışsız olabilecek çıktı: <?= {ifade[:70]} ?>')

# --- use ile içe alınan App\ sınıfları gerçekten var mı
for p in php_dosyalari:
    for imp in re.findall(r'^use (App\\[\w\\]+);', p.read_text(encoding='utf-8'), re.M):
        hedef = KOK / 'app' / (imp[4:].replace('\\', '/') + '.php')
        if not hedef.exists() and not hedef.with_suffix('').is_dir():
            hata(f'{p.relative_to(KOK).as_posix()}: use hedefi yok: {imp}')

if hatalar:
    print(f'{len(hatalar)} SORUN:')
    for h in hatalar:
        print(' -', h)
    sys.exit(1)
print(f'Statik kontrol temiz: {len(php_dosyalari)} PHP dosyası, {len(gorunumler)} görünüm.')
