FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    curl \
    git \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_mysql opcache zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /var/www/html

RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader --no-interaction; fi

RUN mkdir -p /tmp/communalink-sessions \
    && chown -R www-data:www-data /var/www/html /tmp/communalink-sessions

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV AUTO_CREATE_DATABASE=false
ENV AUTO_DB_SCHEMA_SYNC=false
ENV SESSION_SAVE_PATH=/tmp/communalink-sessions
ENV SESSION_DRIVER=db

RUN sed -ri -e 's!80!8080!g' /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD curl -fsS http://127.0.0.1:8080/api/health.php || exit 1

USER www-data

CMD ["apache2-foreground"]
