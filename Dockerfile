FROM php:8.2-apache

# Cài PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-install \
        mysqli \
        pdo_mysql \
        mbstring \
        xml \
        zip \
    && apt-get purge -y --auto-remove \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# ==========================================================
# FIX APACHE MPM - CHỈ CHO PHÉP MỘT MPM
# ==========================================================

RUN a2dismod mpm_event mpm_worker mpm_prefork || true \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod headers

# Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html

WORKDIR /var/www/html

# Copy source code
COPY . /var/www/html/

# Quyền thư mục storage
RUN mkdir -p storage/keys storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && find storage -type d -exec chmod 755 {} \;

# Kiểm tra Apache configuration khi build
RUN apache2ctl configtest

EXPOSE 80
