#!/bin/bash

cd /var/www/html/migration
# # step 1 setup migration make sure is have csv for ready to run migration.
csv_file="./migration.csv"
if [ ! -f "$csv_file" ]; then
    echo "Creating new CSV file and adding header..."
    echo "version,filename,run_time" > "$csv_file"
else
    echo "CSV file exists. Appending data..."
fi

# # step 2 run migration.
if [ -z "$APP_PASSWORD" ]; then
    export APP_PASSWORD="yeg2f7lKI3jlp_mN"
fi
#echo "APP_PASSWORD is $APP_PASSWORD ."
php user.php "$APP_PASSWORD"
php shop.php