# Güvenlik politikası

## Güvenlik açığı bildirimi

Bir güvenlik açığı buldunuz mu? **Lütfen herkese açık bir issue açmayın.**

1. Depoda **Security → Report a vulnerability** (GitHub *private vulnerability reporting*) üzerinden özel bildirim gönderin.
2. Bildirimde şunları yazın: etkilenen sürüm/dosya, adım adım yeniden üretme, etkisi ve (varsa) önerdiğiniz düzeltme.

Bildirimleri makul sürede yanıtlamaya ve düzeltme yayımlanana kadar ayrıntıları gizli tutmaya çalışırız. Düzeltme çıktığında, isterseniz adınızla teşekkür ederiz.

## Desteklenen sürümler

Yalnızca en güncel sürüm düzeltme alır. Güncelleme: [docs/KURULUM.md](docs/KURULUM.md#güncelleme).

## Tasarım gereği güvenlik önlemleri

| Alan | Önlem |
|---|---|
| SQL enjeksiyonu | Yalnızca PDO + gerçek (native) hazır sorgular; kullanıcı girdisi SQL'e birleştirilmez |
| XSS | Her çıktı `htmlspecialchars` (ENT_QUOTES) ile kaçışlanır; Markdown önce kaçışlanır, yalnızca çevirici etiket üretir, URL'ler beyaz listeyle sınırlıdır |
| CSP | `default-src 'self'`, script'ler nonce'lu, harici kaynak yok; yalnızca YouTube-nocookie/Vimeo `frame-src` açıktır |
| CSRF | Tüm POST'larda oturum başına rastgele token (form alanı veya `X-CSRF-Token`), `hash_equals` |
| Oturum | HttpOnly, SameSite=Lax, `Secure` (HTTPS), strict mode, girişte kimlik yenileme, boşta/mutlak zaman aşımı, IP öneki + User-Agent bağlama |
| Kimlik doğrulama | bcrypt (cost 12), zamanlama eşitleme, kimlik+IP ve IP bazlı kilit, **TOTP 2FA** (gizli anahtar AES-256-GCM ile şifreli, tekrar kullanım engeli, tek kullanımlık yedek kodlar) |
| Yükleme | İçerik tabanlı MIME doğrulaması (uzantıya/istemci MIME'ına güvenilmez), SVG/HTML/PHP reddi, rastgele dosya adı, **web root dışında saklama**, sabit `Content-Type` + `nosniff` + sandbox CSP ile sunum, EXIF/GPS/IPTC/XMP temizliği, dosya sonuna eklenmiş artıkların (polyglot) atılması |
| E-posta | CR/LF başlık enjeksiyonuna karşı temizleme ve adres doğrulama, TLS sertifika doğrulaması |
| Hız sınırı | İletişim formu, giriş denemeleri ve kurulum sihirbazı |
| Kurulum sihirbazı | Varsayılan **kapalı**; yalnızca `.env` içinde ≥16 karakterlik `KURULUM_TOKEN` varsa ve veritabanında hiç kullanıcı yokken açılır (aksi halde 404), anahtar `hash_equals` ile doğrulanır, ilk yönetici oluşunca kapanır |
| Parola sıfırlama | 256 bit rastgele jeton, veritabanında yalnızca SHA-256 özeti, 60 dk, tek kullanımlık (yarışa karşı atomik tüketim); yanıt kullanıcının varlığını ele vermez (aynı mesaj, jeton/e-posta işi yanıttan SONRA); bağlantı `APP_URL`'den üretilir (Host başlığı sahteciliği yok); `Referrer-Policy: no-referrer`; sıfırlayınca tüm oturumlar kapanır |
| Oturum izleme | Oturum başına rastgele izleme anahtarı (özeti saklanır); uzaktan kapatma bir sonraki istekte etkilidir; parola değişince diğer oturumlar kapanır |
| Denetim günlüğü | Giriş/çıkış, başarısız deneme, parola/2FA, yedek ve tüm yönetici işlemleri; IP `ip_saklama` ayarına uyar (tam/kısaltılmış/yok) |
| Yedek / geri yükleme | Yalnızca yönetici; geri yükleme parola (+2FA) ve yazılı onay ister; yedek dosyası **katı beyaz liste** ile doğrulanır (yalnızca uygulamanın tabloları için `DROP/CREATE/INSERT`; `SELECT`, `GRANT`, alt sorgu, dosya yazma, yığınlı ifade reddedilir); öncesinde otomatik yedek, hata olursa geri alma; indirme/geri yükleme adları yol gezdirmeye karşı doğrulanır; dış hedef parolaları yalnızca `.env`'de |
| Herkese açık özgeçmiş | Varsayılan **kapalı** (404); telefon/e-posta yalnızca yöneticinin ayrıca doldurduğu alanlardan gösterilir (iletişim formu e-postası ifşa edilmez); metinler Markdown beyaz listesinden geçer, diğer alanlar kaçışlanır; vCard değerleri kaçışlanır ve satır sonu enjeksiyonu yapılamaz; teknoloji filtresi parametreli sorgudur |
| Bilgi sızdırma | Üretimde hata ayrıntısı gösterilmez; günlüğe yazılır. `.env`, `app/`, `config/`, `storage/`, `bin/` web'den erişilemez |

## Dağıtım sahibi olarak sizin sorumluluklarınız

- HTTPS kullanın; `.env` içinde `APP_DEBUG=false`, `SESSION_SECURE=true`, `HSTS=true`.
- Yönetici hesapları için **2FA** açın, güçlü ve benzersiz parola kullanın.
- `database/install.php` ve `database/seed.php` dosyalarını canlıdan kaldırın; `.env` izni `600` olsun; kurulum bittiyse `KURULUM_TOKEN` satırını silin.
- Cloudflare/ters vekil arkasındaysanız `GUVENILIR_PROXY=true` yapın (hız sınırı ve oturum bağlama gerçek IP'yi görsün). Panelin **Canlı ortam kontrolü** kartı bunları ve diğer yanlış ayarları sizin için denetler.
- Düzenli **yedek** alın (Ayarlar → Otomatik yedekleme ya da `php bin/yedekle.php`) ve yedekleri sunucu dışında saklayın (e-posta/FTP/WebDAV hedefi). Yedekler parola özetleri ve ziyaretçi mesajları içerir.
- İletişim formu kişisel veri topluyorsa aydınlatma metni yayınlayın (Sayfalar → hazır şablonlar). Şablonlar genel bir başlangıçtır, hukuki danışmanlık değildir.
- PHP ve veritabanını güncel tutun.
- Ayrıntılı liste: [docs/KURULUM.md → Canlıya çıkış kontrol listesi](docs/KURULUM.md#canlıya-çıkış-kontrol-listesi).
