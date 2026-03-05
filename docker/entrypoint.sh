#!/usr/bin/env bash
set -e

# Optionnel mais utile en prod
php artisan optimize:clear || true

# Storage link (ignore si déjà existant)
php artisan storage:link || true

# Migrations (Railway ne propose pas "postdeploy", donc on le fait au démarrage)
php artisan migrate --force

# Cache (optionnel)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Démarrage (OK pour une V1 de test)
php -S 0.0.0.0:${PORT:-8080} -t public