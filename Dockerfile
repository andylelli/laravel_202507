# ---------- Build stage ----------
FROM php:8.1-fpm AS build

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev zlib1g-dev libpq-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libcurl4-openssl-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions commonly needed by Laravel apps
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql intl zip opcache mbstring bcmath gd xml curl

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install vendors EARLY (better layer caching) – but WITHOUT scripts (artisan not copied yet)
COPY composer.json composer.lock ./
# Optional: auth for private packages via build-arg (safe to omit)
ARG GITHUB_TOKEN=""
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com "$GITHUB_TOKEN"; fi
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader -vvv

# Now copy the full app (artisan becomes available)
COPY . .

# Don’t fail the build if these need runtime env
RUN php artisan config:clear  || true \
 && php artisan route:clear   || true \
 && php artisan view:clear    || true


# ---------- Runtime stage ----------
FROM php:8.1-fpm

# Runtime packages (Nginx + Supervisor + tools)
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

# OPcache tuned for production
RUN printf "%s\n" \
  "opcache.enable=1" \
  "opcache.enable_cli=0" \
  "opcache.jit=1255" \
  "opcache.jit_buffer_size=64M" \
  "opcache.memory_consumption=256" \
  "opcache.max_accelerated_files=50000" \
  > /usr/local/etc/php/conf.d/opcache.ini

# App
WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html

# Nginx + Supervisor configs (ensure these exist in your repo)
# .deploy/nginx.conf should serve /var/www/html/public and pass PHP to 127.0.0.1:9000
# .deploy/supervisord.conf should start php-fpm and nginx (daemon off)
COPY .deploy/nginx.conf /etc/nginx/nginx.conf
COPY .deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Simple health endpoint (expect your nginx.conf to serve /health)
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/health || exit 1

EXPOSE 80
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
