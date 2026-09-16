FROM php:8.2-apache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod 755 /usr/local/bin/docker-entrypoint.sh

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

# Chỉ giữ 1 MPM duy nhất trong runtime: prefork
RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true \
    && find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.load' -delete \
    && find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.conf' -delete \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p storage/keys storage/logs storage/uploads \
    && chown -R www-data:www-data storage \
    && find storage -type d -exec chmod 755 {} \;

RUN apache2ctl -t \
    && apache2ctl -M 2>/dev/null | grep -q 'mpm_prefork_module' \
    && ! apache2ctl -M 2>/dev/null | grep -Eq 'mpm_(event|worker)_module'

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]