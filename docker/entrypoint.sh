#!/bin/sh
set -e
export PORT="${PORT:-8080}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf

echo "=== MPM DEBUG START ==="
ls /etc/apache2/mods-enabled | grep -i mpm || true
grep -RnE "LoadModule +mpm_" /etc/apache2/apache2.conf /etc/apache2/conf-enabled /etc/apache2/mods-enabled /etc/apache2/sites-enabled 2>/dev/null || true

for f in $(grep -RlE "^[[:space:]]*LoadModule +mpm_" /etc/apache2/apache2.conf /etc/apache2/conf-enabled /etc/apache2/mods-enabled /etc/apache2/sites-enabled 2>/dev/null || true); do
  case "$f" in */mods-enabled/mpm_prefork.load) continue;; esac
  sed -i --follow-symlinks -E 's/^([[:space:]]*LoadModule +mpm_.*)$/# \1/' "$f"
done
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "--- after fix:"
grep -RnE "^[[:space:]]*LoadModule +mpm_" /etc/apache2/apache2.conf /etc/apache2/conf-enabled /etc/apache2/mods-enabled /etc/apache2/sites-enabled 2>/dev/null || true
apache2ctl -t 2>&1 || true
echo "=== MPM DEBUG END ==="

if [ "${AUTO_MIGRATE:-0}" = "1" ]; then php /var/www/html/scripts/migrate.php || exit 1; fi
exec docker-php-entrypoint "$@"
