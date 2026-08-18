# Assets são pré-buildados localmente e commitados em public/build.
# O servidor só precisa rodar composer install + configurar PHP-FPM + Nginx.
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        mbstring \
        xml \
        ctype \
        fileinfo \
        gd \
        zip \
        intl \
        opcache \
    && pecl channel-update pecl.php.net \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/app/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/app/nginx.conf /etc/nginx/sites-available/sile
COPY docker/app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/app/entrypoint.sh /entrypoint.sh
COPY docker/app/migrate.sh /migrate.sh
RUN chmod +x /entrypoint.sh /migrate.sh \
    && mkdir -p /var/log/supervisor /run/php \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/sile /etc/nginx/sites-enabled/sile

COPY composer*.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

RUN mkdir -p storage/framework/views \
    && mkdir -p storage/framework/cache \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/app/public \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ARG APP_VERSION=
ARG APP_REVISION=
ENV APP_VERSION=${APP_VERSION}
ENV APP_REVISION=${APP_REVISION}

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
