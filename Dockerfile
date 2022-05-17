FROM wordpress:php7.2-apache

VOLUME /var/www/wordpress-ni-forms

# Setup xdebug on php
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
COPY ./docker/xdebug_settiings.ini  /usr/local/etc/php/conf.d/

ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod +x /usr/local/bin/wp

ADD https://getcomposer.org/installer /tmp/composer-setup.php
RUN php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Needed for install-wp-tests
RUN apt-get update && apt-get install -y \
    subversion \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

COPY docker/custom-entrypoint.sh /usr/local/bin/

ENTRYPOINT ["custom-entrypoint.sh"]
CMD ["apache2-foreground"]
