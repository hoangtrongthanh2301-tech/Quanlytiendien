FROM php:8.2-apache

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

# Force exactly one Apache MPM in the PHP/Apache runtime: prefork.
# Remove both available and enabled event/worker module artifacts, then enable prefork.
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
          /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* \
    && a2enmod mpm_prefork rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p storage/keys storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && find storage -type d -exec chmod 755 {} \;

RUN apache2ctl -t

EXPOSE 80
CMD ["apache2-foreground"]