FROM php:8.2-apache

# Sistem paketlerini yükle
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    pkg-config \
    zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_sqlite gd \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

# Gerekirse uploads ve logos klasörlerine yazma izni
RUN chown -R www-data:www-data /var/www/html/uploads /var/www/html/logos /var/www/html/db

EXPOSE 80
