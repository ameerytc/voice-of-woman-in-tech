# Production image for Railway: Apache + PHP 8.3 (mod_php), PDO PostgreSQL, GD for profile pictures.
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg-dev libwebp-dev postgresql-client \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd exif opcache \
 && rm -rf /var/lib/apt/lists/*

# Exactly one MPM (prefork, required by mod_php). Remove every other MPM load/conf file, then fail the build if Apache still complains.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
 && a2enmod mpm_prefork rewrite headers \
 && ls /etc/apache2/mods-enabled | grep mpm \
 && [ "$(ls /etc/apache2/mods-enabled/mpm_*.load | wc -l)" = "1" ]

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-vw.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/vw-entrypoint
WORKDIR /var/www/html
COPY . /var/www/html
RUN chmod +x /usr/local/bin/vw-entrypoint \
 && mkdir -p storage/logs storage/reports storage/backups storage/database \
 && chown -R www-data:www-data storage

ENV APP_ENV=production
EXPOSE 8080
ENTRYPOINT ["vw-entrypoint"]
CMD ["apache2-foreground"]
