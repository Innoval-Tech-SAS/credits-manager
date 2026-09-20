FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath intl zip opcache pcntl \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/opcache-custom.ini /usr/local/etc/php/conf.d/zz-opcache-custom.ini
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY --from=vendor /app ./

RUN mkdir -p /var/exportaciones \
    && chown -R www-data:www-data storage bootstrap/cache /var/exportaciones

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
