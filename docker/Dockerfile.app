# KMP Application Container - PHP 8.3 + Apache
# Slimmed version of .devcontainer/Dockerfile for multi-container setup
#
# This container expects:
#   - PostgreSQL provided by separate 'db' service
#   - Code mounted at /var/www/html
#   - Environment variables for configuration

FROM php:8.3-apache-bookworm

ARG NODE_VERSION=24.15.0

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.gpg] http://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
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
    xz-utils \
    default-mysql-client \
    libpq-dev \
    postgresql-client-16 \
    cron \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

# Install Node.js from the official distribution to pin the exact version used by Vite.
RUN ARCH="$(dpkg --print-architecture)" && \
    case "$ARCH" in \
        amd64) NODE_ARCH='x64' ;; \
        arm64) NODE_ARCH='arm64' ;; \
        *) echo "Unsupported architecture for Node.js: $ARCH"; exit 1 ;; \
    esac && \
    curl -fsSLO "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" && \
    tar -xJf "node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" -C /usr/local --strip-components=1 --no-same-owner && \
    rm "node-v${NODE_VERSION}-linux-${NODE_ARCH}.tar.xz" && \
    node --version && \
    npm --version

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        bz2 \
        intl \
        gd \
        mysqli \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
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
COPY docker/scheduler-loop.sh /usr/local/bin/kmp-scheduler-loop
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/kmp-scheduler-loop

# Expose port 80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
