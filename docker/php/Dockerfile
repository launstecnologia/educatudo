FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mysqli bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-educatudo.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-educatudo.conf

WORKDIR /var/www/html
