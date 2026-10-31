FROM php:8.2-apache
RUN a2enmod rewrite headers \
 && docker-php-ext-install pdo pdo_mysql mysqli

COPY frontend/ /var/www/html/
COPY backend/  /var/www/backend/
# Apache config that sends /api/* to the backend entry point
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/storage && chown -R www-data:www-data /var/www/storage
# Set working directory
WORKDIR /var/www/html/

# Expose port 80 (Render uses this automatically)
EXPOSE 80

# Enable .htaccess support
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
