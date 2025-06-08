# Use official PHP image with Apache
FROM php:8.1-apache

# Install MySQLi extension for database connectivity
RUN docker-php-ext-install mysqli pdo_mysql

# Copy application files to Apache's web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Enable Apache rewrite module (optional, if needed for URL rewriting)
RUN a2enmod rewrite

# Update Apache configuration to use port 8080 (Render's default)
ENV APACHE_PORT 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

# Expose port 8080
EXPOSE 8080

# Start Apache
CMD ["apache2-foreground"]