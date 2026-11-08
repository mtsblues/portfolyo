# Hosting + alan adı + SSL ile yayınlama

Bu rehber, siteyi **XAMPP olmadan**, gerçek bir **hosting**, **alan adı (domain)** ve **SSL sertifikası** ile internette yayınlamanızı anlatır.
Teknik bilginiz az olsa da, adımları sırayla izleyerek yayına alabilirsiniz.

- [Başlamadan önce neye ihtiyacınız var](#başlamadan-önce-neye-ihtiyacınız-var)
- [A) Paylaşımlı hosting (cPanel / Plesk / DirectAdmin)](#a-paylaşımlı-hosting-cpanel--plesk--directadmin) — en yaygın yol
- [B) Kendi sunucunuz (VPS)](#b-kendi-sunucunuz-vps)
- [C) Cloudflare kullanıyorsanız](#c-cloudflare-kullanıyorsanız)
- [Alan adı ve DNS](#alan-adı-ve-dns)
- [Güncelleme](#güncelleme)
- [Yayından sonra kontrol listesi](#yayından-sonra-kontrol-listesi)
- [Sorun giderme](#sorun-giderme)

## Başlamadan önce neye ihtiyacınız var

| Gereken | Notlar |
|---|---|
| **Alan adı** | `ornek.com` gibi. Hosting firmasından veya bir alan adı sağlayıcısından alınır. |
| **Hosting** | **PHP 8.2+** ve **MySQL/MariaDB** desteği olan herhangi bir paylaşımlı hosting yeterlidir. |
| **SSL sertifikası** | Neredeyse tüm hostinglerde **ücretsiz** (Let's Encrypt / AutoSSL) verilir. HTTPS **şarttır**: yönetici parolanız şifresiz gitmemeli. |
| **Bu proje** | GitHub'dan **Code → Download ZIP** ile indirin (ya da `git clone`). |

> Hosting seçerken kontrol edin: PHP sürümü seçilebiliyor mu (8.2 veya üzeri), veritabanı oluşturabiliyor musunuz, ücretsiz SSL var mı.
> SSH (komut satırı) erişimi **gerekli değildir**: kurulum tarayıcıdan yapılabilir.

## A) Paylaşımlı hosting (cPanel / Plesk / DirectAdmin)

Aşağıda cPanel menü adları kullanıldı; Plesk ve DirectAdmin'de karşılıkları benzerdir.

### 1. Alan adını hostinge bağlayın
Alan adını hosting firmasından aldıysanız genellikle otomatik bağlıdır. Başka yerden aldıysanız: [Alan adı ve DNS](#alan-adı-ve-dns).

### 2. PHP sürümünü ve eklentileri ayarlayın
cPanel → **MultiPHP Manager** (veya **Select PHP Version**) → alan adınız için **PHP 8.2 / 8.3** seçin.
Eklentilerde şunların işaretli olduğundan emin olun: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` (zorunlu) · `gd`, `exif`, `zip`, `zlib` (önerilen).

### 3. Veritabanı oluşturun
cPanel → **MySQL Databases** (veya *MySQL Veritabanı Sihirbazı*):
1. Yeni bir veritabanı oluşturun (örn. `kullanici_portfolyo`).
2. Yeni bir veritabanı **kullanıcısı** oluşturup **güçlü bir parola** verin.
3. Kullanıcıyı veritabanına **ALL PRIVILEGES** ile ekleyin.
4. Not alın: veritabanı adı, kullanıcı adı, parola (host genellikle `localhost`).

### 4. Dosyaları yükleyin
İki seçenek var; **1. seçenek daha güvenlidir**.

**Seçenek 1 (önerilen) — Belge kökünü `public/` klasörüne çevirin**
1. File Manager'da ana dizinde (`/home/kullanici/`) `portfolyo` klasörü açın ve ZIP'i oraya yükleyip **Extract** edin (içerik doğrudan `portfolyo/` altında olsun).
2. cPanel → **Domains** (veya *Addon Domains*) → alan adınızın **Document Root** değerini `portfolyo/public` yapın.

**Seçenek 2 — Tüm projeyi `public_html` içine koyun** (belge kökünü değiştiremiyorsanız)
1. ZIP'i `public_html` içine çıkarın (dosyalar doğrudan `public_html/` altında olsun: `app/`, `public/`, `.htaccess`…).
2. Projenin kök `.htaccess` dosyası istekleri otomatik `public/` içine yönlendirir ve `app/`, `config/`, `.env`, `database/`, `bin/`, `storage/` gibi klasörleri engeller.
3. **Mutlaka doğrulayın** (aşağıdaki adresler 403 veya 404 vermeli, içerik göstermemeli):
   `https://ornek.com/.env` · `https://ornek.com/app/Core/Veritabani.php` · `https://ornek.com/config/config.php` · `https://ornek.com/storage/`

> FTP kullanıyorsanız (FileZilla) tüm klasörleri yükleyin, **gizli dosyaları göster** seçeneğini açın (`.htaccess` ve `.env.example` noktayla başlar).

### 5. `.env` dosyasını oluşturun
File Manager'da `config/.env.example` dosyasını **proje köküne** kopyalayıp adını `.env` yapın, sonra düzenleyin:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ornek.com            # kendi alan adınız (https ile)

DB_HOST=localhost
DB_NAME=kullanici_portfolyo          # 3. adımda oluşturduğunuz
DB_KULLANICI=kullanici_portfolyo_kul
DB_SIFRE=veritabani-parolaniz

SESSION_SECURE=true                  # SSL kurulduktan sonra (7. adım)
HSTS=false                           # her şey çalıştıktan sonra true yapın

KURULUM_TOKEN=en-az-16-karakterlik-rastgele-bir-deger   # yalnızca SSH yoksa, kurulum için
```
`KURULUM_TOKEN` için uzun, rastgele bir metin kullanın (örn. bir parola yöneticisinin ürettiği). Kurulum bitince silinecek.

### 6. Kurulumu yapın

**SSH/Terminal varsa:**
```bash
cd ~/portfolyo && php database/install.php
```
Ad, e-posta ve parola sorar.

**SSH yoksa — tarayıcıdan kurulum sihirbazı:**
1. `https://ornek.com/kurulum` adresini açın (SSL henüz yoksa geçici olarak `http://` ile açabilirsiniz, ardından SSL kurup parolanızı değiştirin).
2. Üstteki **ön kontrol** listesinde her şeyin ✓ olduğundan emin olun; ✗ varsa yanındaki ipucuna göre düzeltip sayfayı yenileyin.
3. **Kurulum anahtarı** alanına `.env` içindeki `KURULUM_TOKEN` değerini, sonra yönetici adı/e-postası/parolasını girin.
4. Bitince **`.env` dosyasından `KURULUM_TOKEN` satırını silin**.

Güvenlik: sihirbaz `KURULUM_TOKEN` tanımlı değilse ve veritabanında zaten bir kullanıcı varsa **hiç yokmuş gibi 404 verir**; yanlış anahtar denemeleri sınırlıdır.

### 7. SSL sertifikasını etkinleştirin
cPanel → **SSL/TLS Status** → alan adınızı seçip **Run AutoSSL** (ya da *Let's Encrypt* bölümünden sertifika alın). Birkaç dakika sürer.
Sonra:
1. `.env` içinde `SESSION_SECURE=true` olduğundan emin olun.
2. `public/.htaccess` dosyasını açın ve **"HTTP → HTTPS yönlendirmesi"** bölümündeki satırların başındaki `#` işaretini kaldırın. (Cloudflare kullanıyorsanız önce [C bölümüne](#c-cloudflare-kullanıyorsanız) bakın.)
3. `https://ornek.com` adresini açıp kilit simgesini görün. Her şey sorunsuzsa `.env` içinde `HSTS=true` yapın.

### 8. Klasör izinleri
`storage/` ve içindeki klasörler **yazılabilir** olmalı (genellikle `755`, yazılamıyorsa `775`). `.env` için `600` önerilir.
Panelin **Canlı ortam kontrolü** kartı yazma sorunlarını gösterir.

### 9. E-posta bildirimi (isteğe bağlı)
Hostinginizin e-posta (SMTP) bilgilerini `.env` içine yazın; mesaj geldiğinde size e-posta gelir:
```ini
MAIL_HOST=mail.ornek.com
MAIL_PORT=465
MAIL_SIFRELEME=ssl
MAIL_KULLANICI=bildirim@ornek.com
MAIL_SIFRE=eposta-parolasi
```
Hosting panelinde bu adreste bir e-posta hesabı oluşturmanız gerekir. Deneme: sitenin iletişim formundan bir mesaj gönderin; olmazsa `storage/logs/uygulama.log` dosyasına bakın.

### 10. Yedekleme
- cPanel → **Cron Jobs** → her gün 03:00:
  `0 3 * * * /usr/local/bin/php /home/kullanici/portfolyo/bin/yedekle.php --medya`
  (PHP yolunu hosting belgenizden doğrulayın.) Yedekler `storage/yedekler/` içinde birikir; **düzenli olarak bilgisayarınıza da indirin.**
- Hostingin kendi yedeklerine **ek olarak** bunu kullanın.

### 11. Son kontrol
[Yayından sonra kontrol listesine](#yayından-sonra-kontrol-listesi) bakın.

## B) Kendi sunucunuz (VPS)

Ubuntu/Debian için adımlar [KURULUM.md → Linux — Apache / Nginx](KURULUM.md#linux--apache) bölümünde. Ek olarak:
1. Alan adının **A kaydını** sunucunuzun IP adresine yönlendirin ([DNS](#alan-adı-ve-dns)).
2. Sunucuda güvenlik duvarı: `sudo ufw allow OpenSSH && sudo ufw allow "Nginx Full"` (veya Apache) `&& sudo ufw enable`.
3. SSL: `sudo certbot --apache -d ornek.com -d www.ornek.com` (Nginx: `--nginx`). Sertifika otomatik yenilenir.
4. SSH olduğu için kurulum için `php database/install.php` kullanın; `KURULUM_TOKEN` gerekmez.

## C) Cloudflare kullanıyorsanız

Cloudflare (ücretsiz plan) alan adınızın önüne geçip hız/güvenlik katar. Şunlara dikkat edin:

1. **SSL/TLS → Overview** modunu **Full (strict)** yapın. *Flexible* modunu **kullanmayın**: ziyaretçiyle Cloudflare arası şifreli, Cloudflare ile sunucunuz arası şifresiz olur ve yönlendirme döngüsü yaşarsınız.
   (Sunucunuzda geçerli bir sertifika olmalı: hostingin AutoSSL'i veya Cloudflare *Origin Certificate*.)
2. **`.env` içinde `GUVENILIR_PROXY=true` yapın.** Bunu yapmazsanız sunucu tüm ziyaretçileri Cloudflare'in IP'leri gibi görür: giriş ve iletişim hız sınırı herkesi birlikte kilitler, oturumlar sık sık düşer.
3. **SSL/TLS → Edge Certificates → Always Use HTTPS** seçeneğini açın (HTTP→HTTPS yönlendirmesini Cloudflare yapar; `.htaccess` yönlendirmesini açmanız gerekmez).
4. Panelin **Canlı ortam kontrolü** kartı proxy ayarı eksikse sizi uyarır.

## Alan adı ve DNS

Alan adını hostingten **ayrı** bir yerden aldıysanız, ikisinden birini yapın:

| Yöntem | Ne yapılır |
|---|---|
| **Nameserver (en kolay)** | Alan adı panelinde nameserver'ları hosting firmasının verdiği adreslerle (örn. `ns1.hosting.com`) değiştirin. DNS'i hosting yönetir. |
| **A kaydı** | Alan adı panelinde `@` için **A kaydı** → hostingin IP adresi; `www` için **CNAME** → `ornek.com`. |

DNS değişikliklerinin yayılması dakikalar ile 24 saat arası sürebilir.

**www ve www'suz:** İkisinden yalnızca birini kullanın (arama motorları için önemlidir). `public/.htaccess` içindeki hazır **www → www'suz** yönlendirmesini (alan adınızı yazarak) açabilirsiniz. `.env` içindeki `APP_URL` kullandığınız biçimle aynı olmalıdır.

## Güncelleme

1. **Yedek alın** (`php bin/yedekle.php --medya` ya da cron'un son yedeğini indirin).
2. Yeni sürümün ZIP'ini indirin, içindeki dosyaları sunucudaki proje klasörünün **üzerine yazın**.
   **Dokunmayın / üzerine yazmayın:** `.env` ve `storage/` klasörü (yüklemeleriniz, yedekleriniz).
3. **Yönetim paneline girin.** Veritabanı değişikliği varsa (yeni tablo/sütun) panel ilk girişte bunları **kendiliğinden uygular** — SSH gerekmez. SSH varsa `php database/install.php` de çalıştırabilirsiniz.
4. [CHANGELOG.md](../CHANGELOG.md) içinde o sürümün notlarını okuyun.

## Yayından sonra kontrol listesi

Hepsini tarayıcıdan kontrol edin:

- [ ] `https://ornek.com` açılıyor, adres çubuğunda kilit var, `http://ornek.com` otomatik `https`e yönleniyor
- [ ] `https://ornek.com/.env`, `/app/Core/Veritabani.php`, `/config/config.php`, `/storage/`, `/database/schema.sql` adresleri **içerik göstermiyor** (403/404)
- [ ] `https://ornek.com/kurulum` → **404** (kurulum sihirbazı kapalı), `.env` içinde `KURULUM_TOKEN` yok
- [ ] `https://ornek.com/admin/giris` ile giriş yapabiliyorsunuz; **Dashboard → Canlı ortam kontrolü** kartında kırmızı/turuncu madde **yok**
- [ ] **Profil** sayfasından **iki adımlı doğrulama (2FA)** açıldı, yedek kodlar kaydedildi
- [ ] **Ayarlar** dolduruldu (site adı, e-posta, ana sayfa metni, Hakkımda, sosyal bağlantılar); örnek içerik silindi
- [ ] Bir görsel ve bir video yüklenip bir makalede gösterildi
- [ ] İletişim formu denendi; mesaj panelde görünüyor (SMTP ayarlıysa e-posta geldi)
- [ ] `https://ornek.com/rss.xml`, `/sitemap.xml`, `/robots.txt` açılıyor
- [ ] Yedekleme zamanlandı ve bir yedek dosyası oluştu
- [ ] `database/install.php` ve `database/seed.php` sunucudan silindi
- [ ] (Faydalı) Google Search Console'a siteyi ve `sitemap.xml` adresini ekleyin

## Sorun giderme

| Belirti | Çözüm |
|---|---|
| **500 hatası / beyaz sayfa** | `storage/logs/uygulama.log` ve `storage/logs/php-hata.log` dosyalarına bakın. Genellikle `.env` eksik/yanlış, PHP 8.2'den eski sürüm veya eksik eklenti. Geçici olarak `.env` içinde `APP_DEBUG=true` yapıp hatayı görün, sonra **geri kapatın**. |
| **Alt sayfalar 404** | `mod_rewrite` kapalı ya da `.htaccess` yüklenmemiş (gizli dosya!). Hostinge sorun ya da belge kökünün `public/` olduğunu doğrulayın. |
| **`/kurulum` 404** | Beklenen davranış: `.env` içinde `KURULUM_TOKEN` yok/kısa (16 karakterden az) ya da kurulum daha önce yapılmış. |
| **Kurulum ön kontrolünde ✗ Veritabanı** | `.env` içindeki `DB_HOST` (çoğu hostingte `localhost`), `DB_NAME`, `DB_KULLANICI`, `DB_SIFRE` değerlerini kontrol edin; kullanıcının veritabanına yetkisi olmalı. Hostinglerde ad genellikle `hesapadi_` önekiyle başlar. |
| **Giriş yapınca tekrar giriş ekranı** | HTTP üzerinden `SESSION_SECURE=true` olabilir; HTTPS'e geçin. Cloudflare/proxy arkasındaysanız `GUVENILIR_PROXY=true`. |
| **Tarayıcı "çok fazla yönlendirme" diyor** | Cloudflare *Flexible* mod + `.htaccess` HTTPS yönlendirmesi → *Full (strict)* yapın veya `.htaccess` yönlendirmesini kapatın. |
| **Video/görsel yüklenmiyor** | PHP yükleme sınırları küçük: [KURULUM.md → Yükleme sınırları](KURULUM.md#yükleme-sınırları-görselvideo). Panelin **Canlı ortam kontrolü** kartı bunu da uyarır. |
| **E-posta gelmiyor** | Port/şifreleme eşleşmesi (587→`tls`, 465→`ssl`), gönderen adresinin hostingteki hesaba ait olması; hatalar `storage/logs/uygulama.log` içinde. |
