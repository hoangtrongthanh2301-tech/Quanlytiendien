FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring xml zip \
    && apt-get purge -y --auto-remove libonig-dev libxml2-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p storage/keys storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && find storage -type d -exec chmod 755 {} \;

EXPOSE 80
