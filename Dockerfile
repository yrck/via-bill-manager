FROM php:8.4-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libicu-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_mysql intl zip pcntl opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN printf "upload_max_filesize=10M\npost_max_size=12M\n" > /usr/local/etc/php/conf.d/uploads.ini
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1
