# ============================================
# ATS CV Analyzer - Production Dockerfile
# ============================================

FROM php:8.1-apache

# Install system dependencies for PDF parsing and cURL
RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    unzip \
    git \
    && docker-php-ext-install curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application source code
COPY . .

# Configure PHP for file uploads
RUN echo "upload_max_filesize = 10M\n\
post_max_size = 12M\n\
memory_limit = 256M\n\
max_execution_time = 120" > /usr/local/etc/php/conf.d/app.ini

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Run Apache in foreground
CMD ["apache2-foreground"]