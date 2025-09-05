# Verwenden Sie ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

# Installieren Sie den MySQL PDO-Treiber für die Datenbankverbindung
RUN docker-php-ext-install pdo pdo_mysql

# Aktivieren Sie optional mod_rewrite, falls saubere URLs benötigt werden (nicht zwingend für dieses Formular)
# Hinweis: In Ihrem Log war diese Zeile auskommentiert: RUN a2enmod rewrite
# Wenn Sie mod_rewrite benötigen, stellen Sie sicher, dass das # davor entfernt wird.
# => WICHTIG: mod_rewrite für unsere Regeln aktivieren
RUN a2enmod rewrite

# Kopieren Sie die individuelle Apache-Konfigurationsdatei
# Diese wird in das Verzeichnis für verfügbare Konfigurationen kopiert.
COPY my-custom.conf /etc/apache2/conf-available/my-custom.conf

# Aktivieren Sie Ihre individuelle Apache-Konfigurationsdatei
RUN a2enconf my-custom

# Kopieren Sie alle Webseiten-Dateien in den DocumentRoot
COPY ./src_meins /var/www/html/

# Stellen Sie sicher, dass Ihre 404.html-Seite ebenfalls kopiert wird
# (Gehe davon aus, dass sie sich in ./src_meins befindet)
COPY ./src_meins/404.html /var/www/html/404.html

# Konfigurieren Sie Apache, um auf Port 801 zu lauschen
EXPOSE 801
RUN echo "Listen 801" >> /etc/apache2/ports.conf

# Passen Sie die Standard-VirtualHost-Konfiguration an, um auf Port 801 zu lauschen
RUN sed -i -e 's/VirtualHost \*:80/VirtualHost \*:801/' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e 's/VirtualHost _default_:80/VirtualHost _default_:801/' /etc/apache2/sites-available/default-ssl.conf

# WICHTIG: Setzen Sie AllowOverride All für den DocumentRoot, damit mod_rewrite-Regeln funktionieren
# Die Standardkonfiguration setzt oft "AllowOverride None".
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf

# Apache wird als Standard-CMD des Basis-Images gestartet (apache2-foreground)
CMD ["apache2-foreground"]