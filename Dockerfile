# ---------- Build stage ----------
FROM php:8.1-fpm AS build

# System deps for common Laravel stacks
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev zlib1g-dev libonig-dev libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions (add more if your app needs them: gd, bcmath, redis, imagick, etc.)
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql intl zip opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App deps
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy app code
COPY . .

# Cache config/routes/views for production
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# ---------- Runtime stage ----------
FROM php:8.1-fpm

# Nginx + Supervisor + tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor ca-certificates wget curl tar \
    && rm -rf /var/lib/apt/lists/*

# ---- ionCube loader for PHP 8.1 ----
ARG IONCUBE_URL=https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
RUN set -eux; \
    wget -O /tmp/ioncube.tgz "$IONCUBE_URL"; \
    tar -xzf /tmp/ioncube.tgz -C /tmp; \
    PHP_EXT_DIR="$(php -i | grep '^extension_dir =>' | awk '{print $7}')"; \
    cp "/tmp/ioncube/ioncube_loader_lin_8.1.so" "$PHP_EXT_DIR/"; \
    echo "zend_extension=$PHP_EXT_DIR/ioncube_loader_lin_8.1.so" > /usr/local/etc/php/conf.d/00-ioncube.ini; \
    php -v

# OPcache for prod
RUN printf "%s\n" \
    "opcache.enable=1" \
    "opcache.enable_cli=0" \
    "opcache.jit=1255" \
    "opcache.jit_buffer_size=64M" \
    "opcache.memory_consumption=256" \
    "opcache.max_accelerated_files=50000" \
  > /usr/local/etc/php/conf.d/opcache.ini

# Bring in built app
WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html

# Nginx + PHP-FPM config
COPY .deploy/nginx.conf /etc/nginx/nginx.conf
COPY .deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Simple health endpoint
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/health || exit 1

EXPOSE 80
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
