# Verwenden Sie ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

COPY ./src_meins /var/www/html/

# Installieren Sie den MySQL PDO-Treiber für die Datenbankverbindung
RUN docker-php-ext-install pdo pdo_mysql

# Aktivieren Sie optional mod_rewrite, falls saubere URLs benötigt werden (nicht zwingend für dieses Formular)
RUN a2enmod rewrite
# Hinweis: In Ihrem Log war diese Zeile unkommentiert: RUN a2enmod rewrite
# Wenn Sie mod_rewrite benötigen, stellen Sie sicher, dass das # davor entfernt wird.

# Exponieren Sie Port 801
EXPOSE 801