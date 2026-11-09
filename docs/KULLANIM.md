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
| **Özgeçmiş** | Deneyim, eğitim, yetenek, sertifika, hizmet, referans (yalnızca yönetici) |
| **Kullanıcılar, Ayarlar** | (yalnızca yönetici) |

**Dashboard → Canlı ortam kontrolü** (yalnızca yönetici): sitenizin canlıda güvenli ve doğru yapılandırıldığını denetler. Kırmızı (kritik) ve turuncu (uyarı) maddeleri düzeltin; mavi maddeler bilgidir. Örnekler: hata ayıklama modu açık, HTTPS yok, `SESSION_SECURE` kapalı, Cloudflare/proxy ayarı eksik, `install.php` sunucuda duruyor, `storage` yazılamıyor, PHP yükleme sınırı video sınırından küçük, yedek alınmamış.

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

## Güvenlik merkezi

**Yönetim → Güvenlik**
- **Açık oturumlar:** hangi cihaz/tarayıcı ve IP'den, ne zaman girildi. Tanımadığınız oturumu **Kapat**; hepsini birden **Diğerlerini kapat**. Kapatılan oturum bir sonraki istekte düşer. Parola değişince diğer oturumlar kendiliğinden kapanır.
- **Denetim günlüğü:** girişler (başarılı/başarısız), parola ve 2FA değişiklikleri, yedek alma/indirme/geri yükleme, yönetici işlemleri. Yönetici herkesinkini, editör yalnızca kendi kayıtlarını görür. 365 günden eskileri bakım görevinde silinir.
- **Yeni cihaz bildirimi:** daha önce kullanılmayan bir tarayıcıdan girişte e-posta gelir (SMTP gerekir). Ayarlar → Güvenlik.
- **2FA zorunluluğu** (Ayarlar → Güvenlik): açıkken 2FA'sı olmayan kullanıcı, kurulum yapana kadar yalnızca Profil sayfasını görür. Kilitlenmemek için önce kendi 2FA'nızı açın (açmadan kaydedilmez).
- **Parola sıfırlama:** giriş sayfasında “Parolamı unuttum” (SMTP + `APP_URL` gerekir). Bağlantı tek kullanımlıktır, 60 dakika geçerlidir, yalnızca özeti saklanır; kullanıcının var olup olmadığı yanıttan anlaşılmaz. Sıfırlayınca tüm oturumlar kapanır.

## Yedekleme ve geri yükleme

**Yönetim → Yedekleme** (yalnızca yönetici). Ayrıntılar ve komut satırı: [KURULUM.md](KURULUM.md#yedekleme-ve-geri-yükleme).
1. **Şimdi yedek al** (isteğe bağlı: medya dahil). Liste: indir, geri yükle, dışarı gönder, sil.
2. **Geri yükleme sihirbazı:** yedeği seçin → *kuru çalıştırma özeti* (tarih, şema, tablo/satır) → parolanız (+2FA) ve `GERI YUKLE` yazarak onay → mevcut durum otomatik yedeklenir → uygulanır → oturumunuz kapanır, yeniden girersiniz. Hata olursa önceki durum kendiliğinden döner.
3. **Başka yerden yedek:** `.sql` / `.sql.gz` yükleyin; güvenlik denetiminden geçmezse reddedilir.
4. **Otomatik yedek:** Ayarlar → Otomatik yedekleme (günlük/haftalık) + dış hedef (e-posta, FTP/FTPS, WebDAV).
5. Sunucu taşıma: eski sitede yedek alın → yeni sitede kurulumu yapın → Yedekleme'den yedeği yükleyip geri yükleyin → medya arşivini de aynı şekilde.

## Sayfalar ve KVKK

**Yönetim → Sayfalar** (yalnızca yönetici): serbest Markdown sayfalar (`/sayfa/adres`) — gizlilik, aydınlatma metni, hizmet şartları, “Bu site hakkında”…; menüde ve/veya altbilgide gösterilir, taslak/yayında durumu vardır.
- **Hazır şablonlar:** KVKK Aydınlatma Metni, Gizlilik ve Çerez Bildirimi, Bu Site Hakkında. Site adı, e-posta, mesaj saklama süresi ve IP politikasıyla doldurulup **taslak** oluşturulur. **Bunlar genel başlangıç metnidir, hukuki danışmanlık değildir**; yayımlamadan önce kendi durumunuza göre gözden geçirin.
- **Ayarlar → Gizlilik ve KVKK:** yayındaki aydınlatma sayfasının adresini yazarsanız iletişim formunda **zorunlu onay kutusu** çıkar ve onay mesajla birlikte kaydedilir. *Mesaj saklama süresi* (gün) dolunca mesajlar bakım görevinde silinir. *IP saklama*: tam / kısaltılmış (önerilen) / hiç.
- Site yalnızca oturum için gerekli çerez (yönetici girişi) ve tema tercihi için tarayıcı depolaması kullanır; üçüncü taraf izleyici yoktur.

## Özgeçmiş, hizmetler ve müsaitlik

**Yönetim → Özgeçmiş** (yalnızca yönetici) altı sekmeden oluşur: **Deneyim**, **Eğitim**, **Yetenekler**, **Sertifikalar**, **Hizmetler**, **Referanslar**.
- *Deneyim / Eğitim:* başlangıç ve bitiş ayı seçin; devam ediyorsa “Devam ediyor”u işaretleyin. En yeni kayıt en üstte görünür. Açıklamada Markdown madde işaretleri kullanabilirsiniz.
- *Yetenekler:* “Grup” alanına aynı değeri yazdıklarınız (örn. Backend) birlikte listelenir; seviye 0–100 girilir ve 1–5 nokta olarak gösterilir.
- *Sertifikalar:* alınış tarihi, (varsa) geçerlilik sonu ve doğrulama bağlantısı.
- *Hizmetler ve Referanslar* **anasayfada** “Neler yapıyorum” ve “Referanslar” bölümlerinde görünür. Referansı yalnızca kişinin izniyle ekleyin.
- Her satırda **Gizle/Yayınla** ile kaydı silmeden sitede göstermeyi kapatabilirsiniz.

**Ayarlar → Özgeçmiş ve müsaitlik:** özgeçmiş sayfasını (`/cv`) yayınlamak için *Özgeçmiş sayfasını yayınla*yı işaretleyin; ad, unvan, kısa özet, konum ve iletişim bilgilerini girin.
> **Gizlilik:** özgeçmiş sayfası herkese açıktır. *Herkese açık e-posta* ve *Telefon* alanlarını yalnızca paylaşmak istiyorsanız doldurun; boş bıraktığınız bilgi sayfada ve kartvizitte hiç görünmez. İletişim formunun e-postası ayrıdır ve ifşa edilmez.

- **Yazdır / PDF:** `/cv` sayfasındaki “Yazdır / PDF olarak kaydet” düğmesi tarayıcının yazdırma penceresini açar; hedef olarak “PDF olarak kaydet”i seçin. Menü ve düğmeler çıktıda görünmez.
- **Kartvizit (.vcf):** ziyaretçiler tek tıkla rehberlerine ekleyebilir (Ayarlar'dan kapatılabilir).
- **“Yeni işlere açığım” rozeti:** açıp metnini değiştirin (örn. “Ağustos’tan itibaren müsaitim”); anasayfada ve özgeçmişte görünür. Müsaitlik değişince kapatmayı unutmayın.

**Projelerde:** *Teknolojiler* alanına virgülle ayırarak yazın (PHP, MySQL, Docker); ziyaretçiler proje listesinde bu etiketlere tıklayarak filtreleyebilir. *Vaka çalışması* (Problem / Çözüm / Sonuç) alanları doluysa proje sayfasında üç kutu olarak görünür — mümkünse sonucu ölçülebilir yazın.

**Makalelerde:** üç ya da daha fazla başlık (`#`, `##`) varsa otomatik **içindekiler** çıkar; sayfa sonunda önceki/sonraki makale ve paylaşım bağlantıları görünür (paylaşım için üçüncü taraf betik yüklenmez).

## Kullanıcılar ve roller

- **Yönetici (admin):** her şey (kullanıcılar, ayarlar dahil).
- **Editör:** makale, proje, kategori, etiket, medya ve mesajlar; kullanıcı/ayar yönetemez.
- Son aktif yönetici silinemez/devre dışı bırakılamaz; kimse kendi hesabını silemez.

## SEO ve paylaşım

- **Site haritası:** `/sitemap.xml` · **RSS:** `/rss.xml` (son 20 makale, tam metin) · **robots.txt:** otomatik (yönetim paneli taranmaz, sitemap bildirilir).
- Her sayfada `canonical`, Open Graph ve Twitter kartı vardır. Makale/projede **kapak görseli** paylaşım görseli olur; yoksa **Ayarlar → paylaşım görseli** kullanılır.
- Makaleler `BlogPosting` yapılandırılmış verisi (JSON-LD) üretir.
- `APP_URL` canlı adresinizle eşleşmelidir; RSS/og:image adresleri buradan üretilir.
