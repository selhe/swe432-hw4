#!/bin/sh
set -e

# 1. Make Apache listen on the port Render expects
: "${PORT:=10000}"
sed -i "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# 2. Apply the schema (idempotent: CREATE TABLE IF NOT EXISTS), retrying while the DB starts up
if [ -n "$DATABASE_URL" ]; then
  i=0
  until psql "$DATABASE_URL" -q -1 -v ON_ERROR_STOP=1 -f /app/schema.sql; do
    i=$((i + 1))
    if [ "$i" -ge 15 ]; then echo "ERROR: could not apply schema.sql" >&2; exit 1; fi
    echo "Schema not applied (attempt $i); retrying in 3s..." >&2
    sleep 3
  done
else
  echo "WARNING: DATABASE_URL is not set" >&2
fi

exec "$@"
