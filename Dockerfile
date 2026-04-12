FROM php:8.3-cli

# Base image already includes: dom, curl, libxml, mbstring, pdo, pdo_sqlite, sqlite3

# System dependencies for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype-dev \
        libgmp-dev \
        libmemcached-dev \
        libzstd-dev \
        liblz4-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions available via docker-php-ext-install
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        zip \
        pcntl \
        gd \
        gmp

# PECL extensions: install igbinary and msgpack first (redis and memcached depend on them)
RUN pecl install igbinary msgpack \
    && docker-php-ext-enable igbinary msgpack

# PECL extensions: compression libraries
RUN pecl install lzf zstd \
    && docker-php-ext-enable lzf zstd

# lz4 extension (not on PECL, built from source)
RUN git clone --depth 1 https://github.com/kjdev/php-ext-lz4.git /tmp/lz4 \
    && cd /tmp/lz4 \
    && phpize \
    && ./configure --with-lz4-includedir=/usr \
    && make \
    && make install \
    && docker-php-ext-enable lz4 \
    && rm -rf /tmp/lz4

# PECL extension: redis (with igbinary, msgpack, lzf, zstd, lz4 support)
RUN pecl install --configureoptions \
        'enable-redis-igbinary="yes" enable-redis-msgpack="yes" enable-redis-lzf="yes" enable-redis-zstd="yes" enable-redis-lz4="yes"' \
        redis \
    && docker-php-ext-enable redis

# PECL extension: memcached (with igbinary and msgpack support)
RUN pecl install --configureoptions \
        'enable-memcached-igbinary="yes" enable-memcached-msgpack="yes"' \
        memcached \
    && docker-php-ext-enable memcached

# PECL extension: pcov (code coverage, matching CI)
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Match CI ini setting
RUN echo "error_reporting=E_ALL" > /usr/local/etc/php/conf.d/error-reporting.ini

# Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
