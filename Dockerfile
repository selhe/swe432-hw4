FROM php:8.3-apache

# PostgreSQL driver for PDO + psql client (used by the entrypoint to apply schema.sql)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev postgresql-client \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/* \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

COPY public/ /var/www/html/
COPY schema.sql /app/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Render sets PORT (10000 by default); the entrypoint points Apache at it.
ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
