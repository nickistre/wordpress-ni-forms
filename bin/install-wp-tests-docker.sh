#!/usr/bin/env bash

wp db drop --allow-root --yes --path=/var/www/html

bin/install-wp-tests.sh \
    "$WORDPRESS_DB_NAME" \
    "$WORDPRESS_DB_USER" \
    "$WORDPRESS_DB_PASSWORD" \
    "$WORDPRESS_DB_HOST"
