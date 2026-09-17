#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage bootstrap/cache database
chown -R www-data:www-data storage bootstrap/cache database

# SQLite — файловая БД; создаём пустой файл при первом запуске. Схему и
# демо-данные намеренно не применяем автоматически: это явный шаг в README.
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi
chown www-data:www-data database/database.sqlite

lock_hash="$(sha256sum composer.lock | cut -d ' ' -f 1)"
stored_hash=""
if [ -f vendor/.composer-lock-hash ]; then
    stored_hash="$(cat vendor/.composer-lock-hash)"
fi

if [ "$lock_hash" != "$stored_hash" ]; then
    composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts
    printf '%s\n' "$lock_hash" > vendor/.composer-lock-hash
fi

exec "$@"
