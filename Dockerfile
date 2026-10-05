FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libjpeg-dev libwebp-dev libfreetype6-dev ffmpeg \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 14M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 60'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8001

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
