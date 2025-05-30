#!/bin/bash
# Generate a self-signed SSL certificate for local development
# Usage: ./generate_dev_ssl.sh

CERT_DIR="/etc/apache2/ssl"
DOMAIN="localhost"

sudo mkdir -p "$CERT_DIR"

sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout "$CERT_DIR/dev.key" \
  -out "$CERT_DIR/dev.crt" \
  -subj "/C=US/ST=Dev/L=Dev/O=Dev/OU=Dev/CN=$DOMAIN"

echo "Self-signed certificate generated at $CERT_DIR/dev.crt and $CERT_DIR/dev.key"
