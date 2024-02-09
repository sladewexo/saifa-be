#!/bin/bash
# Run initialization commands

cat
cd /var/www/html/migration

# add migration csv for keep log migration
csv_file="./migration.csv"
if [ ! -f "$csv_file" ]; then
    echo "Creating new CSV file and adding header..."
    echo "version,filename,run_time" > "$csv_file"
else
    echo "CSV file exists. Appending data..."
fi

php user.php devpassword
php shop.php

# Execute the command specified by CMD
exec "$@"
