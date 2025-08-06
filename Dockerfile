# Use official PHP with Apache image
FROM php:8.2-apache

# Enable Apache rewrite module (important for routes and .htaccess files)
RUN a2enmod rewrite

# Copy your app code into the Apache server folder
COPY . /var/www/html/

# Set the working directory inside the container
WORKDIR /var/www/html
