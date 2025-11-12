# syntax=docker/dockerfile:1.5

FROM php:8.2-cli

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1
ENV DEBIAN_FRONTEND=noninteractive


RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        sqlite3 \
        libsqlite3-dev \
        pkg-config \
        curl \
        autoconf \
        automake \
        libtool \
        m4 \
        gettext \
        perl \
        build-essential \
        gcc \
        g++ \
        make \
        zlib1g-dev \
        libxml2-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_sqlite zip; \
    rm -rf /var/lib/apt/lists/*

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Xdebug for coverage
RUN set -eux; \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    { \
        echo "zend_extension=$(php-config --extension-dir)/xdebug.so"; \
        echo "xdebug.mode=coverage"; \
        echo "xdebug.start_with_request=off"; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /workspace

COPY composer.json composer.lock* ./

# Install dependencies (no-autoload to defer autoloader until full copy)
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist || true

# Copy the rest of the application
COPY . .

# Create optimized autoload
RUN composer dump-autoload --optimize || true

CMD ["sleep", "infinity"]
