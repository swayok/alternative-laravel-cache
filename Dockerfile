FROM php:8.1-fpm

WORKDIR /workspace

VOLUME ["/workspace"]

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt update -y \
    && apt install -y unzip git --no-install-recommends \
    && rm -rf /var/lib/apt/lists

RUN pecl install redis-5.3.7 \
    && docker-php-ext-enable redis

COPY composer.* ./

RUN composer install

COPY . .
