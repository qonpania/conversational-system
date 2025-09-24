FROM php:8.4-fpm AS builder

ARG DEBIAN_FRONTEND=noninteractive

# 1) Paquetes del sistema + headers necesarios
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev g++ \
    libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    git unzip curl gnupg2 \
    default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 # 2) Extensiones PHP (incluye intl)
 && docker-php-ext-install -j"$(nproc)" intl pdo pdo_mysql mbstring exif zip gd xml soap \
 # 3) PECL
 && pecl install redis && docker-php-ext-enable redis \
 # Limpieza
 && rm -rf /var/lib/apt/lists/*

# (Opcional) Node LTS para build de assets — usa 20.x (18 ya es EOL)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Workdir
WORKDIR /var/www/qonpania

EXPOSE 9000
CMD ["php-fpm"]
