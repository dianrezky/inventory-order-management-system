FROM php:8.3.20-cli

# System libraries + cron (used by the cron service)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libfreetype6-dev \
        libcurl4-openssl-dev \
        unzip \
        git \
        cron \
    && rm -rf /var/lib/apt/lists/*

# Memcached extension (needs libsasl2/libssl for configure to succeed)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libmemcached-dev \
        libsasl2-dev \
        libssl-dev \
        zlib1g-dev \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && rm -rf /var/lib/apt/lists/*

# Redis extension (session)
RUN pecl install redis \
    && docker-php-ext-enable redis

# GD, PDO MySQL, OPcache, curl (MinIO client)
RUN docker-php-ext-configure gd --with-jpeg --with-freetype --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql opcache curl

# OPcache
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Raise CLI memory_limit for PHPStan
RUN echo 'memory_limit=512M' > /usr/local/etc/php/conf.d/memory-limit-cli.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies are copied first so the layer stays cached when code changes.
# The build fails hard if composer install fails, so the image is self-contained.
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-progress --prefer-dist

COPY . .

RUN if [ -f composer.json ]; then composer dump-autoload --optimize; fi

# Low-stock cron schedule
COPY docker/cron/low-stock.cron /etc/cron.d/low-stock
RUN chmod 0644 /etc/cron.d/low-stock

# Password-reset email schedule (every 3 minutes)
COPY docker/cron/password-reset.cron /etc/cron.d/password-reset
RUN chmod 0644 /etc/cron.d/password-reset

# Entrypoint: installs vendor/ on a clean clone
COPY docker/php/entrypoint.sh /usr/local/bin/iom-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/iom-entrypoint && chmod +x /usr/local/bin/iom-entrypoint
ENTRYPOINT ["iom-entrypoint"]

# Run as a non-root user by default (the app service). www-data ships with the
# PHP base image. The cron service overrides this back to root in docker-compose.yaml,
# because the cron daemon needs root to start.
USER www-data

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
