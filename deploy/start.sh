#!/bin/sh
set -eu
: "${APP_KEY:?Set APP_KEY using the generated Render secret.}"
: "${DB_URL:?Set DB_URL to your Neon PostgreSQL connection URL.}"
: "${ADMIN_EMAIL:?Set ADMIN_EMAIL for the administrator login.}"
: "${ADMIN_PASSWORD:?Set ADMIN_PASSWORD to a strong administrator password.}"
# Render generates a base64-encoded 256-bit secret; Laravel expects this prefix.
case "$APP_KEY" in base64:*) ;; *) export APP_KEY="base64:$APP_KEY" ;; esac
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT:-10000}}}"
export FRONTEND_URL="${FRONTEND_URL:-$APP_URL}"
if [ -z "${SANCTUM_STATEFUL_DOMAINS:-}" ]; then
    SANCTUM_STATEFUL_DOMAINS=$(php -r '$url=parse_url(getenv("APP_URL")); echo $url["host"].(isset($url["port"]) ? ":".$url["port"] : "");')
    export SANCTUM_STATEFUL_DOMAINS
fi
sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:${PORT:-10000}/" /etc/apache2/sites-available/000-default.conf
php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=AdministratorSeeder --force
php artisan config:cache
php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
