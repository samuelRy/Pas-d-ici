# Use the official PHP + Apache image
FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install PHP extensions (modify if needed)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project into container
COPY src/ /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80 (Render uses this automatically)
EXPOSE 80

# Enable .htaccess support
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
