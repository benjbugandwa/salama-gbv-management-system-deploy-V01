# ---- PHP (Debian) ----
FROM php:8.2-cli AS php

ENV DEBIAN_FRONTEND=noninteractive



# Libs nécessaires pour GD (jpeg/freetype/png), intl, zip, pgsql…
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libpq-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensions PHP requises par ton projet (Spreadsheet/Excel => gd, bcmath)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd bcmath pdo_pgsql intl zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 (pour Vite build)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Dépendances PHP
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Dépendances JS + build assets
COPY package.json package-lock.json* pnpm-lock.yaml* yarn.lock* ./
RUN if [ -f package-lock.json ]; then npm ci; \
    elif [ -f pnpm-lock.yaml ]; then npm i -g pnpm && pnpm i --frozen-lockfile; \
    elif [ -f yarn.lock ]; then npm i -g yarn && yarn install --frozen-lockfile; \
    else npm i; fi

# Code app
COPY . .

# Build Vite
RUN npm run build

# Permissions (important)
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
CMD ["/entrypoint.sh"]