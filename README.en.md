# Portfolyo — a personal portfolio, blog and knowledge base

A secure, fast and tidy **portfolio + blog + solutions archive** written in plain **PHP 8.2+** — no framework, no Composer dependencies.
It installs in minutes, runs on shared hosting, and everything about the site (name, hero text, About page, social links, logo…) is edited from the admin panel.

I built it for my own site and share it so **others can adapt it to their needs**.

> The admin/visitor UI is currently **Turkish only** (no i18n layer yet). Translation / i18n contributions are very welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).

## Features

- **Public site:** home (editable hero), projects, blog/knowledge base (categories, tags, search, pagination), About page, contact form, dark/light theme, mobile friendly, no external CDN/fonts
- **SEO:** clean URLs, `sitemap.xml`, **RSS**, dynamic `robots.txt`, Open Graph / Twitter cards, JSON-LD
- **Content:** safe Markdown, syntax-highlighted code blocks with a copy button, captioned images with lightbox, **galleries**, self-hosted **video** (MP4/WebM, seekable), **YouTube/Vimeo** embeds (privacy-friendly `youtube-nocookie`)
- **Media library:** multi-upload, alt text, copy-ready Markdown, "in use" delete protection; **EXIF/GPS metadata is stripped losslessly from JPEG/PNG/WebP** (no GD required)
- **Admin:** dashboard, articles, projects, categories, tags, messages, users (admin/editor roles), live Markdown preview, **two-factor authentication (TOTP)** with backup codes, brute-force lockout
- **Hosting-friendly:** token-protected browser installer (off by default) for shared hosting without SSH, automatic schema updates when an admin signs in, and a "live environment check" card in the dashboard (debug on, no HTTPS, missing proxy setting, leftover installer files…)
- **Ops:** SMTP notification for contact messages, dependency-free **backups** (`php bin/yedekle.php`), non-destructive **updates** (`php database/install.php`), Docker, Nginx/Apache samples, CI
- **Security:** PDO prepared statements, output escaping, CSRF, strict CSP (nonce), hardened sessions, bcrypt, content-based MIME validation for uploads (no SVG; files stored outside the web root), header-injection-safe SMTP, rate limits — see [SECURITY.md](SECURITY.md)

## Quick start

Requirements: **PHP 8.2+** (`pdo_mysql`, `mbstring`, `fileinfo`, `openssl`; recommended `gd`, `exif`, `zip`) and **MySQL 8 / MariaDB 10.4+**.

```bash
git clone https://github.com/USER/portfolyo.git
cd portfolyo
cp config/.env.example .env        # edit DB_* values (Windows: copy config\.env.example .env)
php database/install.php           # creates the database, tables and the first admin (prompts for a password)
php -S localhost:8000 -t public public/index.php
```

Site: <http://localhost:8000> · Admin: <http://localhost:8000/admin/giris>

Detailed guides (Turkish): [docs/KURULUM.md](docs/KURULUM.md) (XAMPP, Linux, Nginx, Docker, backups, SMTP), [docs/YAYINLAMA.md](docs/YAYINLAMA.md) (**going live with hosting + domain + SSL**, incl. a browser-based installer for hosts without SSH, Cloudflare notes) and [docs/KULLANIM.md](docs/KULLANIM.md) (writing content, media syntax, settings, 2FA).

## Commands

```bash
php database/install.php                          # install / update (data is preserved)
php database/install.php --parola-sifirla=a@b.c   # reset a password
php database/install.php --2fa-sifirla=a@b.c      # disable 2FA for a locked-out user
php bin/yedekle.php [--yuklemeler]                # database (+ media) backup → storage/yedekler/
php bin/eposta-test.php [to@example.com]          # test SMTP settings
php tests/calistir.php                            # automated tests (no database required)
```

## Markdown media syntax (cheat-sheet)

```markdown
![Caption](/yuklemeler/<file>.jpg)          captioned figure (click to enlarge)

:::galeri
![One](/yuklemeler/<a>.jpg)
![Two](/yuklemeler/<b>.jpg)
:::

@[video](/yuklemeler/<file>.mp4)            self-hosted video
@[youtube](https://youtu.be/VIDEO_ID)      or just the 11-character id
@[vimeo](https://vimeo.com/123456789)
```

## License

[MIT](LICENSE). Inter font: SIL OFL. highlight.js: BSD-3-Clause.
