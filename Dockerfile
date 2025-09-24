# Imagen base PHP-FPM
FROM php:8.4-fpm AS builder
ARG DEBIAN_FRONTEND=noninteractive

# 1) Paquetes del sistema y headers
RUN apt-get update && apt-get install -y --no-install-recommends \
    g++ \
    libicu-dev \
    libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev \
    git unzip curl gnupg2 \
    default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 # 2) Extensiones PHP (incluye exif)
 && docker-php-ext-install -j"$(nproc)" \
    intl pdo_mysql mbstring zip gd xml soap exif opcache \
 # 3) PECL
 && pecl install redis && docker-php-ext-enable redis \
 # Limpieza
 && rm -rf /var/lib/apt/lists/*

# (Opcional) Node LTS para build de assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (como root, permitido)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Workdir del proyecto
WORKDIR /var/www/qonpania

EXPOSE 9000
CMD ["php-fpm"]
