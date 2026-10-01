FROM php:8.4-fpm

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl pdo_pgsql zip opcache apcu

COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app
