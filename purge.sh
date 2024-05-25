#!/bin/bash

# Purge older items from the database.

KEYFILE='decryption_key.txt'
FILENAME='db_creds.gpg'
DB_HOST="localhost"
DB_NAME="sasquatch_index"

# Get database credentials
CREDS=`gpg --decrypt --batch --passphrase-file $KEYFILE $FILENAME`

# Database connection settings
DB_USER=`echo $CREDS | cut -d ":" -f 1`
DB_PASSWORD=`echo $CREDS | cut -d ":" -f 2`

# Run MySQL query to delete items older than 10 minutes
mariadb -h $DB_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME -e "DELETE FROM sites WHERE last_visited IS NOT NULL AND last_visited < NOW() - INTERVAL 10 MINUTE;"
