# PHP 8.5 + extensions for Laravel 13 API (+ Node/Chromium for Browsershot PDFs)
FROM php:8.5-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev \
    chromium \
    fonts-liberation \
    fonts-dejavu-core \
    nodejs \
    npm \
    && docker-php-ext-install pdo_pgsql bcmath intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && npm install -g puppeteer \
    && rm -rf /var/lib/apt/lists/*

ENV BROWSERSHOT_CHROME_PATH=/usr/bin/chromium
ENV PUPPETEER_SKIP_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
