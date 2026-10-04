#!/bin/sh
set -e

# The bind mount hides vendor/ from the image; install it here on first start.
# flock prevents the app and cron containers from installing at the same time.
cd /var/www/html
if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
    flock /var/www/html/.composer-install.lock sh -c \
        '[ -f vendor/autoload.php ] || composer install --no-interaction --no-progress --prefer-dist'
fi

exec "$@"
