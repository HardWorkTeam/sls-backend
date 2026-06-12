#!/usr/bin/env bash
set -e

# Render provides $PORT; Apache must listen on it (default 80 locally).
PORT="${PORT:-80}"
sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Generate an APP_KEY at runtime only if one wasn't supplied via env.
if [ -z "${APP_KEY}" ]; then
  echo "WARNING: APP_KEY not set — generating an ephemeral key. Set APP_KEY in Render for stable sessions/encryption."
  php artisan key:generate --force
fi

# Cache config/routes/views for production performance.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apply database migrations. Add --seed only on first deploy if you want demo data.
php artisan migrate --force

# Link public storage for uploaded gallery media.
php artisan storage:link || true

exec apache2-foreground
