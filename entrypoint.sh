#!/bin/sh
set -e

# Ensure we run from the app root
cd /var/www/html

# Folders needed for persistence
folder_paths="
    /var/www/html/storage/app/dictionaries
    /var/www/html/storage/app/fonts
    /var/www/html/storage/app/images/book_images
    /var/www/html/storage/app/public
    /var/www/html/storage/app/temp/dictionaries
    /var/www/html/storage/framework/cache/data
    /var/www/html/storage/framework/sessions
    /var/www/html/storage/framework/testing
    /var/www/html/storage/framework/views
    /var/www/html/storage/logs
    /var/www/html/storage/backup
"

# Ensure the folders exist
for folder_path in $folder_paths; do
    if [ ! -d "$folder_path" ]; then
        mkdir -p "$folder_path"
        echo "Folder created: $folder_path"
    else
        echo "Folder already exists: $folder_path"
    fi
done

retry_count=0

while [ $retry_count -lt 40 ] && ! php artisan migrate --force; do
    sleep 15
    retry_count=$((retry_count+1))
done

php artisan db:seed --force

exec "$@"
