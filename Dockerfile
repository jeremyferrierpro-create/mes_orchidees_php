FROM php:8.2-apache

# 1. Installation des dépendances système pour PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Activation du module de réécriture d'URL Apache
RUN a2enmod rewrite

# 3. Installation des extensions PHP (MySQL + PostgreSQL)
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql

# 4. Copie des fichiers du projet
COPY . /var/www/html/

# 5. Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]