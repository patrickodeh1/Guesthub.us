FROM php:8.2-cli

# Server-side ID barcode decoder (used by App\Services\BarcodeDecoder via scripts/barcode_decode.py)
RUN apt-get update && apt-get install -y --no-install-recommends python3 python3-venv \
    && python3 -m venv /opt/decoder \
    && /opt/decoder/bin/pip install --no-cache-dir zxing-cpp pillow \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libjpeg-dev libwebp-dev libfreetype6-dev ffmpeg \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

RUN { \
    echo 'upload_max_filesize = 200M'; \
    echo 'post_max_size = 210M'; \
    echo 'memory_limit = 512M'; \
    echo 'max_execution_time = 900'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8001

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
