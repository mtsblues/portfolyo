FROM php:8.2-apache

# Sistem bağımlılıkları + PHP uzantıları
#  pdo_mysql, mbstring : zorunlu
#  gd (jpeg/png/webp)  : çok büyük görselleri küçültmek için (yoksa da çalışır; üstveri temizliği GD'siz yapılır)
#  exif                : fotoğraf yönü (EXIF Orientation) için
#  zip                 : bin/yedekle.php --yuklemeler
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libonig-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd mbstring exif zip \
    && rm -rf /var/lib/apt/lists/*

# Video yükleme sınırları (YUKLEME_VIDEO_MAX_MB ile uyumlu olmalı)
COPY docker/php.ini /usr/local/etc/php/conf.d/portfolyo.ini

# Apache modülleri: temiz URL (rewrite), güvenlik/cache başlıkları (headers, expires), gzip (deflate)
RUN a2enmod rewrite headers expires deflate

# DocumentRoot = /public ve public/.htaccess için AllowOverride All
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
COPY docker/apache-portfolyo.conf /etc/apache2/conf-available/portfolyo.conf
RUN a2enconf portfolyo

WORKDIR /var/www/html
COPY . .

# Yazılabilir dizinler (oturum, cache, yüklemeler); .env'i imaja dahil etme (.dockerignore)
RUN mkdir -p storage/cache storage/oturum storage/yuklemeler storage/yedekler storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && find /var/www/html -type f -exec chmod 644 {} + \
    && chmod -R 775 storage

EXPOSE 80
