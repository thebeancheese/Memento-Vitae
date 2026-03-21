FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/storage/record_documents \
    && chown -R www-data:www-data /var/www/html/storage

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
