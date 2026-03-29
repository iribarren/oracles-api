FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql intl zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction || true

# Copy the rest of the application
COPY . .
RUN composer dump-autoload --optimize || true

# Set correct permissions for Symfony's var/ directory
RUN chown -R www-data:www-data /var/www/backend/var || true
