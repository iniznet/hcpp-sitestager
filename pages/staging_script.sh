#!/bin/bash

# Staging Script for hcpp-sitestager
# This script creates a staging copy of an existing website

# Command line arguments
USER="$1"                # Hestia CP username
SOURCE_DOMAIN="$2"       # Original domain to copy from
STAGING_PREFIX="$3"      # Prefix for staging domain (e.g., "staging")
SOURCE_DB="$4"           # Original database name
STAGING_DB_PASS="$5"     # Generated password for staging database
CONFIG_TYPE="$6"         # Type of configuration file to update
CONFIG_PATH="$7"         # Path to the configuration file
ENV_DB_NAME_KEY="$8"     # Database name variable in .env file
ENV_DB_USER_KEY="$9"     # Database user variable in .env file
ENV_DB_PASS_KEY="${10}"  # Database password variable in .env file

# System paths
HESTIA_BIN="/usr/local/hestia/bin"
STAGING_DOMAIN="${STAGING_PREFIX}.${SOURCE_DOMAIN}"
SOURCE_PATH="/home/$USER/web/$SOURCE_DOMAIN/public_html"
STAGING_PATH="/home/$USER/web/$STAGING_DOMAIN/public_html"
DB_BACKUP_PATH="/tmp/${SOURCE_DB}.sql"

# Generate database naming with hash suffix for uniqueness
STAGING_DB_SUFFIX="stg_$(echo "$STAGING_DOMAIN" | md5sum | head -c 8)"
STAGING_DB_USER_SUFFIX="${STAGING_DB_SUFFIX}"
FULL_STAGING_DB_NAME="${USER}_${STAGING_DB_SUFFIX}"
FULL_STAGING_DB_USER="${USER}_${STAGING_DB_USER_SUFFIX}"

# Function to send notifications to the user
notify_user() {
    local topic="$1"
    local message="$2"
    $HESTIA_BIN/v-add-user-notification "$USER" "$topic" "$message"
}

# Start the staging process
notify_user "Staging Started" "Staging process for '$SOURCE_DOMAIN' has begun. A new site will be created at '$STAGING_DOMAIN'."

# Create the new web domain
if ! $HESTIA_BIN/v-add-web-domain "$USER" "$STAGING_DOMAIN"; then
    notify_user "Staging Failed" "Error: Could not create staging web domain. Check system logs."
    exit 1
fi

# Create the staging database
echo "Creating database with suffix '$STAGING_DB_SUFFIX'..."
if ! $HESTIA_BIN/v-add-database "$USER" "$STAGING_DB_SUFFIX" "$STAGING_DB_USER_SUFFIX" "$STAGING_DB_PASS" "mysql"; then
    notify_user "Staging Failed" "Error: Could not create the staging database. Check system logs."
    exit 1
fi
echo "Database '$FULL_STAGING_DB_NAME' creation requested successfully."

# Copy website files from source to staging
rsync -a --delete "$SOURCE_PATH/" "$STAGING_PATH/"
chown -R "$USER:$USER" "/home/$USER/web/$STAGING_DOMAIN"

# MySQL connection settings
DEFAULTS_FILE="/usr/local/hestia/conf/.mysql.localhost"

# Dump source database
if ! mysqldump --defaults-extra-file="$DEFAULTS_FILE" "$SOURCE_DB" > "$DB_BACKUP_PATH"; then
    notify_user "Staging Failed" "Error: Could not dump source database '$SOURCE_DB'. Check permissions or database existence."
    exit 1
fi

# Replace domain references in the SQL dump
if [ -f "$DB_BACKUP_PATH" ] && [ -r "$DB_BACKUP_PATH" ]; then
    echo "Performing URL replacement in SQL dump..."
    awk -v source="$SOURCE_DOMAIN" -v staging="$STAGING_DOMAIN" '{ gsub(source, staging); print }' "$DB_BACKUP_PATH" > "$DB_BACKUP_PATH.tmp" && mv "$DB_BACKUP_PATH.tmp" "$DB_BACKUP_PATH"
else
    notify_user "Staging Failed" "Error: Database backup file not found or not readable for URL replacement."
    exit 1
fi

# Wait for the new database to be fully created
echo "Waiting for database '$FULL_STAGING_DB_NAME' to be created..."
COUNT=0
MAX_TRIES=60
while [ $COUNT -lt $MAX_TRIES ]; do
    if mysql --defaults-extra-file="$DEFAULTS_FILE" -e "USE \`$FULL_STAGING_DB_NAME\`;" 2>/dev/null; then
        echo "Database found."
        break
    fi
    sleep 1
    COUNT=$((COUNT+1))
done

if [ $COUNT -eq $MAX_TRIES ]; then
    notify_user "Staging Failed" "Error: Timed out waiting for database '$FULL_STAGING_DB_NAME' to be created."
    exit 1
fi

# Import data into staging database
if ! mysql --defaults-extra-file="$DEFAULTS_FILE" "$FULL_STAGING_DB_NAME" < "$DB_BACKUP_PATH"; then
    notify_user "Staging Failed" "Error: Could not import SQL dump into '$FULL_STAGING_DB_NAME'."
    exit 1
fi

# Clean up the temporary SQL dump
rm -f "$DB_BACKUP_PATH"

# Update configuration files based on application type
FULL_CONFIG_PATH="/home/$USER/web/$STAGING_DOMAIN/$CONFIG_PATH"
echo "Checking for configuration file at $FULL_CONFIG_PATH"

if [ -f "$FULL_CONFIG_PATH" ]; then
    case "$CONFIG_TYPE" in
        wordpress)
            echo "Updating WordPress config (wp-config.php)..."
            sed -i "s|define( *'DB_NAME', *'.*' *);|define( 'DB_NAME', '$FULL_STAGING_DB_NAME' );|" "$FULL_CONFIG_PATH"
            sed -i "s|define( *'DB_USER', *'.*' *);|define( 'DB_USER', '$FULL_STAGING_DB_USER' );|" "$FULL_CONFIG_PATH"
            sed -i "s|define( *'DB_PASSWORD', *'.*' *);|define( 'DB_PASSWORD', '$STAGING_DB_PASS' );|" "$FULL_CONFIG_PATH"
            notify_user "Config Updated" "wp-config.php for '$STAGING_DOMAIN' has been updated with new database details."
            ;;
        env)
            echo "Updating .env file..."
            sed -i "s|^${ENV_DB_NAME_KEY}=.*|${ENV_DB_NAME_KEY}=${FULL_STAGING_DB_NAME}|" "$FULL_CONFIG_PATH"
            sed -i "s|^${ENV_DB_USER_KEY}=.*|${ENV_DB_USER_KEY}=${FULL_STAGING_DB_USER}|" "$FULL_CONFIG_PATH"
            sed -i "s|^${ENV_DB_PASS_KEY}=.*|${ENV_DB_PASS_KEY}=${STAGING_DB_PASS}|" "$FULL_CONFIG_PATH"
            notify_user "Config Updated" ".env file for '$STAGING_DOMAIN' has been updated with new database details."
            ;;
        *)
            echo "Manual configuration selected. Skipping automatic update."
            ;;
    esac
else
    echo "Configuration file not found at the specified path. Skipping automatic update."
fi

# Complete the staging process
notify_user "Staging Complete" "Successfully created a staging site for '$SOURCE_DOMAIN' at '$STAGING_DOMAIN'. New DB: '$FULL_STAGING_DB_NAME'."

exit 0