#!/bin/bash
# Validation script to check if all tools are properly installed in the container

echo "=== Container Build Validation ==="
echo "Checking if all tools are properly installed..."

# Function to check if a command exists
check_command() {
    if command -v "$1" &> /dev/null; then
        echo "✅ $1 is installed"
        if [ "$2" != "no-version" ]; then
            $1 --version 2>/dev/null | head -1 || echo "   (version info not available)"
        fi
    else
        echo "❌ $1 is NOT installed"
        return 1
    fi
}

# Function to check if a file exists
check_file() {
    if [ -f "$1" ]; then
        echo "✅ $1 exists"
    else
        echo "❌ $1 does NOT exist"
        return 1
    fi
}

echo ""
echo "--- Core Development Tools ---"
check_command "php"
check_command "composer"
check_command "node"
check_command "npm"
check_command "go"
check_command "java"
check_command "python"

echo ""
echo "--- Security Tools ---"
check_command "zap" "no-version"
check_command "dependency-check" "no-version"
check_command "nikto" "no-version"
check_command "sqlmap" "no-version"
check_command "local-php-security-checker" "no-version"
check_command "nuclei"

echo ""
echo "--- Development Tools ---"
check_command "phpunit"
check_command "mailpit"
check_command "mermerd" "no-version"
check_command "playwright" "no-version"

echo ""
echo "--- Configuration Files ---"
check_file "/etc/php/8.3/cli/conf.d/20-xdebug.ini"
check_file "/opt/templates/apache-vhost.template"
check_file "/etc/init.d/mailpit"

echo ""
echo "--- Services ---"
if systemctl is-active --quiet apache2 2>/dev/null || service apache2 status >/dev/null 2>&1; then
    echo "✅ Apache service is available"
else
    echo "⚠️  Apache service status unknown (normal in build)"
fi

if systemctl is-active --quiet mariadb 2>/dev/null || service mariadb status >/dev/null 2>&1; then
    echo "✅ MariaDB service is available"
else
    echo "⚠️  MariaDB service status unknown (normal in build)"
fi

echo ""
echo "--- PHP Extensions ---"
php -m | grep -E "(xdebug|apcu|mysql)" | while read ext; do
    echo "✅ PHP extension: $ext"
done

echo ""
echo "--- Environment Setup ---"
if [ -n "$JAVA_HOME" ]; then
    echo "✅ JAVA_HOME is set: $JAVA_HOME"
else
    echo "⚠️  JAVA_HOME not set in current session"
fi

if echo "$PATH" | grep -q "/usr/local/go/bin"; then
    echo "✅ Go is in PATH"
else
    echo "⚠️  Go not in current PATH"
fi

echo ""
echo "=== Validation Complete ==="
