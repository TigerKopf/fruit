#!/bin/sh

# Start PHP-FPM in the background
php-fpm --daemon

# Start NGINX in the foreground
nginx -g "daemon off;"