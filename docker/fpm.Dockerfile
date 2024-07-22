FROM php:8.2-fpm-alpine

COPY docker/conf/php.ini /usr/local/etc/php/conf.d/php.ini

RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-install bcmath

RUN apk add --no-cache \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) gd
