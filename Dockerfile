FROM php:8.2-apache
RUN a2enmod rewrite headers \
 && docker-php-ext-install pdo pdo_mysql mysqli

COPY frontend/ /var/www/html/
COPY backend/  /var/www/backend/

# Apache : /api est servi par le backend
RUN printf '%s\n' \
 '<VirtualHost *:80>' \
 '  DocumentRoot /var/www/html' \
 '  <Directory /var/www/html>' \
 '    Require all granted' \
 '  </Directory>' \
 '  Alias /api /var/www/backend/public' \
 '  <Directory /var/www/backend/public>' \
 '    Require all granted' \
 '    FallbackResource /api/index.php' \
 '  </Directory>' \
 '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Limites d'upload de PHP (sans cela : 2 Mo maximum par fichier)
RUN printf "upload_max_filesize=210M\npost_max_size=215M\n" > /usr/local/etc/php/conf.d/uploads.ini

# Dossier de stockage : même chemin que celui du backend, droits pour Apache
RUN mkdir -p /var/www/backend/storage/uploads/videos \
 && chown -R www-data:www-data /var/www/backend/storage \
 && chmod -R 750 /var/www/backend/storage

WORKDIR /var/www/html/
EXPOSE 80
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf