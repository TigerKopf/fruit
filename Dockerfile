# Verwenden Sie ein offizielles PHP-Image mit Apache
FROM php:8.2-apache

# Installieren Sie den MySQL PDO-Treiber für die Datenbankverbindung
RUN docker-php-ext-install pdo pdo_mysql

# Aktivieren Sie mod_rewrite für unsere Regeln
RUN a2enmod rewrite

# Kopieren Sie die individuelle Apache-Konfigurationsdatei
COPY my-custom.conf /etc/apache2/conf-available/my-custom.conf

# Aktivieren Sie Ihre individuelle Apache-Konfigurationsdatei
RUN a2enconf my-custom

# Kopieren Sie alle Webseiten-Dateien in den DocumentRoot
COPY ./src /var/www/html/

# Optional: Stellen Sie sicher, dass Ihre 404.html-Seite ebenfalls kopiert wird,
# falls sie nicht bereits im ./src-Verzeichnis enthalten ist, das oben kopiert wird.
# Wenn sie in ./src liegt, ist dieser Schritt überflüssig.
# COPY ./src/404.html /var/www/html/404.html 

# Stellen Sie sicher, dass das 'config'-Verzeichnis im DocumentRoot existiert.
# Dies ist wichtig, damit die sensitive_config.php dort platziert werden kann.
RUN mkdir -p /var/www/html/config

# Generieren Sie die sensitive_config.php während des Builds.
# Diese Datei enthält PHP-Code, der zur Laufzeit die Umgebungsvariablen ausliest.
# Die Werte werden in Coodlify in den "Environment Variables" definiert.
RUN cat << 'EOF' > /var/www/html/config/sensitive_config.php
<?php
// config/sensitive_config.php

// Diese Datei wurde automatisch während des Docker-Builds erstellt.
// Ihre Werte werden zur Laufzeit aus den Umgebungsvariablen gelesen,
// die in Coodlify gesetzt sind. Bearbeiten Sie diese Datei NICHT direkt im Container.

// --- DATENBANK-KONFIGURATION ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost'); // Standardwert für Entwicklung/Fallback
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // Wichtige Umgebungsvariable
define('DB_NAME', getenv('DB_NAME') ?: 'mydatabase');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// --- E-MAIL-KONFIGURATION (SMTP) ---
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.example.com');
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'noreply@example.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ''); // Wichtige Umgebungsvariable
define('MAIL_PORT', (int)getenv('MAIL_PORT') ?: 587); // Typ-Casting zu int
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');

// Absender-Details für E-Mails
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'noreply@example.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Your Application');

// Für den Login auf der Admin Seite
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: ''); // Wichtige Umgebungsvariable

?>
EOF

# Konfigurieren Sie Apache, um auf Port 801 zu lauschen
EXPOSE 801
RUN echo "Listen 801" >> /etc/apache2/ports.conf

# Passen Sie die Standard-VirtualHost-Konfiguration an, um auf Port 801 zu lauschen
RUN sed -i -e 's/VirtualHost \*:80/VirtualHost \*:801/' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e 's/VirtualHost _default_:80/VirtualHost _default_:801/' /etc/apache2/sites-available/default-ssl.conf

# WICHTIG: Setzen Sie AllowOverride All für den DocumentRoot, damit mod_rewrite-Regeln funktionieren
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf

# Apache wird als Standard-CMD des Basis-Images gestartet (apache2-foreground)
CMD ["apache2-foreground"]