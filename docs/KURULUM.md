# Kurulum ve yayına alma rehberi

Bu rehber Portfolyo'nun **XAMPP (Windows)**, Linux (Apache / Nginx), macOS, **Docker** ve **paylaşımlı hosting** üzerinde nasıl kurulacağını,
yapılandırılacağını, yedekleneceğini ve güncelleneceğini anlatır.

- [Gereksinimler](#gereksinimler)
- [Hızlı deneme (php -S)](#hızlı-deneme-php--s)
- [Windows — XAMPP](#windows--xampp)
- [Linux — Apache](#linux--apache) · [Linux — Nginx](#linux--nginx) · [macOS](#macos)
- [Docker](#docker)
- [Paylaşımlı hosting / alan adı + SSL](#paylaşımlı-hosting) → ayrıntı: [YAYINLAMA.md](YAYINLAMA.md)
- [Yapılandırma (.env)](#yapılandırma-env)
- [Yükleme sınırları (görsel/video)](#yükleme-sınırları-görselvideo)
- [E-posta bildirimi (SMTP)](#e-posta-bildirimi-smtp)
- [Yedekleme ve geri yükleme](#yedekleme-ve-geri-yükleme)
- [Güncelleme](#güncelleme)
- [Parola / 2FA sıfırlama](#parola--2fa-sıfırlama)
- [Canlıya çıkış kontrol listesi](#canlıya-çıkış-kontrol-listesi)
- [Sorun giderme](#sorun-giderme)

## Gereksinimler

| | Gerekli | Önerilen |
|---|---|---|
| PHP | **8.2+** | 8.3 / 8.4 |
| PHP eklentileri | `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` | `gd` (büyük görselleri küçültür), `exif`, `zip` (yedekte medya arşivi), `zlib` |
| Veritabanı | **MySQL 8** veya **MariaDB 10.4+** (`utf8mb4`) | |
| Web sunucusu | Apache (`mod_rewrite`, `AllowOverride All`) **veya** Nginx + PHP-FPM | HTTPS (Let's Encrypt) |

Eklentileri görmek için: `php -m`. `gd` olmadan da her şey çalışır; yalnızca 2560 px'den büyük görseller küçültülmez
(**EXIF/GPS bilgisi GD olmadan da silinir**).

## Hızlı deneme (php -S)

PHP'nin yerleşik sunucusuyla, Apache/Nginx kurmadan:

```bash
cp config/.env.example .env             # Windows: copy config\.env.example .env
# .env içinde DB_KULLANICI / DB_SIFRE değerlerini düzenleyin, APP_URL=http://localhost:8000
php database/install.php                # veritabanı + tablolar + ilk yönetici
php database/seed.php                   # (isteğe bağlı) örnek içerik
php -S localhost:8000 -t public public/index.php
```

> `php -S` yalnızca geliştirme içindir; `.htaccess` kuralları çalışmaz (yönlendirmeyi `public/index.php` yapar).

## Windows — XAMPP

1. **XAMPP'ı kurun** (PHP 8.2+ sürümlü paket) ve **Apache** ile **MySQL**'i XAMPP Control Panel'den başlatın.
2. **Projeyi kopyalayın:** `C:\xampp\htdocs\portfolyo` (`.git`, `.venv` gibi gereksiz klasörleri kopyalamanız gerekmez).
3. **Kendi yöntemlerinizden birini seçin:**

   **A) Alt klasör (en kolay):** adres `http://localhost/portfolyo`. Kök `.htaccess` istekleri otomatik olarak `public/` içine yönlendirir
   ve `app/`, `config/`, `.env` gibi dizinlere erişimi engeller.

   **B) Sanal host (önerilen; canlıya benzer):** `C:\xampp\apache\conf\extra\httpd-vhosts.conf` dosyasına ekleyin:
   ```apache
   <VirtualHost *:80>
       ServerName portfolyo.local
       DocumentRoot "C:/xampp/htdocs/portfolyo/public"
       <Directory "C:/xampp/htdocs/portfolyo/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
   `C:\Windows\System32\drivers\etc\hosts` dosyasına (yönetici olarak) `127.0.0.1 portfolyo.local` satırını ekleyip Apache'yi yeniden başlatın.
4. **`.env` dosyasını oluşturun** (Komut İstemi):
   ```bat
   cd C:\xampp\htdocs\portfolyo
   copy config\.env.example .env
   ```
   `.env` içinde şunları ayarlayın:
   ```ini
   APP_URL=http://localhost/portfolyo        # (B yönteminde: http://portfolyo.local)
   DB_KULLANICI=root
   DB_SIFRE=                                  # XAMPP varsayılanı: boş
   SESSION_SECURE=false                       # yalnızca yerel HTTP için
   ```
5. **Veritabanı ve yönetici:**
   ```bat
   C:\xampp\php\php.exe database\install.php
   ```
   Betik veritabanını ve tabloları oluşturur, sonra **ad, e-posta ve parola** (en az 12 karakter; büyük/küçük harf, rakam, özel karakter) sorar.
   Parola yazılırken ekranda görünmez; bu normaldir.
6. **Aç:** `http://localhost/portfolyo` · Yönetim: `http://localhost/portfolyo/admin/giris`
7. (İsteğe bağlı) Örnek içerik: `C:\xampp\php\php.exe database\seed.php`. İlk gerçek içeriği girmeden önce örnekleri paneldeki **Makaleler/Projeler** sayfalarından silin.

**İpuçları (XAMPP)**
- **GD / zip'i açın:** `C:\xampp\php\php.ini` dosyasında `;extension=gd` ve `;extension=zip` satırlarının başındaki `;` işaretini silin, Apache'yi yeniden başlatın.
- Apache günlüğünde `Unable to load dynamic library 'curl'` uyarısı görürseniz bu uygulamayı etkilemez (uygulama cURL kullanmaz).
- `localhost` bazen `::1` (IPv6), bazen `127.0.0.1` olarak çözülür; oturum IP önekine bağlı olduğu için giriş arada düşebilir.
  Sorun yaşarsanız tarayıcıda hep `http://localhost/...` kullanın ya da yerelde `.env` içinde `SESSION_IP_BAGLA=false` yapın (canlıda `true` bırakın).
- Video yüklemek için PHP limitleri `public/.htaccess` içinde 64 MB'a çıkarılmıştır (Apache + mod_php). Başka kurulumda bkz. [Yükleme sınırları](#yükleme-sınırları-görselvideo).

## Linux — Apache

```bash
sudo apt update
sudo apt install apache2 php8.2 libapache2-mod-php8.2 php8.2-mysql php8.2-mbstring php8.2-gd php8.2-zip mariadb-server
sudo a2enmod rewrite headers expires deflate
```

`/etc/apache2/sites-available/portfolyo.conf`:
```apache
<VirtualHost *:80>
    ServerName ornek.com
    DocumentRoot /var/www/portfolyo/public
    <Directory /var/www/portfolyo/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
```bash
sudo a2ensite portfolyo.conf && sudo systemctl reload apache2

# Veritabanı ve kullanıcı (root yerine ayrı, yetkisi kısıtlı kullanıcı)
sudo mysql -e "CREATE DATABASE portfolyo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
               CREATE USER 'portfolyo_app'@'localhost' IDENTIFIED BY 'GucluBirParola!'; \
               GRANT ALL ON portfolyo.* TO 'portfolyo_app'@'localhost';"

cd /var/www/portfolyo
cp config/.env.example .env && nano .env     # DB_*, APP_URL=https://ornek.com, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE=true, HSTS=true
php database/install.php
sudo chown -R www-data:www-data storage && sudo chmod -R 775 storage
sudo chmod 600 .env && sudo chown www-data:www-data .env
sudo certbot --apache -d ornek.com            # ücretsiz HTTPS
```

## Linux — Nginx

```bash
sudo apt install nginx php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-gd php8.2-zip mariadb-server
```
Hazır, güvenlik başlıkları ve gizli dosya engeli içeren yapılandırma: [`nginx.conf.example`](../nginx.conf.example).
Kendi sitenize göre `server_name`, `root` ve `fastcgi_pass` satırlarını düzenleyin; ardından Apache bölümündeki veritabanı/`.env`/`install.php` adımlarını uygulayın.
Nginx'te `client_max_body_size` (örnekte 72m) ve PHP-FPM'de `upload_max_filesize`/`post_max_size` değerleri video sınırınızdan büyük olmalıdır.

## macOS

```bash
brew install php mysql
brew services start mysql
cp config/.env.example .env && php database/install.php
php -S localhost:8000 -t public public/index.php
```

## Docker

Depodaki `Dockerfile` ve `docker-compose.yml` (PHP 8.2 + Apache + MySQL 8) kullanılır:

```bash
cp config/.env.example .env
#   .env içinde zorunlu: DB_SIFRE=GucluBirParola   UYGULAMA_ANAHTARI=<openssl rand -hex 32 çıktısı>
docker compose up -d --build
docker compose exec app php database/install.php      # şema + ilk yönetici
docker compose exec app php database/seed.php         # (isteğe bağlı) örnek içerik
```
Site: `http://localhost:8080` · Yönetim: `/admin/giris`.

- MySQL portu dışarı açılmaz; yalnızca uygulama erişir. Yüklemeler, oturumlar ve yedekler adlandırılmış volume'larda kalır.
- Yedek: `docker compose exec app php bin/yedekle.php --yuklemeler` (dosyalar `uygulama_yedek` volume'unda).
- Güncelleme: `git pull && docker compose up -d --build && docker compose exec app php database/install.php`.
- HTTPS arkasında yayınlarken `.env`: `SESSION_SECURE=true`, `HSTS=true`, `APP_DEBUG=false`, `APP_URL=https://...`.

## Paylaşımlı hosting

Alan adı + hosting + SSL ile yayınlamak için **adım adım rehber: [YAYINLAMA.md](YAYINLAMA.md)** (cPanel/Plesk, DNS, Cloudflare, SSL, e-posta, yedek).
Kısaca:

- En güvenli yol, alan adının **DocumentRoot**'unu projenin `public/` klasörüne yönlendirmektir (cPanel → *Domains → Document Root*).
  Yapamıyorsanız tüm projeyi `public_html` içine koyun: kök `.htaccess` istekleri `public/` içine yönlendirir ve `app/`, `config/`, `.env`, `database/`, `bin/`, `storage/` gibi dizinleri 403 ile kapatır. **Kurulumdan sonra** `/.env`, `/app/Core/Veritabani.php`, `/config/config.php`, `/storage/` adreslerinin içerik göstermediğini doğrulayın.
- **SSH yoksa** kurulumu tarayıcıdan yapın: `.env` içine en az 16 karakterlik bir `KURULUM_TOKEN` yazıp `https://alanadiniz.com/kurulum` adresini açın (sihirbaz varsayılan olarak **kapalıdır**; token yoksa veya bir kullanıcı varsa 404 verir). Kurulum bitince `KURULUM_TOKEN` satırını silin.
- SSL'i açtıktan sonra `public/.htaccess` içindeki hazır HTTP→HTTPS yönlendirmesini etkinleştirin; Cloudflare kullanıyorsanız `GUVENILIR_PROXY=true` yapın ve SSL modunu *Full (strict)* seçin.
- Yönetim paneli **Dashboard → Canlı ortam kontrolü** kartı, canlıda yanlış kalan ayarları (debug açık, HTTPS yok, `install.php` duruyor, proxy ayarı eksik, yazılamayan `storage`, PHP yükleme sınırı…) sizin için listeler.

## Yapılandırma (.env)

Tüm değerler ve açıklamaları [`config/.env.example`](../config/.env.example) içindedir. En önemlileri:

| Anahtar | Yerel | Canlı |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_URL` | `http://localhost:8000` | `https://alanadiniz.com` |
| `SESSION_SECURE` | `false` | **`true`** (HTTPS) |
| `HSTS` | `false` | `true` |
| `UYGULAMA_ANAHTARI` | otomatik | otomatik üretilir; **sonradan değiştirmeyin** (2FA anahtarları çözülemez) |

Site içeriği/markası (başlık, ana sayfa metni, Hakkımda, sosyal bağlantılar…) `.env`'de değil, panelde **Ayarlar** altındadır.

## Yükleme sınırları (görsel/video)

Uygulama sınırları `.env` içindedir: `YUKLEME_MAX_BOYUT_MB` (görsel, varsayılan 5) ve `YUKLEME_VIDEO_MAX_MB` (video, varsayılan 50).
Bunların ötesinde **PHP'nin kendi sınırları** da yeterince büyük olmalıdır, yoksa "Dosya sunucunun yükleme sınırını aşıyor" hatası alırsınız:

| Kurulum | Nerede |
|---|---|
| Apache + mod_php (XAMPP, çoğu paylaşımlı hosting) | `public/.htaccess` (hazır: 64M / 72M) |
| PHP-FPM / CGI | `php.ini` veya `public/.user.ini`: `upload_max_filesize = 64M`, `post_max_size = 72M`, `memory_limit = 256M` |
| Nginx | `client_max_body_size 72m;` |
| Docker | `docker/php.ini` (hazır) |

## E-posta bildirimi (SMTP)

İletişim formundan mesaj geldiğinde size e-posta göndermek için `.env` içinde `MAIL_*` alanlarını doldurun (boş bırakırsanız e-posta gitmez; mesajlar yine panelde görünür):

```ini
# Gmail (hesapta 2 adımlı doğrulama + "Uygulama parolası" gerekir)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SIFRELEME=tls
MAIL_KULLANICI=adiniz@gmail.com
MAIL_SIFRE=uygulama-parolasi
```
Diğer sağlayıcılar: Outlook/Microsoft 365 `smtp.office365.com:587 (tls)`, Yandex `smtp.yandex.com:465 (ssl)`, hosting SMTP'niz.
Sınamak için: `php bin/eposta-test.php` (ya da `php bin/eposta-test.php baska@adres.com`). Panelde **Ayarlar → İletişim ve altbilgi → Yeni mesajda e-posta ile bildir** işaretli olmalıdır.
Gönderim yanıt verildikten sonra yapılır; SMTP yavaş/hatalı olsa bile ziyaretçi beklemez ve mesaj kaybolmaz. Hatalar `storage/logs/uygulama.log` içine yazılır.

## Yedekleme ve geri yükleme

```bash
php bin/yedekle.php                  # storage/yedekler/portfolyo-YYYYMMDD-HHMMSS.sql.gz
php bin/yedekle.php --yuklemeler     # + yuklemeler-....zip (php zip eklentisi gerekir)
php bin/yedekle.php --sakla=30       # en yeni 30 yedeği tut (varsayılan 14)
```
mysqldump gerekmez. Yedek dosyaları yalnızca sahibi tarafından okunabilir (0600) ve web'den erişilemeyen `storage/` içindedir. **Yedekleri sunucu dışına da kopyalayın.**

**Geri yükleme:**
```bash
gunzip -c storage/yedekler/portfolyo-20261108-030000.sql.gz | mysql -u kullanici -p veritabani_adi
# Windows/XAMPP:  C:\xampp\php\php.exe -r "echo gzdecode(file_get_contents($argv[1]));" yedek.sql.gz | C:\xampp\mysql\bin\mysql.exe -uroot portfolyo
```
Medya zip'ini `storage/yuklemeler/` içine açın.

**Zamanlama**
- Linux/macOS (cron): `0 3 * * * cd /var/www/portfolyo && php bin/yedekle.php --yuklemeler >> storage/logs/yedek.log 2>&1`
- Windows (Görev Zamanlayıcı):
  ```bat
  schtasks /Create /SC DAILY /ST 03:00 /TN "Portfolyo Yedek" /TR "C:\xampp\php\php.exe C:\xampp\htdocs\portfolyo\bin\yedekle.php --yuklemeler"
  ```

## Güncelleme

1. Önce yedek alın: `php bin/yedekle.php --yuklemeler`
2. Yeni sürümün dosyalarını üzerine kopyalayın (`git pull` ya da arşivi açın). **`.env` ve `storage/` korunur.**
3. `php database/install.php` — mevcut verinize dokunmadan yeni tabloları/sütunları ekler ("Geçiş: …" satırları yazar).
   **SSH yoksa** bu adımı atlayabilirsiniz: yönetim paneline girdiğinizde şema geçişleri kendiliğinden uygulanır.
4. [CHANGELOG.md](../CHANGELOG.md) içindeki o sürümün notlarını okuyun.

## Parola / 2FA sıfırlama

```bash
php database/install.php --parola-sifirla=ben@ornek.com
php database/install.php --2fa-sifirla=ben@ornek.com      # telefonu ve yedek kodları kaybeden kullanıcı için
php database/install.php --yeni-admin                      # ek yönetici oluştur
```

## Canlıya çıkış kontrol listesi

- [ ] **HTTPS** açık (Let's Encrypt) ve `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE=true`, `HSTS=true`, `APP_URL=https://…`
- [ ] DocumentRoot `public/` (ya da gizli dizinlerin 403 verdiği doğrulandı)
- [ ] `database/seed.php` ve **`database/install.php` sunucudan silindi** (güncellemede geçici olarak geri koyabilirsiniz); örnek içerik silindi
- [ ] Veritabanı için ayrı, kısıtlı kullanıcı (root değil)
- [ ] `.env` izni `600`; klasörler `755`, dosyalar `644`, `storage/` yazılabilir
- [ ] Panelde **Ayarlar** dolduruldu (site adı, e-posta, hero, Hakkımda, sosyal bağlantılar)
- [ ] **İki adımlı doğrulama** açıldı (Yönetim → kullanıcı adınız → Profil) ve yedek kodlar kaydedildi
- [ ] Otomatik **yedek** zamanlandı ve bir kez geri yükleme denendi
- [ ] (İsteğe bağlı) SMTP ayarlandı: `php bin/eposta-test.php`
- [ ] `https://alanadiniz.com/robots.txt`, `/sitemap.xml`, `/rss.xml` açılıyor; Google Search Console'a sitemap eklendi

## Sorun giderme

| Belirti | Neden / çözüm |
|---|---|
| Alt sayfalar 404 | `mod_rewrite` kapalı ya da `AllowOverride None`. Apache: `a2enmod rewrite`, VirtualHost'ta `AllowOverride All`. Alt klasörde çalışıyorsanız kök `.htaccess` mevcut olmalı. |
| `SQLSTATE[HY000] [2002]` | MySQL çalışmıyor / `DB_HOST`/`DB_PORT` yanlış. XAMPP'ta MySQL'i başlatın. |
| `Access denied for user` | `.env` içindeki `DB_KULLANICI` / `DB_SIFRE` yanlış. |
| `could not find driver` | `php.ini`'de `extension=pdo_mysql` açık değil. |
| Giriş yapınca tekrar giriş ekranına dönüyor | HTTP üzerinde `SESSION_SECURE=true`. Yerelde `false` yapın; canlıda HTTPS kullanın. Yerelde `localhost` IPv4/IPv6 geçişi için bkz. XAMPP ipuçları. |
| Video/görsel yüklenmiyor, "sunucunun yükleme sınırını aşıyor" | PHP limitleri küçük: bkz. [Yükleme sınırları](#yükleme-sınırları-görselvideo). |
| Yüklenen görsel/video 404 | `storage/yuklemeler/` yazılabilir mi? Dosya gerçekten orada mı? (Medya yalnızca `/yuklemeler/{ad}` rotasından sunulur.) |
| Beyaz sayfa / 500 | `storage/logs/uygulama.log` ve `storage/logs/php-hata.log` dosyalarına bakın; geçici olarak `APP_DEBUG=true` yapın. |
| E-posta gelmiyor | `php bin/eposta-test.php` ile sınayın; `MAIL_*` değerleri, 587/tls ↔ 465/ssl eşleşmesi ve gönderen adresinin SMTP hesabınıza ait olması. |
| 2FA kodu "hatalı" | Telefonunuzun saati otomatik olmalı (±30 sn tolerans). Aynı kod iki kez kullanılamaz; bir sonraki kodu bekleyin. |
