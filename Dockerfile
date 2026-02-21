# Gunakan image PHP 8.2 dengan Apache
FROM php:8.2-apache

# Install ekstensi sistem yang dibutuhkan Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP (termasuk PDO MySQL untuk koneksi database)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Aktifkan mod_rewrite Apache (wajib untuk routing Laravel)
RUN a2enmod rewrite

# Set working directory ke /var/www/html
WORKDIR /var/www/html

# Copy semua file project ke dalam container
COPY . .

# Ubah DocumentRoot Apache agar mengarah ke folder public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer dari official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Jalankan Composer install untuk mengunduh dependencies (tanpa package dev)
RUN composer install --no-dev --optimize-autoloader

# Atur permission agar folder storage dan cache bisa ditulis oleh web server
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80
EXPOSE 80

# Command yang dijalankan saat container start: 
# 1. Jalankan migrasi database
# 2. Cache config & route
# 3. Jalankan Apache di foreground
CMD php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && apache2-foreground
