# See here for image contents: https://github.com/microsoft/vscode-dev-containers/tree/v0.191.1/containers/debian/.devcontainer/base.Dockerfile
# This Dockerfile has been optimized to move time-consuming operations from the post-start script
# to the build process, significantly reducing container startup time.
# Run validate_build.sh after build to verify all tools are installed.

# [Choice] Debian version: bullseye, buster, stretch
FROM mcr.microsoft.com/devcontainers/base:bookworm

# Install system packages and PHP repository
RUN apt-get update && \
    apt-get install -y socat lsb-release apt-transport-https ca-certificates && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list && \
    rm -rf /var/lib/apt/lists/*

# Install PHP, server apps, and Java
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive && \
    apt-get -y install --no-install-recommends \
    cron supervisor \
    php8.3 php8.3-bcmath php8.3-bz2 php8.3-intl php8.3-gd php8.3-mbstring \
    php8.3-mysql php8.3-zip php8.3-dom php8.3-yaml php8.3-curl php8.3-sqlite \
    php8.3-apcu php8.3-xdebug \
    mariadb-server apache2 nodejs npm \
    patch python3-pip ruby-full build-essential zlib1g-dev \
    default-jdk libnet-ssleay-perl perl-modules libwhisker2-perl git && \
    rm -rf /var/lib/apt/lists/*

# Configure Java
RUN export JAVA_HOME=$(readlink -f /usr/bin/java | sed "s:/bin/java::") && \
    echo "JAVA_HOME=$JAVA_HOME" >> /etc/environment && \
    echo "export JAVA_HOME=$JAVA_HOME" >> /etc/bash.bashrc

# Install Ruby gems
RUN gem install jekyll bundler

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache
RUN echo "Listen 8080" >> /etc/apache2/ports.conf && \
    a2enmod rewrite

# Configure PHP 8.3 (APCu and assertions)
RUN echo "extension=apcu.so" >> /etc/php/8.3/cli/php.ini && \
    echo "extension=apcu.so" >> /etc/php/8.3/apache2/php.ini && \
    echo "apc.enable_cli=1" >> /etc/php/8.3/cli/php.ini && \
    echo "zend.assertions=1" >> /etc/php/8.3/apache2/php.ini && \
    echo "zend.assertions=1" >> /etc/php/8.3/cli/php.ini

# Copy and configure Xdebug
COPY init_env/20-xdebug.ini /etc/php/8.3/cli/conf.d/20-xdebug.ini

# Set up supervisor and logging
RUN mkdir -p /var/log/supervisor && \
    touch /var/log/cron.log && \
    chmod 644 /var/log/cron.log

# Copy supervisor configuration
COPY supervisord.conf /etc/supervisor/supervisord.conf

# Install Go and mermerd
RUN cd /tmp && \
    ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then \
    GO_PACKAGE="go1.22.0.linux-amd64.tar.gz"; \
    elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then \
    GO_PACKAGE="go1.22.0.linux-arm64.tar.gz"; \
    else \
    echo "Unsupported architecture: $ARCH"; exit 1; \
    fi && \
    wget "https://go.dev/dl/$GO_PACKAGE" && \
    tar -C /usr/local -xzf "$GO_PACKAGE" && \
    rm "$GO_PACKAGE"

# Add Go to PATH and install mermerd
ENV PATH="/usr/local/go/bin:${PATH}"
RUN /usr/local/go/bin/go install github.com/KarnerTh/mermerd@latest

# Install Mailpit
# ...existing code...

# Install Mailpit - Fix the shell syntax issue
RUN curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash

# ...existing code...
# Copy Mailpit init script and set up service
COPY init_env/mailpit.init.d /etc/init.d/mailpit
RUN chmod +x /etc/init.d/mailpit && \
    update-rc.d mailpit defaults

# Install PHPUnit
RUN wget -O /usr/local/bin/phpunit https://phar.phpunit.de/phpunit-10.phar && \
    chmod +x /usr/local/bin/phpunit

# Install Playwright system dependencies
RUN npm install -g playwright && \
    npx playwright install-deps

# Create template directory for Apache virtual host configuration
RUN mkdir -p /opt/templates

# Copy the Apache virtual host template
COPY init_env/apache-vhost.template /opt/templates/apache-vhost.template

# Install security tools
RUN curl -sSL https://github.com/zaproxy/zaproxy/releases/download/w2025-05-20/ZAP_WEEKLY_D-2025-05-20.zip -o /tmp/zaproxy.zip && \
    unzip /tmp/zaproxy.zip -d /opt && \
    rm /tmp/zaproxy.zip && \
    ln -s /opt/ZAP_D-2025-05-20/zap.sh /usr/local/bin/zap

RUN mkdir -p /opt/dependency-check && \
    curl -sSL https://github.com/jeremylong/DependencyCheck/releases/download/v8.2.1/dependency-check-8.2.1-release.zip -o /tmp/dependency-check.zip && \
    unzip /tmp/dependency-check.zip -d /opt && \
    rm /tmp/dependency-check.zip && \
    ln -s /opt/dependency-check/bin/dependency-check.sh /usr/local/bin/dependency-check

RUN git clone https://github.com/sullo/nikto.git /opt/nikto && \
    ln -s /opt/nikto/program/nikto.pl /usr/local/bin/nikto && \
    chmod +x /usr/local/bin/nikto

RUN git clone --depth 1 https://github.com/sqlmapproject/sqlmap.git /opt/sqlmap && \
    ln -s /opt/sqlmap/sqlmap.py /usr/local/bin/sqlmap && \
    chmod +x /usr/local/bin/sqlmap

# Install PHP Security Checker (architecture-aware)
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then \
    CHECKER_ARCH="amd64"; \
    elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then \
    CHECKER_ARCH="arm64"; \
    else \
    echo "Unsupported architecture for PHP Security Checker: $ARCH"; exit 1; \
    fi && \
    curl -sSL "https://github.com/fabpot/local-php-security-checker/releases/download/v2.0.6/local-php-security-checker_2.0.6_linux_${CHECKER_ARCH}" -o /usr/local/bin/local-php-security-checker && \
    chmod +x /usr/local/bin/local-php-security-checker

# Install Nuclei (architecture-aware)
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then \
    NUCLEI_ARCH="amd64"; \
    elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then \
    NUCLEI_ARCH="arm64"; \
    else \
    echo "Unsupported architecture for Nuclei: $ARCH"; exit 1; \
    fi && \
    curl -sSL "https://github.com/projectdiscovery/nuclei/releases/download/v3.4.4/nuclei_3.4.4_linux_${NUCLEI_ARCH}.zip" -o /tmp/nuclei.zip && \
    unzip /tmp/nuclei.zip -d /tmp && \
    mv /tmp/nuclei /usr/local/bin/ && \
    chmod +x /usr/local/bin/nuclei && \
    rm /tmp/nuclei.zip

# Add Python alias for compatibility
RUN ln -s /usr/bin/python3 /usr/local/bin/python

# Set up environment for vscode user
USER vscode

# Add Go and environment setup to vscode user's bashrc
RUN echo 'export PATH=$PATH:/usr/local/go/bin' >> ~/.bashrc && \
    echo 'export GOPATH=$HOME/go' >> ~/.bashrc && \
    echo 'export PATH=$PATH:$GOPATH/bin' >> ~/.bashrc

# Switch back to root for final setup
USER root

# Create directory for mermerd config
RUN mkdir -p /home/vscode && chown vscode:vscode /home/vscode