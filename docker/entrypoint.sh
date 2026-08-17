#!/bin/sh
set -e

echo "🚀 Starting OneSol Invoice Manager Container Initialization..."

# Wait for MySQL database container to be ready
echo "⏳ Waiting for Database host (${DB_HOST:-db}:${DB_PORT:-3306})..."
max_tries=30
count=0
until nc -z -v -w5 "${DB_HOST:-db}" "${DB_PORT:-3306}" || [ $count -eq $max_tries ]; do
    count=$((count+1))
    echo "   Database not ready yet... retry $count/$max_tries in 2 seconds"
    sleep 2
done

if [ $count -eq $max_tries ]; then
    echo "❌ ERROR: Database connection timed out after $max_tries attempts!"
    exit 1
fi

echo "✅ Database connection established!"

# Run automatic database migrations & seeders
echo "🔄 Running Database Migrations & Schema Seeders..."
php /var/www/html/migrate.php

echo "⚡ Starting PHP-FPM & Nginx via Supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
