FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    gd \
    zip


COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN groupadd -g 1000 www && useradd -u 1000 -g www -m www -s /bin/bash

WORKDIR /var/www/

COPY --chown=www:www . /var/www/html

RUN chown -R www:www /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage \
    && chown -R www:www /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/bootstrap/cache
EXPOSE 9000

CMD ["php-fpm"]