#!/bin/sh
# Railway injects $PORT; Apache must listen on it (never use the PHP built-in server in production).
set -e
export PORT="${PORT:-8080}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf
# Safety net: make sure only prefork is loaded
for m in event worker; do rm -f /etc/apache2/mods-enabled/mpm_$m.load /etc/apache2/mods-enabled/mpm_$m.conf; done
# Optional: run migrations on every start (the recommended way is Railway's pre-deploy command: php scripts/migrate.php)
if [ "${AUTO_MIGRATE:-0}" = "1" ]; then php /var/www/html/scripts/migrate.php || exit 1; fi
exec docker-php-entrypoint "$@"
