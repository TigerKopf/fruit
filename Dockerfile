# Verwenden Sie ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

# Installieren Sie den MySQL PDO-Treiber für die Datenbankverbindung
RUN docker-php-ext-install pdo pdo_mysql

# Aktivieren Sie optional mod_rewrite, falls saubere URLs benötigt werden (nicht zwingend für dieses Formular)
RUN a2enmod rewrite
# Hinweis: In Ihrem Log war diese Zeile unkommentiert: RUN a2enmod rewrite
# Wenn Sie mod_rewrite benötigen, stellen Sie sicher, dass das # davor entfernt wird.

COPY . /var/www/html/

# Exponieren Sie Port 801
EXPOSE 801

# Konfigurieren Sie Apache, um auf Port 801 zu lauschen
RUN echo "Listen 801" >> /etc/apache2/ports.conf
RUN sed -i -e 's/VirtualHost \*:80/VirtualHost \*:801/' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e 's/VirtualHost _default_:80/VirtualHost _default_:801/' /etc/apache2/sites-available/default-ssl.conf

# Apache wird als Standard-CMD des Basis-Images gestartet (apache2-foreground)
CMD ["apache2-foreground"]