FROM php:8.2-fpm-alpine

COPY docker/conf/php.ini /usr/local/etc/php/conf.d/php.ini

# Packages for PHP extensions
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    ca-certificates \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        bcmath \
        intl \
        gd \
        zip

# Composer — multi-stage copy (no network dependency, no zlib issues)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
