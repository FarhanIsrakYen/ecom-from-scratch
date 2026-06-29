FROM php:8.4-fpm-alpine

RUN apk add --no-cache bash git icu-dev libzip-dev oniguruma-dev sqlite-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install intl mbstring opcache pdo_mysql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
