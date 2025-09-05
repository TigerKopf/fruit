# Verwenden Sie ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

RUN ls && pwd

# Kopieren Sie den Anwendungscode in das Web-Root des Containers
COPY src/ /var/www/html/

# Kopieren Sie Konfigurationsdateien in einen nicht direkt über das Web zugänglichen Ort
# Diese werden dann von den PHP-Skripten mit require_once eingebunden.
COPY config/ /etc/php-app-config/

# Installieren Sie den MySQL PDO-Treiber für die Datenbankverbindung
RUN docker-php-ext-install pdo pdo_mysql

# Aktivieren Sie optional mod_rewrite, falls saubere URLs benötigt werden (nicht zwingend für dieses Formular)
RUN a2enmod rewrite

# Exponieren Sie Port 801
EXPOSE 801

# Konfigurieren Sie Apache, um auf Port 801 zu lauschen
RUN echo "Listen 801" >> /etc/apache2/ports.conf
RUN sed -i -e 's/VirtualHost \*:80/VirtualHost \*:801/' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e 's/VirtualHost _default_:80/VirtualHost _default_:801/' /etc/apache2/sites-available/default-ssl.conf

# Setzen Sie Umgebungsvariablen für E-Mail und Datenbank.
# Passen Sie diese Werte an Ihre tatsächlichen Konfigurationen an!
ENV MAIL_TO_ADDRESS="your_recipient@example.com" \
    MAIL_FROM_NAME="Kontaktformular" \
    MAIL_FROM_ADDRESS="no-reply@yourdomain.com" \
    DB_HOST="your_db_host" \
    DB_NAME="form_db" \
    DB_USER="your_db_user" \
    DB_PASS="your_db_password"

# Apache wird als Standard-CMD des Basis-Images gestartet (apache2-foreground)
