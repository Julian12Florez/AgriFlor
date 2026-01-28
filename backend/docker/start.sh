#!/bin/bash
set -e

echo "==> Starting AgriFlor Backend..."

# Substitute PORT in nginx config
export PORT=${PORT:-10000}
envsubst '$PORT' < /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/default

# Create storage directories if needed
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/{cache,sessions,views}
mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Cache config for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force || echo "WARNING: Migrations failed, continuing..."

# Seed if needed (only run once)
if [ "$RUN_SEED" = "true" ]; then
    echo "==> Running seeders..."
    php artisan db:seed --force || echo "WARNING: Seeder failed, continuing..."
fi

echo "==> Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
