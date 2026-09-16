#!/bin/sh
set -eu

port="${PORT:-80}"
case "$port" in
    ''|*[!0-9]*) port=80 ;;
esac

mkdir -p /var/www/html/storage/keys /var/www/html/storage/logs /var/www/html/storage/uploads
chown -R www-data:www-data /var/www/html/storage
find /var/www/html/storage -type d -exec chmod 755 {} \;

# Re-assert the single MPM at runtime in case the platform reuses an old layer.
find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.load' -delete
find /etc/apache2/mods-enabled -maxdepth 1 -type l -name 'mpm_*.conf' -delete
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

sed -ri "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

apache2ctl -t
exec "$@"