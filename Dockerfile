FROM php:8.3-fpm-alpine AS php-base

RUN apk add --no-cache \
    bash \
    curl \
    fcgi \
    freetype \
    icu-libs \
    libjpeg-turbo \
    libwebp \
    libpng \
    libzip \
    nginx \
    oniguruma \
    supervisor \
    tzdata \
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
# Ziggy é importado a partir de vendor no build do Vite.
COPY --from=vendor /var/www/vendor /app/vendor
RUN npm run build

FROM php-base AS app

ENV APP_ENV=production
ENV APP_DEBUG=false

WORKDIR /var/www

COPY . .
COPY --from=vendor /var/www/vendor /var/www/vendor
COPY --from=assets /app/public/build /var/www/public/build

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/queue-worker.sh /usr/local/bin/queue-worker.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/queue-worker.sh \
    && rm -f /var/www/public/hot \
    && mkdir -p /run/nginx /var/lib/nginx/tmp \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisord.conf"]
