FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy your app
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Explicitly start Apache
CMD ["apache2-foreground"]