# See here for image contents: https://github.com/microsoft/vscode-dev-containers/tree/v0.191.1/containers/debian/.devcontainer/base.Dockerfile

# [Choice] Debian version: bullseye, buster, stretch
FROM mcr.microsoft.com/devcontainers/base:bookworm

RUN apt-get update && \
    apt-get install -y socat && \
    rm -rf /var/lib/apt/lists/*

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

RUN curl -sSL https://github.com/zaproxy/zaproxy/releases/download/w2025-05-20/ZAP_WEEKLY_D-2025-05-20.zip -o /tmp/zaproxy.zip && \
    unzip /tmp/zaproxy.zip -d /opt && \
    rm /tmp/zaproxy.zip && \
    ln -s /opt/ZAP_D-2025-05-20/zap.sh /usr/local/bin/zap

#RUN  apt-get install -y default-jdk
#RUN  export JAVA_HOME=$(readlink -f /usr/bin/java | sed "s:/bin/java::")
#RUN  echo "JAVA_HOME=$JAVA_HOME" >> /etc/environment
#RUN  echo "export JAVA_HOME=$JAVA_HOME" >> ~/.bashrc

RUN mkdir -p /opt/dependency-check && \
    curl -sSL https://github.com/jeremylong/DependencyCheck/releases/download/v8.2.1/dependency-check-8.2.1-release.zip -o /tmp/dependency-check.zip && \
    unzip /tmp/dependency-check.zip -d /opt && \
    rm /tmp/dependency-check.zip && \
    ln -s /opt/dependency-check/bin/dependency-check.sh /usr/local/bin/dependency-check

RUN apt-get update && \
    git clone https://github.com/sullo/nikto.git /opt/nikto && \
    ln -s /opt/nikto/program/nikto.pl /usr/local/bin/nikto && \
    chmod +x /usr/local/bin/nikto && \
    apt-get install -y libnet-ssleay-perl perl-modules && \
    apt-get install -y libwhisker2-perl

RUN apt-get update && \
    apt-get install -y git python3-pip && \
    git clone --depth 1 https://github.com/sqlmapproject/sqlmap.git /opt/sqlmap && \
    ln -s /opt/sqlmap/sqlmap.py /usr/local/bin/sqlmap && \
    chmod +x /usr/local/bin/sqlmap

RUN curl -sSL https://github.com/fabpot/local-php-security-checker/releases/download/v2.0.6/local-php-security-checker_2.0.6_linux_amd64 -o /usr/local/bin/local-php-security-checker && \
    chmod +x /usr/local/bin/local-php-security-checker

RUN curl -sSL https://github.com/projectdiscovery/nuclei/releases/download/v3.4.4/nuclei_3.4.4_linux_arm64.zip -o /tmp/nuclei.zip && \
    unzip /tmp/nuclei.zip -d /tmp && \
    mv /tmp/nuclei /usr/local/bin/ && \
    chmod +x /usr/local/bin/nuclei && \
    rm /tmp/nuclei.zip

# Add python3 to python alias for compatibility
RUN ln -s /usr/bin/python3 /usr/local/bin/python && \
    python --version