FROM php:8.1-fpm

RUN apt update \
    && apt install -y zlib1g-dev git libzip-dev zip \
    && docker-php-ext-install zip pdo_mysql

WORKDIR /var/www/slim_app

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN git config --global user.email "sdf" \
    && git config --global user.name "adfs"