#!/bin/sh

# Starten Sie PHP-FPM im Hintergrund
php-fpm -D

# Starten Sie NGINX im Vordergrund
nginx -g 'daemon off;'