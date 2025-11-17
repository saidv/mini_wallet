# Multi-stage build for Laravel backend
ARG PHP_VERSION=8.3
ARG COMPOSER_VERSION=2.7
ARG ALPINE_VERSION=3.19

FROM composer:${COMPOSER_VERSION} AS composer

WORKDIR /app

COPY backend/composer.* ./

RUN if [ -f composer.json ]; then \
        composer install \
            --no-dev \
            --no-interaction \
            --no-progress \
            --no-scripts \
            --prefer-dist \
            --optimize-autoloader \
            --classmap-authoritative \
        && composer clear-cache; \
    fi

COPY backend ./

# Run post-autoload scripts if vendor exists
RUN if [ -d vendor ]; then \
        composer dump-autoload --optimize --classmap-authoritative; \
    fi

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS base

RUN apk update && apk upgrade && apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    oniguruma \
    autoconf \
    g++ \
    make \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del \
        autoconf \
        g++ \
        make \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
    && rm -rf /tmp/pear /var/cache/apk/*

FROM base AS production

WORKDIR /var/www/html

# Configure PHP
COPY infra/backend/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY infra/backend/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY infra/backend/php/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-custom.conf

# Copy Nginx configuration
COPY infra/backend/nginx/nginx.conf /etc/nginx/nginx.conf
COPY infra/backend/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy Supervisor configuration
COPY infra/backend/supervisor/supervisord.conf /etc/supervisord.conf

# Copy SSL certificates
COPY infra/backend/certs/localhost.pem /etc/nginx/certs/localhost.pem
COPY infra/backend/certs/localhost-key.pem /etc/nginx/certs/localhost-key.pem

# Create necessary directories and set permissions
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /var/log/supervisor \
    /run/nginx \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/log/supervisor \
    && chown -R www-data:www-data /run/nginx

# Copy application code
COPY --chown=www-data:www-data backend /var/www/html

# Copy vendor directory from composer stage if it exists
RUN mkdir -p /var/www/html/vendor
COPY --from=composer --chown=www-data:www-data /app/vendor/ /var/www/html/vendor/

# Set permissions for storage and bootstrap/cache if they exist
RUN if [ -d storage ]; then chmod -R 775 storage; fi \
    && if [ -d bootstrap/cache ]; then chmod -R 775 bootstrap/cache; fi

# Generate application key if Laravel is installed
RUN if [ -f artisan ]; then \
        if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi; \
        php artisan key:generate --ansi --no-interaction || true; \
    fi

# Healthcheck configuration
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

EXPOSE 443

# Run supervisord as root (it will manage nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]