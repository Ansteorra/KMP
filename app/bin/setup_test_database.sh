#!/bin/bash

# Setup Test Database Script
# This script recreates the test database with the correct schema from dev

set -e

# Load environment variables
source /workspaces/KMP/app/config/.env

# Database names
DEV_DB="${MYSQL_DB_NAME}"
TEST_DB="${MYSQL_DB_NAME}_test"

echo "Setting up test database: ${TEST_DB}"

# Drop and recreate test database
echo "Dropping existing test database..."
mysql -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} -e "DROP DATABASE IF EXISTS ${TEST_DB};"

echo "Creating test database..."
mysql -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} -e "CREATE DATABASE ${TEST_DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema from dev database
echo "Importing schema from dev database..."
mysqldump -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} --no-data ${DEV_DB} | mysql -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} ${TEST_DB}

# Verify critical columns exist
echo ""
echo "Verifying gatherings table schema..."
DELETED_EXISTS=$(mysql -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} ${TEST_DB} -e "SHOW COLUMNS FROM gatherings LIKE 'deleted';" | wc -l)
MODIFIED_BY_EXISTS=$(mysql -u ${MYSQL_USERNAME} -p${MYSQL_PASSWORD} ${TEST_DB} -e "SHOW COLUMNS FROM gatherings LIKE 'modified_by';" | wc -l)

if [ $DELETED_EXISTS -lt 2 ]; then
    echo "ERROR: 'deleted' column missing from gatherings table!"
    exit 1
fi

if [ $MODIFIED_BY_EXISTS -lt 2 ]; then
    echo "ERROR: 'modified_by' column missing from gatherings table!"
    exit 1
fi

echo "✓ Schema verified: deleted and modified_by columns present"
echo ""
echo "Test database setup complete!"
echo "Database: ${TEST_DB}"
echo "Tables imported from: ${DEV_DB}"
