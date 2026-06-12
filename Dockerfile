# Laravel backend image for Render (Render has no native PHP runtime — it runs
# this Docker container instead). PHP 8.3 + Apache serving from /public.
FROM php:8.3-apache

# --- System deps + PHP extensions Laravel/this app needs -----------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl mbstring opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (from the official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: enable rewrite + headers, point docroot at Laravel's public/
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

WORKDIR /var/www/html

# --- Install PHP dependencies (cached layer) -----------------------------
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- App source ----------------------------------------------------------
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Render injects $PORT; the start script binds Apache to it.
CMD ["start.sh"]
