#!/bin/sh
set -eu

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*) port=80 ;;
esac

mkdir -p /var/www/html/storage/keys /var/www/html/storage/logs /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage
find /var/www/html/storage -type d -exec chmod 755 {} \;

sed -ri "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"