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

# Enable Apache modules and allow .htaccess overrides
RUN a2enmod rewrite \
    && printf "<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n" > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install dependencies
COPY composer.json composer.lock clear_opcache.php ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader

# Copy application source
COPY . .

# Ensure runtime directories exist and are writable by Apache
RUN mkdir -p data/sqlite \
    && chown -R www-data:www-data /var/www/html

# --- Final Stage ---
FROM php:8.2-apache

# Create a non-root user
RUN useradd -ms /bin/bash appuser

# Copy enabled modules from builder stage
COPY --from=builder /etc/apache2/mods-enabled/ /etc/apache2/mods-enabled/
COPY --from=builder /etc/apache2/conf-enabled/ /etc/apache2/conf-enabled/

WORKDIR /var/www/html

# Copy application files from the builder stage
COPY --from=builder /var/www/html .

# Set user
USER appuser

EXPOSE 80
CMD ["apache2-foreground"]
