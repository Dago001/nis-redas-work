#!/usr/bin/env bash
set -e

PORT="${PORT:-10000}"

# Map Render's DATABASE_URL to Laravel 12's DB_URL and enforce SSL for Neon PostgreSQL
if [ -z "${DB_URL:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
    export DB_URL="${DATABASE_URL}"
fi
export DB_SSLMODE="${DB_SSLMODE:-require}"

# Configure Apache to listen on Render's injected PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Create storage symlink, run migrations and idempotent directory seeder
php artisan storage:link --force || true
php artisan migrate --force
php artisan db:seed --class=NisDirectorySeeder --force || true

# Cache config and views for fast cold-starts (skip route:cache due to closure routes)
php artisan config:cache
php artisan view:cache

# Ensure Apache (www-data) owns all storage & cache directories created above
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

exec apache2-foreground
