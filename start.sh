#!/bin/bash
set -e

# Cache config & route agar start lebih cepat
echo "Caching configuration..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Caching views..."
php artisan view:cache

# Jalankan migrasi database paksa pada production
echo "Running database migrations..."
php artisan migrate --force

# Mulai web server apache di foreground
echo "Starting Apache web server..."
exec apache2-foreground
