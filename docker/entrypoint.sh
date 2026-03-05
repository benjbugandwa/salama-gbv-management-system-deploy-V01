#!/usr/bin/env bash
set -e

cd /app

# Optionnel mais utile en prod
php artisan optimize:clear || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# (B) (optionnel) afficher la connexion détectée (debug)
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo 'DB_CONNECTION='.(config('database.default')).PHP_EOL;"

# Storage link (ignore si déjà existant)
php artisan storage:link || true

# Migrations (Railway ne propose pas "postdeploy", donc on le fait au démarrage)
php artisan migrate --force

# Cache (optionnel)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Démarrage (OK pour une V1 de test)
PORT="${PORT:-8080}"
php artisan serve --host=0.0.0.0 --port="$PORT"
#php -S 0.0.0.0:${PORT:-8080} -t public