#!/bin/bash

# Exit on fail
set -e

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations and seed data
php artisan migrate --force
php artisan db:seed --force

# Start Apache
exec apache2-foreground
