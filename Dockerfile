FROM php:8.4-fpm AS builder
ARG DEBIAN_FRONTEND=noninteractive

# Paquetes del sistema + headers necesarios
RUN apt-get update && apt-get install -y --no-install-recommends \
    g++ pkg-config \
    libonig-dev \             # ← necesario para mbstring (oniguruma)
    libicu-dev \
    libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev \
    git unzip curl gnupg2 \
    default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 # Extensiones PHP
 && docker-php-ext-install -j"$(nproc)" \
    intl pdo_mysql mbstring zip gd xml soap exif opcache \
 # PECL
 && pecl install redis && docker-php-ext-enable redis \
 # Limpieza
 && rm -rf /var/lib/apt/lists/*

# (Opcional) Node LTS para build de assets
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/qonpania
EXPOSE 9000
CMD ["php-fpm"]
