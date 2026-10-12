# syntax=docker/dockerfile:1

# API Laravel servida pelo FrankenPHP
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions pdo_pgsql pdo_sqlite pcntl opcache intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080

CMD ["sh", "docker/iniciar.sh"]
