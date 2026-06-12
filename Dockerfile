# Laravel backend image for Render (Render has no native PHP runtime — it runs
# this Docker container instead). PHP 8.4 + Apache serving from /public.
# Must be 8.4: composer.lock resolved Symfony 8 components that require PHP >=8.4.
FROM php:8.4-apache

# install-php-extensions reliably installs PHP extensions *with* their system
# libraries — avoids the "works locally, fails in Docker" missing-ext errors.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        mbstring \
        gd \
        exif \
        pcntl \
        opcache

# System tools needed by composer (git/unzip for VCS + dist installs).
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (from the official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_MEMORY_LIMIT=-1

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
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Render injects $PORT; the start script binds Apache to it.
CMD ["start.sh"]
