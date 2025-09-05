# Basis-Image: PHP 8.2 mit Apache
FROM php:8.2-apache

# Setze Arbeitsverzeichnis
WORKDIR /var/www/html

# Aktivieren von Apache-Modulen
RUN a2enmod rewrite

# Installiere PHP-Erweiterungen
RUN docker-php-ext-install pdo pdo_mysql

# Konfiguriere Apache für Port 801
RUN echo "Listen 801" >> /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:801>/' /etc/apache2/sites-available/000-default.conf

# Kopiere Anwendungscode (alle Dateien im src/)
COPY ./src /var/www/html/

# Kopiere Konfiguration (nicht öffentlich erreichbar)
COPY config/ /etc/php-app-config/

# Setze Dateirechte (optional, je nach Setup)
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Debug: Zeige Inhalte des Webverzeichnisses
RUN echo "Inhalt von /var/www/html:" && ls -al /var/www/html

# Exponiere Port 801
EXPOSE 801

# Umgebungsvariablen für Mail & DB
ENV MAIL_TO_ADDRESS="your_recipient@example.com" \
    MAIL_FROM_NAME="Kontaktformular" \
    MAIL_FROM_ADDRESS="no-reply@yourdomain.com" \
    DB_HOST="your_db_host" \
    DB_NAME="form_db" \
    DB_USER="your_db_user" \
    DB_PASS="your_db_password"

# Startkommando (Apache im Vordergrund)
CMD ["apache2-foreground"]
