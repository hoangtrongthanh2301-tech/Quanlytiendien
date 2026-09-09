FROM php:8.2-apache

# Cài PHP extensions cần thiết
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

# Chọn MPM đúng cho PHP Apache image: tắt event/worker và chỉ bật prefork
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers

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
RUN apache2ctl -t

EXPOSE 80

CMD ["apache2-foreground"]
