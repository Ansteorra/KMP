#!/bin/sh
set -eu

# Refresh all inherited native libraries before compiling extensions against them.
# Preserve Docker's existing Apache configuration when a security update changes conffiles.
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get -y --with-new-pkgs --no-install-recommends -o Dpkg::Options::=--force-confold upgrade
. /etc/os-release
case "${VERSION_CODENAME:-}" in
    bookworm|trixie) ;;
    *) echo 'Unsupported Debian runtime release' >&2; exit 1 ;;
esac

install-php-extensions \
        bcmath \
        bz2 \
        gd \
        grpc-1.82.0 \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        protobuf-5.36.1 \
        redis-6.3.0 \
        zip \
        apcu-5.1.28 \
        yaml-2.3.0 \
    && apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-mysql-client \
        cron \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl --fail --silent --show-error \
        -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
        https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo '0144068502a1eddd2a0280ede10ef607d1ec592ce819940991203941564e8e76  /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc' | sha256sum --check \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        postgresql-client-16 \
    && rm -rf /var/lib/apt/lists/*

mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

{ \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.interned_strings_buffer=16'; \
    } > "$PHP_INI_DIR/conf.d/opcache-prod.ini"

echo 'apc.enable_cli=1' > "$PHP_INI_DIR/conf.d/apcu.ini"

{ \
        echo 'upload_max_filesize = 5M'; \
        echo 'post_max_size = 20M'; \
    } > "$PHP_INI_DIR/conf.d/uploads.ini" \
    && echo 'memory_limit = 256M' > "$PHP_INI_DIR/conf.d/memory.ini"

echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/security.ini"

a2enmod rewrite headers

echo "ServerName localhost" >> /etc/apache2/apache2.conf

{ \
        echo '<VirtualHost *:80>'; \
        echo '    ServerAdmin webmaster@localhost'; \
        echo '    DocumentRoot /var/www/html/webroot'; \
        echo ''; \
        echo '    <Directory /var/www/html/webroot>'; \
        echo '        Options FollowSymLinks'; \
        echo '        AllowOverride All'; \
        echo '        DirectoryIndex index.php'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo ''; \
        echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
        echo '    LogFormat "%h %l %u %t \"%m\" %>s %b %D" kmp_private'; \
        echo '    CustomLog ${APACHE_LOG_DIR}/access.log kmp_private'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

# Discover installed compiler/header packages instead of assuming one GCC version.
# Extension installation marks its shared runtime libraries as manually required.
dpkg-query -W -f='${db:Status-Abbrev} ${binary:Package}\n' \
    | awk '$1 == "ii" {print $2}' \
    | sed 's/:[^:]*$//' \
    | awk '/-dev$/ || /^(autoconf|automake|dpkg-dev|make|pkg-config|pkgconf|re2c|linux-libc-dev)$/ \
        || /^(gcc|g\+\+|cpp|binutils)(-[0-9]+)?(-[a-z0-9-]+)?$/' \
    | awk '!/^gcc-[0-9]+-base$/' \
    | sort -u \
    | xargs -r apt-get purge -y --auto-remove
rm -rf /var/lib/apt/lists/*
php -r 'if (version_compare(PHP_VERSION, "8.4.24", "<") || !extension_loaded("gd")) { exit(1); }'
