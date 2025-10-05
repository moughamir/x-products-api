# syntax=docker/dockerfile:1.4

# --- Build Stage ---
FROM php:8.2-apache AS builder

# Install build dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache to serve from /var/www/html as root
# Your app will be in /var/www/html/cosmos
RUN printf "<Directory /var/www/html>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n" > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# Create the cosmos subdirectory structure
WORKDIR /var/www/html/cosmos

# Install dependencies
COPY composer.json composer.lock clear_opcache.php ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader

# Copy application source
COPY . .

# Ensure runtime directories exist and are writable by Apache
RUN mkdir -p data/sqlite \
    && chown -R www-data:www-data /var/www/html/cosmos

# --- Final Stage ---
FROM php:8.2-apache

# Copy enabled modules and Apache config from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /etc/apache2/mods-enabled/ /etc/apache2/mods-enabled/
COPY --from=builder /etc/apache2/conf-enabled/ /etc/apache2/conf-enabled/

WORKDIR /var/www/html/cosmos

# Copy application files from the builder stage
COPY --from=builder /var/www/html/cosmos /var/www/html/cosmos

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/cosmos

# Switch to www-data user
USER www-data

EXPOSE 80
CMD ["apache2-foreground"]
