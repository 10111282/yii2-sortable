FROM php:7.2.0-fpm

LABEL maintainer="10111282@smail.ru>"
LABEL description="Php-fpm"

WORKDIR /var/www/html

# make mysql-server install without password prompt
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -q -y --no-install-recommends \
        apt-utils \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libcurl4-openssl-dev \
        libpq-dev \
        libxml2-dev \
        libz-dev \
        libmemcached-dev \
        build-essential \
        redis-server \
        memcached \
        wget \
        vim \
        curl \
        git \
        apt-transport-https \
        mysql-server \
        mysql-server \
        gnupg \
        libc-client-dev \
        libkrb5-dev \
    && rm -r /var/lib/apt/lists/*

ARG XDEBUG_REMOTE_HOST
ARG XDEBUG_REMOTE_PORT

RUN pecl install xdebug-2.6.0 \
    && rm -rf /tmp/pear \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) soap curl json mbstring pdo pdo_mysql pgsql pdo_pgsql opcache xml zip \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-enable  xdebug \
    && docker-php-ext-install -j$(nproc) gd mysqli \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl && docker-php-ext-install imap \
    && echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.file_link_format=phpstorm://open?%f:%l" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_host=${XDEBUG_REMOTE_HOST}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_port=${XDEBUG_REMOTE_PORT}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_connect_back=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_autostart=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.profiler_enable_trigger=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.profiler_output_dir=/var/www/html/storage/logs/" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini


# Composer
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm -rf composer-setup.php

ENV PATH="/root/.composer/vendor/bin:${PATH}"

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY . /var/www/html

CMD ["php-fpm"]
ENTRYPOINT ["/entrypoint.sh"]
