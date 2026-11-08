## Ne değişti ve neden?

<!-- Kısaca anlatın; ilgili issue varsa bağlayın (örn. "Fixes #12"). -->

## Nasıl denedim?

- [ ] `php tests/calistir.php` geçiyor
- [ ] `python -I tests/statik_kontrol.py` temiz
- [ ] Değişikliği tarayıcıda / `curl` ile elle denedim
- [ ] Yeni davranış için test ekledim (mümkün olduğunda)

## Kontrol listesi

- [ ] Kullanıcı girdisi her yerde doğrulanıyor, çıktı `e()` ile kaçışlanıyor, SQL yalnızca hazır sorguyla
- [ ] POST işlemleri CSRF korumalı, yönetim uçları oturum/rol denetliyor
- [ ] Harici bağımlılık eklemedim (eklediysem gerekçesini yazdım) ve CSP'yi gevşetmedim
- [ ] Veritabanı değişikliği varsa `database/schema.sql` **ve** `database/migrasyon.php` güncellendi (mevcut kurulumlar bozulmuyor)
- [ ] `config/.env.example`, `README`/`docs` ve `CHANGELOG.md` gerekiyorsa güncellendi
