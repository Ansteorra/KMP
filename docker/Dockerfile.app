# KMP Application Container - PHP 8.3 + Apache
# Slimmed version of .devcontainer/Dockerfile for multi-container setup
#
# This container expects:
#   - MySQL provided by separate 'db' service
#   - Code mounted at /var/www/html
#   - Environment variables for configuration

FROM php:8.3-apache-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    # PHP build dependencies
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libbz2-dev \
    libyaml-dev \
    libcurl4-openssl-dev \
    # Tools
    curl \
    git \
    unzip \
    nodejs \
    npm \
    default-mysql-client \
    cron \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        bz2 \
        intl \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        opcache

# Install PECL extensions
RUN pecl install apcu yaml xdebug \
    && docker-php-ext-enable apcu yaml xdebug

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo "apc.enable_cli=1" >> "$PHP_INI_DIR/conf.d/apcu.ini" \
    && echo "upload_max_filesize = 5M" >> "$PHP_INI_DIR/conf.d/uploads.ini" \
    && echo "post_max_size = 20M" >> "$PHP_INI_DIR/conf.d/uploads.ini" \
    && echo "memory_limit = 256M" >> "$PHP_INI_DIR/conf.d/memory.ini"

# Configure Xdebug for development
RUN echo "xdebug.mode=debug,develop" >> "$PHP_INI_DIR/conf.d/xdebug.ini" \
    && echo "xdebug.start_with_request=trigger" >> "$PHP_INI_DIR/conf.d/xdebug.ini" \
    && echo "xdebug.client_host=host.docker.internal" >> "$PHP_INI_DIR/conf.d/xdebug.ini" \
    && echo "xdebug.client_port=9003" >> "$PHP_INI_DIR/conf.d/xdebug.ini"

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHPUnit
RUN curl -sSL https://phar.phpunit.de/phpunit-10.phar -o /usr/local/bin/phpunit \
    && chmod +x /usr/local/bin/phpunit

# Set working directory
WORKDIR /var/www/html

# Copy Docker-specific app_local.php
RUN mkdir -p /opt/docker
COPY docker/app_local.php /opt/docker/app_local.php

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
