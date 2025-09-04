# Offizielles PHP-FPM Image als Basis
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Installieren Sie hier benötigte PHP-Erweiterungen
# Beispiel: RUN docker-php-ext-install pdo pdo_mysql

# Installiert Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Kopiert Abhängigkeitsdateien und installiert sie
COPY ./web/composer.json ./web/composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Kopiert den Rest des Anwendungscodes
COPY ./web .

# Setzt die korrekten Berechtigungen
RUN chown -R www-data:www-data /var/www/html