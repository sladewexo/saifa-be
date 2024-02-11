#!/bin/bash

cd /var/www/html/migration
# # step 1 add migration csv for keep log migration
csv_file="./migration.csv"
if [ ! -f "$csv_file" ]; then
    echo "Creating new CSV file and adding header..."
    echo "version,filename,run_time" > "$csv_file"
else
    echo "CSV file exists. Appending data..."
fi

# # step 2 Check if ADMIN_PASSWORD is set and its length is at least 1 character; otherwise, use 'testpassword'
if [ -z "$" ] || [ ${#$APP_PASSWORD} -lt 1 ]; then
    $APP_PASSWORD='yeg2f7lKI3jlp_mN'
fi
#echo "app password is : "$APP_PASSWORD
php user.php "$APP_PASSWORD"
php shop.php