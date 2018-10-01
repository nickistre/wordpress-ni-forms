#!/usr/bin/env bash

docker-entrypoint.sh "apache2"

if [ ! -L /var/www/html/wp-content/plugins/wordpress-ni-forms ]; then
    ln -s /var/www/wordpress-ni-forms /var/www/html/wp-content/plugins/wordpress-ni-forms
fi

if ! $(wp core is-installed); then
    # Setup site from environment
    wp core install --allow-root \
        --url="$WORDPRESS_URL" \
        --title="$WORDPRESS_TITLE" \
        --admin_user="$WORDPRESS_ADMIN_USER" \
        --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" \
        --skip-email

    # Activate plugins to develop or test
    wp plugin activate --allow-root \
        wordpress-ni-forms/ni-forms \
        wordpress-ni-forms/ni-forms-honeypot

    wp post create --allow-root \
        --post_title="Test Form Post" \
        --post_type=post \
        --post_status=publish \
        --post_author="$WORDPRESS_ADMIN_USER" \
        ./wp-content/plugins/wordpress-ni-forms/docker/test-form
fi

exec "$@"