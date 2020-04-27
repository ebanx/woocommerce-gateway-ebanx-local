FROM wordpress:5.4.0-php7.2-apache

ARG WORDPRESS_DB_USER=root
ARG WORDPRESS_DB_PASSWORD=root
ARG WORDPRESS_DB_NAME=wordpress
ARG WORDPRESS_DB_HOST=mysql

WORKDIR /var/www/html

# install the PHP extensions we need
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      vim  \
      git \
      unzip

# Download WP-CLI, install and configure Wordpress
RUN curl -O "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar" && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp && \
    wp config create --allow-root --path=/usr/src/wordpress --dbname=$WORDPRESS_DB_NAME --dbuser=$WORDPRESS_DB_USER --dbpass=$WORDPRESS_DB_PASSWORD --dbhost=$WORDPRESS_DB_HOST --force --skip-check --extra-php="define( 'WP_DEBUG', true );define( 'WP_DEBUG_LOG', true );define( 'FS_METHOD', 'direct' );";

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

RUN pecl install xdebug && \
    echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/xdebug.ini && \
    echo "xdebug.remote_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini

RUN echo "upload_max_filesize=512M" > /usr/local/etc/php/conf.d/upload_max_filesize.ini

COPY wait-for-it.sh /usr/local/bin/
COPY entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/wait-for-it.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
