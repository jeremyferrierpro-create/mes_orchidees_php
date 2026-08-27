FROM php:8.2-apache

# 1. Activation du module de réécriture d'URL
RUN a2enmod rewrite

# 2. Installation des extensions PHP courantes
RUN docker-php-ext-install pdo pdo_mysql

# 3. Copie des fichiers du projet dans le serveur Apache
COPY . /var/www/html/

# 4. Permissions pour le serveur web
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]