#!/bin/sh
# Sobe a aplicação num único contêiner: servidor web, worker da fila e scheduler.
# Suficiente para a demo; em produção maior, separe worker e scheduler em serviços próprios.
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
php artisan db:seed --force

# Worker da fila (reinicia a cada hora para liberar memória)
(while true; do php artisan queue:work --sleep=3 --tries=3 --max-time=3600; sleep 1; done) &

# Scheduler: importação horária do arquivo do dia e diária do dia anterior
php artisan schedule:work &

exec frankenphp php-server --root public/ --listen ":${PORT}"
