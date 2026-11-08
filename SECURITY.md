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
| Bilgi sızdırma | Üretimde hata ayrıntısı gösterilmez; günlüğe yazılır. `.env`, `app/`, `config/`, `storage/`, `bin/` web'den erişilemez |

## Dağıtım sahibi olarak sizin sorumluluklarınız

- HTTPS kullanın; `.env` içinde `APP_DEBUG=false`, `SESSION_SECURE=true`, `HSTS=true`.
- Yönetici hesapları için **2FA** açın, güçlü ve benzersiz parola kullanın.
- `database/install.php` ve `database/seed.php` dosyalarını canlıdan kaldırın; `.env` izni `600` olsun; kurulum bittiyse `KURULUM_TOKEN` satırını silin.
- Cloudflare/ters vekil arkasındaysanız `GUVENILIR_PROXY=true` yapın (hız sınırı ve oturum bağlama gerçek IP'yi görsün). Panelin **Canlı ortam kontrolü** kartı bunları ve diğer yanlış ayarları sizin için denetler.
- Düzenli **yedek** alın (`php bin/yedekle.php`) ve yedekleri sunucu dışında saklayın.
- PHP ve veritabanını güncel tutun.
- Ayrıntılı liste: [docs/KURULUM.md → Canlıya çıkış kontrol listesi](docs/KURULUM.md#canlıya-çıkış-kontrol-listesi).
