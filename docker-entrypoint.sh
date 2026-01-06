#!/bin/bash

# Exit on fail
set -e

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations (force is needed in production)
php artisan migrate --force

# Start Apache
exec apache2-foreground
