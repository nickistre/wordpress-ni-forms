#!/usr/bin/env bash

docker-entrypoint.sh "apache2"

ln -s /var/www/wordpress-ni-forms /var/www/html/wp-content/plugins/wordpress-ni-forms

# Setup site from environment
wp core install --allow-root \
    --url="$WORDPRESS_URL" \
    --title="$WORDPRESS_TITLE" \
    --admin_user="$WORDPRESS_ADMIN_USER" \
    --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
    --admin_email="$WORDPRESS_ADMIN_EMAIL" \
    --skip-email

wp plugin activate --allow-root \
    wordpress-ni-forms/ni-forms \
    wordpress-ni-forms/ni-forms-honeypot

exec "$@"