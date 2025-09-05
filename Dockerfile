# Base Image: PHP 8.2 mit Apache
FROM php:8.2-apache

# Installiere benötigte PHP-Erweiterungen und einen Mail-Client
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    msmtp \
    msmtp-mta \
    && rm -rf /var/lib/apt/lists/*

# Installiere PHP-Erweiterungen
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Aktiviere Apache Rewrite Module (falls später benötigt)
RUN a2enmod rewrite

# Konfiguriere msmtp für E-Mail-Versand
# ACHTUNG: Dies ist eine Beispielkonfiguration. Ersetze die Platzhalter durch deine tatsächlichen SMTP-Daten.
# Für Produktivsysteme wird empfohlen, Environment-Variablen zu nutzen oder PHPMailer mit detaillierterer Konfiguration.
RUN echo "account default\n\
host your_smtp_host.com\n\
port 587\n\
from your_sender_email@example.com\n\
user your_smtp_username\n\
password your_smtp_password\n\
tls on\n\
tls_starttls on\n\
auth on\n\
logfile /var/log/msmtp.log" > /etc/msmtprc \
&& chmod 600 /etc/msmtprc \
&& chown www-data:www-data /etc/msmtprc

# Konfiguriere PHP, um msmtp als sendmail_path zu verwenden
RUN echo "sendmail_path = \"/usr/bin/msmtp -t\"" > /usr/local/etc/php/conf.d/msmtp.ini

# Kopiere die Anwendungsdateien in das Apache-Webroot
COPY src/ /var/www/html/

# Setze die richtigen Berechtigungen (wichtig für Apache)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponiere Port 80
EXPOSE 80

# Starte Apache (standardmäßig durch das Basisimage)
CMD ["apache2-foreground"]
