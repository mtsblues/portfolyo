# Kullanım rehberi

Yönetim paneline `https://siteniz/admin/giris` adresinden girilir. Bu rehber içerik yazmayı, medya kullanmayı ve site ayarlarını anlatır.

- [Panel genel bakış](#panel-genel-bakış)
- [İçerik yazma (Markdown)](#i̇çerik-yazma-markdown)
- [Medya: görsel, galeri, video](#medya-görsel-galeri-video)
- [Medya kütüphanesi](#medya-kütüphanesi)
- [Site ayarları](#site-ayarları)
- [Hesap güvenliği: parola ve iki adımlı doğrulama](#hesap-güvenliği-parola-ve-iki-adımlı-doğrulama)
- [Kullanıcılar ve roller](#kullanıcılar-ve-roller)
- [SEO ve paylaşım](#seo-ve-paylaşım)

## Panel genel bakış

| Bölüm | Ne için |
|---|---|
| **Dashboard** | Sayılar, son makaleler, son mesajlar |
| **Makaleler** | Blog / bilgi kütüphanesi yazıları (taslak → yayında → arşiv) |
| **Projeler** | Portfolyo çalışmaları (görsel, adres, GitHub bağlantısı, durum) |
| **Kategoriler / Etiketler** | Makaleleri düzenleme |
| **Medya** | Yüklenen tüm görsel ve videolar |
| **Mesajlar** | İletişim formundan gelenler |
| **Kullanıcılar, Ayarlar** | (yalnızca yönetici) |

Sağ üstteki **kullanıcı adınıza** tıklayarak **Profil** sayfasına (parola, 2FA) gidebilirsiniz.

## İçerik yazma (Markdown)

Makale ve proje içeriği **Markdown** ile yazılır. Güvenlik için ham HTML çalıştırılmaz; yazdığınız `<script>` gibi şeyler metin olarak görünür.

````markdown
## Başlık (h2)         ### Alt başlık (h3)

**kalın**  *italik*  ~~üstü çizili~~  `satır içi kod`  [bağlantı](https://ornek.com)

- madde            1. sıralı madde
> alıntı

| Sütun A | Sütun B |
|---|---|
| 1 | 2 |

```php
echo "kod bloğu — dil adı renklendirmeyi seçer";
```
````
Kod bloklarına otomatik **sözdizimi renklendirme** ve **kopyala** düğmesi eklenir. Desteklenen diller: php, javascript, typescript, bash/shell, **powershell**, python, sql, json, yaml, html/xml, css/scss, markdown, go, rust, java, csharp, cpp, ruby, perl, swift, kotlin, lua, graphql, makefile, diff, **dockerfile**, **nginx**, **ini** (`.env`/conf için de) — yazmadığınız dil için blok renksiz ama okunaklı görünür.

### Editör araçları

İçerik kutusunun üstündeki çubuk:

| Düğme | İşlev |
|---|---|
| **Dosya yükle** | Görsel/video seçin; yüklenir ve imlecin olduğu yere Markdown kodu eklenir |
| **Kütüphane** | Daha önce yüklenen medyadan seçin |
| **YouTube / Vimeo** | Adres veya kimlik sorar, gömme kodunu ekler |
| **Galeri / Kod bloğu** | Hazır şablon ekler |
| **Önizleme** | Sunucudaki gerçek çeviriciyle sayfanın nasıl görüneceğini gösterir (tekrar basınca düzenlemeye döner) |

## Medya: görsel, galeri, video

Hepsi Markdown içinde yazılır. `/yuklemeler/dosya-adi` yolunu **Medya** sayfasından kopyalayabilirsiniz.

````markdown
![Altyazı buraya](/yuklemeler/0123456789abcdef0123456789abcdef.jpg)
````
Tek başına satırda yazılan görsel, altyazılı bir şekle (figür) dönüşür ve tıklayınca büyür. Alt metin hem altyazı hem erişilebilirlik içindir.
Metnin içinde (aynı satırda) yazılan görsel satır içi kalır.

````markdown
:::galeri
![Birinci](/yuklemeler/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg)
![İkinci](/yuklemeler/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png)
:::
````
Her satır bir görsel; ızgara olarak gösterilir.

````markdown
@[video](/yuklemeler/cccccccccccccccccccccccccccccccc.mp4)
````
Kendi sunucunuzdaki **MP4 / WebM** video; ileri-geri sarma desteklenir.

````markdown
@[youtube](https://youtu.be/dQw4w9WgXcQ)
@[youtube](dQw4w9WgXcQ)
@[vimeo](https://vimeo.com/123456789)
````
YouTube gizlilik dostu `youtube-nocookie.com` ile gömülür. **Yalnızca** YouTube ve Vimeo adresleri kabul edilir (güvenlik ilkesi CSP ile de zorlanır).

**Dikkat:** Güvenlik politikası (CSP) gereği sayfalar yalnızca **kendi sunucunuzdaki** görsel ve videoları yükler. Harici bir adresteki görsel (`![x](https://baska-site.com/a.png)`) tarayıcıda **engellenir**; görseli Medya'ya yükleyin. Harici video gömme yalnızca YouTube ve Vimeo için açıktır.

## Medya kütüphanesi

**Yönetim → Medya**

- **Yükleme:** tek seferde en çok 20 dosya. Görsel: JPEG, PNG, WebP, GIF (en çok 5 MB). Video: MP4, WebM (en çok 50 MB). Sınırlar `.env` ile değişir ([KURULUM.md](KURULUM.md#yükleme-sınırları-görselvideo)).
- **Gizlilik:** JPEG/PNG/WebP'lerdeki konum (GPS), cihaz, tarih ve yorum bilgileri **otomatik silinir**. Fotoğrafın doğru dönmesi için gereken yön bilgisi korunur. (Animasyonlu GIF'ler olduğu gibi saklanır.)
- **Alt metin:** her görsel için kaydedin; kopyalanan kod altyazıyı içerir.
- **Kodu kopyala:** düğme Markdown parçasını panoya alır.
- **Silme:** dosya bir makale/projede ya da ayarda kullanılıyorsa uyarır; "Kullanımdaysa da sil" kutusunu işaretlemeden silinmez.
- Reddedilenler: SVG, HTML, PHP ve içeriği uzantısıyla uyuşmayan her şey. Dosya adları rastgele üretilir, dosyalar web kökü dışındaki `storage/yuklemeler/` içinde durur.

## Site ayarları

**Yönetim → Ayarlar** (yalnızca yönetici). Kod değiştirmeden sitenizi kendinize uyarlayın:

| Grup | Alanlar |
|---|---|
| **Genel** | Site başlığı, açıklama (SEO), anahtar kelimeler, **logo**, **paylaşım görseli (og:image)** |
| **Anasayfa karşılama alanı** | Üst etiket, başlık ("[başlangıç] [vurgulu] [son]"), kısa tanıtım |
| **Hakkımda** | Profil fotoğrafı, Markdown metin. **Metin boşsa sayfa ve menü bağlantısı görünmez.** |
| **Sosyal bağlantılar** | GitHub, LinkedIn, X, YouTube, Instagram (boşlar gösterilmez) |
| **İletişim ve altbilgi** | İletişim e-postası, e-posta bildirimi, saatlik mesaj limiti, altbilgi metni |

Logo ve profil fotoğrafı alanlarına, önce **Medya**'ya yüklediğiniz dosyanın `/yuklemeler/xxxxxxxx.png` yolunu yazın (harici adresler CSP nedeniyle gösterilemediğinden reddedilir). Yalnızca **paylaşım görseli** (og:image) `https://` adresi de olabilir.

## Hesap güvenliği: parola ve iki adımlı doğrulama

**Kullanıcı adınız → Profil**

- **Parola değiştirme:** mevcut parolanız istenir. Kural: en az 12 karakter; büyük harf, küçük harf, rakam, özel karakter.
- **İki adımlı doğrulama (2FA) — şiddetle önerilir:**
  1. *Kurulumu başlat* → ekrandaki **kurulum anahtarını** doğrulayıcı uygulamanıza (Google/Microsoft Authenticator, Authy, Bitwarden, 1Password…) "anahtar girin / zaman tabanlı" olarak ekleyin.
  2. Uygulamadaki 6 haneli kodu girip *Doğrula ve etkinleştir*.
  3. **8 yedek kodu** güvenli bir yere (parola yöneticisi) kaydedin; **yalnızca bir kez** gösterilir. Her kod bir kez kullanılabilir.
  4. Sonraki girişlerde parolanın ardından kod istenir. Aynı kod iki kez kullanılamaz; 5 yanlış denemede 15 dakika kilitlenir.
- **Telefonu ve yedek kodları kaybederseniz** (sunucuya erişiminiz varsa): `php database/install.php --2fa-sifirla=eposta@adresiniz`

## Kullanıcılar ve roller

- **Yönetici (admin):** her şey (kullanıcılar, ayarlar dahil).
- **Editör:** makale, proje, kategori, etiket, medya ve mesajlar; kullanıcı/ayar yönetemez.
- Son aktif yönetici silinemez/devre dışı bırakılamaz; kimse kendi hesabını silemez.

## SEO ve paylaşım

- **Site haritası:** `/sitemap.xml` · **RSS:** `/rss.xml` (son 20 makale, tam metin) · **robots.txt:** otomatik (yönetim paneli taranmaz, sitemap bildirilir).
- Her sayfada `canonical`, Open Graph ve Twitter kartı vardır. Makale/projede **kapak görseli** paylaşım görseli olur; yoksa **Ayarlar → paylaşım görseli** kullanılır.
- Makaleler `BlogPosting` yapılandırılmış verisi (JSON-LD) üretir.
- `APP_URL` canlı adresinizle eşleşmelidir; RSS/og:image adresleri buradan üretilir.
