FROM php:8.2-cli

RUN docker-php-ext-install pdo pdo_pgsql pgsql

WORKDIR /app
COPY . .

CMD php -S 0.0.0.0:$PORT router.php