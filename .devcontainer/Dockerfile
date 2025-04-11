# See here for image contents: https://github.com/microsoft/vscode-dev-containers/tree/v0.191.1/containers/debian/.devcontainer/base.Dockerfile

# [Choice] Debian version: bullseye, buster, stretch
FROM mcr.microsoft.com/devcontainers/base:bookworm

RUN sudo apt -y install lsb-release apt-transport-https ca-certificates \
    && sudo wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list

# Install PHP and server apps.
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get -y install --no-install-recommends cron supervisor php8.3 php8.3-bcmath php8.3-bz2 php8.3-intl php8.3-gd php8.3-mbstring php8.3-mysql php8.3-zip php8.3-dom php8.3-yaml php8.3-curl php8.3-sqlite php8.3-apcu mariadb-server apache2 nodejs npm patch python3-pip ruby-full build-essential zlib1g-dev

# Xdebug.
RUN apt-get install php8.3-xdebug

RUN gem install jekyll bundler

# Install Composer.
RUN sudo curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache.
RUN echo "Listen 8080" >> /etc/apache2/ports.conf && \
    a2enmod rewrite

#config PHP 8.3 to add apcu
RUN echo "extension=apcu.so" >> /etc/php/8.3/cli/php.ini && \
    echo "extension=apcu.so" >> /etc/php/8.3/apache2/php.ini && \
    echo "apc.enable_cli=1" >> /etc/php/8.3/cli/php.ini

RUN mkdir -p /var/log/supervisor

# Copy the supervisor configuration file
COPY supervisord.conf /etc/supervisor/supervisord.conf

RUN touch /var/log/cron.log