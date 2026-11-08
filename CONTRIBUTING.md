# Katkıda bulunma

Katkılarınız için teşekkürler! Hata düzeltmeleri, belgeler, çeviriler ve yeni özellikler memnuniyetle karşılanır.
Büyük bir değişiklik düşünüyorsanız, emek harcamadan önce bir **issue** açıp fikri tartışın.

> Güvenlik açıklarını issue olarak açmayın: [SECURITY.md](SECURITY.md).

## Geliştirme ortamı

```bash
git clone https://github.com/KULLANICI/portfolyo.git && cd portfolyo
cp config/.env.example .env           # DB_* değerlerini düzenleyin
php database/install.php              # veritabanı + ilk yönetici
php database/seed.php                 # örnek içerik (isteğe bağlı)
php -S localhost:8000 -t public public/index.php
```

Testler (veritabanı gerekmez):
```bash
php tests/calistir.php
python -I tests/statik_kontrol.py     # PHP'siz hızlı tutarlılık denetimi
find app config database public bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
```
CI (GitHub Actions) PHP 8.2/8.3/8.4'te bunları ve sıfırdan kurulum + duman testini çalıştırır.

## Proje ilkeleri

1. **Bağımlılıksız:** Composer/NPM paketi ve harici CDN eklemeyin. Gerçekten gerekiyorsa gerekçesini issue'da tartışın.
2. **Önce güvenlik:** her girdiyi sunucuda doğrulayın (`Dogrulayici`), her çıktıyı `e()` ile kaçışlayın, SQL'i yalnızca hazır sorguyla yazın, her POST'ta CSRF koruması olsun, yönetim uçları `AdminTemel` altında oturum/rol denetlesin. CSP'yi gevşetmeyin.
3. **Katmanlar:** Controller → Model → Veritabani. View SQL çalıştırmaz; Model görünüm/denetleyici çağırmaz.
4. **Kod stili:** PHP 8.2+, her dosyada `declare(strict_types=1);`, kapanış `?>` etiketi yok, PSR-12'ye yakın biçim, `.editorconfig`'e uyun. Sınıf/metot/alan adları Türkçe terminolojiyle tutarlıdır (`Makale`, `Yonlendirici`, `kaydet`…), kod yorumları Türkçedir.
5. **Geriye uyumluluk:** Veritabanı şeması değişiyorsa **hem** `database/schema.sql` **hem de** `database/migrasyon.php` güncellenmeli ve `App\Core\Kurulum::SEMA_SURUMU` **bir artırılmalıdır** (yönetici panele girince geçişler SSH olmadan otomatik uygulanır); mevcut kurulumlar `php database/install.php` ile veri kaybı olmadan yükselebilmelidir (migrasyon tekrar çalıştırılabilir/idempotent olmalı).
6. **Test:** yeni davranış için `tests/calistir.php` içine test ekleyin. Güvenlikle ilgili düzeltmelerde, açığı yeniden üreten bir test ekleyin.
7. **Belge:** kullanıcıya görünen değişiklikleri `docs/`, `config/.env.example` ve `CHANGELOG.md` içinde belirtin.

## Pull request süreci

1. Depoyu çatallayın, `main`'den bir dal açın (`duzeltme/kisa-aciklama`, `ozellik/kisa-aciklama`).
2. Küçük, odaklı commit'ler yapın; mesajları açık yazın ("Medya: silerken kullanım uyarısı ekle").
3. Testleri çalıştırın, PR şablonundaki kontrol listesini doldurun.
4. PR açın. Gözden geçirme sırasında değişiklik istenebilir.

## Çeviri / çok dillilik

Arayüz şimdilik Türkçedir. Metinler görünümlerde ve denetleyicilerde dağınıktır; çok dilli altyapı (ör. `lang/tr.php`, `lang/en.php` + `t()` yardımcısı) en çok istenen katkılardan biridir.
Başlamadan önce bir issue açıp yaklaşımı konuşalım.
