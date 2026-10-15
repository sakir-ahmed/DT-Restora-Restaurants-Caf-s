FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor icu-dev libzip-dev zip libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev mysql-client curl git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql bcmath intl zip gd pcntl exif \
    && apk add --no-cache --virtual .pecl-build $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .pecl-build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist || true

COPY . .
RUN composer dump-autoload --optimize || true

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
