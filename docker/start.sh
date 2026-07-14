#!/usr/bin/env bash
set -e

# Fail closed: a production container must never run with debug mode on, or an
# unhandled exception renders Laravel's error page and leaks every secret in the
# environment to the browser. Refuse to boot instead of leaking.
if [ "${APP_ENV}" = "production" ] && [ "${APP_DEBUG}" = "true" ]; then
  echo "FATAL: APP_DEBUG=true with APP_ENV=production. Set APP_DEBUG=false in the environment before deploying." >&2
  exit 1
fi

# Render provides $PORT; Apache must listen on it (default 80 locally).
PORT="${PORT:-80}"
sed -ri "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Set PHP upload limit overrides for containerized environments
if [ -d "/usr/local/etc/php/conf.d" ]; then
  cat <<'EOF' > /usr/local/etc/php/conf.d/uploads.ini
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 120
EOF
fi

# Generate an APP_KEY at runtime only if one wasn't supplied via env.
if [ -z "${APP_KEY}" ]; then
  echo "WARNING: APP_KEY not set — generating an ephemeral key. Set APP_KEY in Render for stable sessions/encryption."
  php artisan key:generate --force
fi

# Cache config/routes/views for production performance.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apply any pending database migrations (idempotent — safe when DB is already set up).
php artisan migrate --force

# Link public storage for uploaded gallery media.
php artisan storage:link || true

exec apache2-foreground
