FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        freetype \
        freetype-dev \
        git \
        icu-dev \
        libjpeg-turbo \
        libjpeg-turbo-dev \
        libpng \
        libpng-dev \
        libwebp \
        libwebp-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        sqlite-dev \
        supervisor \
        zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install bcmath gd intl mbstring opcache pdo_mysql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && mkdir -p /etc/supervisor/conf.d /var/log/supervisor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
COPY docker/supervisor/laravel-scheduler.conf /etc/supervisor/conf.d/laravel-scheduler.conf

WORKDIR /var/www/html
