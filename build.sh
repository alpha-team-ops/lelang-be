#!/bin/bash
set -e

# Install dependencies
composer install --optimize-autoloader --no-interaction

# Cache configurations (tapi skip view:cache karena ini API)
php artisan config:cache
php artisan event:cache
php artisan route:cache
# php artisan view:cache - skip untuk API-only backend

# Storage permissions
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R a+rw storage
