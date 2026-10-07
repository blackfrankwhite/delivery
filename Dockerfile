FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY index.html vite.config.js ./
COPY resources ./resources
ENV VITE_APP_TIMEZONE=Asia/Tbilisi
RUN npm run build

FROM php:8.4-apache-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libicu-dev libonig-dev libzip-dev unzip ca-certificates \
    && docker-php-ext-install -j2 pdo_pgsql pdo_mysql intl mbstring zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/dist/index.html ./public/index.html
COPY --from=frontend /app/dist/assets ./public/assets
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/apache-mpm.conf /etc/apache2/conf-available/dispatch-mpm.conf
RUN a2enconf dispatch-mpm
COPY deploy/php.ini /usr/local/etc/php/conf.d/dispatch.ini
COPY deploy/start.sh /usr/local/bin/dispatch-start
RUN chmod +x /usr/local/bin/dispatch-start
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr PORT=10000
EXPOSE 10000
CMD ["dispatch-start"]
