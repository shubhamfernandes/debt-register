FROM php:8.3-fpm-alpine


RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip


RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN addgroup -g 1000 app && adduser -G app -g app -s /bin/sh -D app -u 1000
USER app

CMD ["php-fpm"]