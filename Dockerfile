# ---------- Build stage ----------
FROM php:8.1-fpm AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev zlib1g-dev libpq-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Laravel and many apps
# (add more if your app needs them: gd, imagick, redis, etc.)
RUN docker-php-ext-install -j"$(nproc)" pdo pdo_mysql intl zip opcache mbstring bcmath

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock ./

# If you have private packages, set auth via build args or GH token
# ARG COMPOSER_AUTH
# RUN [ -n "$COMPOSER_AUTH" ] && echo "$COMPOSER_AUTH" > /root/.composer/auth.json || true

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

# Copy app code
COPY . .

# Cache config/routes/views, but don't fail the build if app boots need env
RUN php artisan config:cache  || true \
 && php artisan route:cache   || true \
 && php artisan view:cache    || true

# ---------- Runtime stage ----------
FROM php:8.1-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor ca-certificates wget curl tar \
 && rm -rf /var/lib/apt/lists/*

# ionCube for PHP 8.1
ARG IONCUBE_URL=https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
RUN set -eux; \
    wget -O /tmp/ioncube.tgz "$IONCUBE_URL"; \
    tar -xzf /tmp/ioncube.tgz -C /tmp; \
    PHP_EXT_DIR="$(php -i | awk -F'=> ' '/^extension_dir/ {print $2}')" ; \
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

WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html

COPY .deploy/nginx.conf /etc/nginx/nginx.conf
COPY .deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/health || exit 1
EXPOSE 80
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
