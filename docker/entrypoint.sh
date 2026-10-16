#!/bin/sh
set -e
export PORT="${PORT:-8080}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf

rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

if [ "${AUTO_MIGRATE:-0}" = "1" ]; then php /var/www/html/scripts/migrate.php || exit 1; fi
exec docker-php-entrypoint "$@"
