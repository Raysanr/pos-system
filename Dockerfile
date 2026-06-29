# syntax=docker/dockerfile:1

# ---------- Stage 1: build frontend assets (Vite + Tailwind) ----------
FROM node:22-bookworm-slim AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Tailwind scans the blade files, so the whole project must be present
COPY . .
RUN npm run build

# ---------- Stage 2: PHP runtime ----------
FROM php:8.3-cli-bookworm AS app
WORKDIR /app

# System libs needed by the PHP extensions below
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev sqlite3 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_sqlite mbstring bcmath intl zip gd pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App source
COPY . .

# Built assets from stage 1
COPY --from=assets /app/public/build ./public/build

# Finish composer autoloader now that source is present
RUN composer dump-autoload --optimize --no-dev

# Make storage + database writable
RUN chmod -R 775 storage bootstrap/cache database

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8000
CMD ["start.sh"]
