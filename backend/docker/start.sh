#!/bin/bash
set -e

echo "==> AgriFlor Backend starting..."
echo "==> PORT=${PORT:-10000}"

# Substitute PORT in nginx config
export PORT=${PORT:-10000}
rm -f /etc/nginx/sites-enabled/default
envsubst '$PORT' < /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/app.conf
echo "==> Nginx configured on port $PORT"

# Ensure storage directories exist
mkdir -p /var/www/storage/logs \
         /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Generate APP_KEY if missing
if [ -z "$APP_KEY" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run migrations (non-blocking)
echo "==> Running migrations..."
php artisan migrate --force 2>&1 || echo "==> WARNING: Migrations had issues, continuing..."

echo "==> Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
