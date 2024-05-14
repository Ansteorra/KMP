# See here for image contents: https://github.com/microsoft/vscode-dev-containers/tree/v0.191.1/containers/debian/.devcontainer/base.Dockerfile

# [Choice] Debian version: bullseye, buster, stretch
ARG VARIANT="debian"
FROM mcr.microsoft.com/vscode/devcontainers/base:0-${VARIANT}

RUN sudo apt -y install lsb-release apt-transport-https ca-certificates \
    && sudo wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/php.list

# Install PHP and server apps.
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get -y install --no-install-recommends php8.3 php8.3-bcmath php8.3-bz2 php8.3-intl php8.3-gd php8.3-mbstring php8.3-mysql php8.3-zip php8.3-dom php8.3-yaml php8.3-curl php8.3-sqlite mariadb-server apache2 nodejs npm patch wkhtmltopdf

# Xdebug.
RUN apt-get install php8.3-xdebug

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache.
RUN echo "Listen 8080" >> /etc/apache2/ports.conf && \
    a2enmod rewrite