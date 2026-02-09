web: vendor/bin/heroku-php-apache2 public/
reverb: php artisan reverb:start --host=0.0.0.0 --port=8080
release: php artisan config:cache && php artisan event:cache && php artisan route:cache && php artisan migrate --force
