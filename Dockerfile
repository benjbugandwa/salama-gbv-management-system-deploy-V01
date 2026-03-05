# =========================
#  SALAMA - Laravel on Railway
#  PHP 8.2 + GD + BCMath + PGSQL + Intl + Zip + Node 20 (Vite)
# =========================

FROM php:8.2-cli

ENV DEBIAN_FRONTEND=noninteractive

# 1) System deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libpq-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd bcmath pdo_pgsql intl zip

# 2) Composer (copied from official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) Node 20 for Vite build
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# 4) Install PHP dependencies WITHOUT scripts (artisan not yet available)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    --no-scripts --no-progress

# 5) Install JS deps
COPY package.json package-lock.json* pnpm-lock.yaml* yarn.lock* ./
RUN if [ -f package-lock.json ]; then npm ci; \
    elif [ -f pnpm-lock.yaml ]; then npm i -g pnpm && pnpm i --frozen-lockfile; \
    elif [ -f yarn.lock ]; then npm i -g yarn && yarn install --frozen-lockfile; \
    else npm i; fi

# 6) Copy full app (artisan now exists)
COPY . .

# 7) Build front assets
RUN npm run build

# 8) Now run Laravel scripts safely (artisan exists)
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan optimize:clear

# 9) Storage/cache permissions
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 10) Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
CMD ["/entrypoint.sh"]