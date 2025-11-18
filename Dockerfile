FROM php:8.4-fpm AS builder

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev g++ \
    libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    git unzip curl gnupg2 \
    default-mysql-client \
    libpq-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    intl pdo pdo_mysql pdo_pgsql mbstring exif zip gd xml soap pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*

# Node LTS 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Config PHP extra
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/qonpania
EXPOSE 9000
CMD ["php-fpm"]
