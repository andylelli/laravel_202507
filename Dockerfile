# ---------- Build stage ----------
FROM php:8.1-fpm AS build

# System packages
#  - libonig-dev + libonig5 for mbstring/oniguruma
#  - pkg-config so mbstring's configure can locate oniguruma
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip pkg-config \
    libicu-dev libzip-dev zlib1g-dev libpq-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libxml2-dev libcurl4-openssl-dev \
    libonig-dev libonig5 \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions commonly needed by Laravel apps
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql intl zip opcache mbstring bcmath gd xml curl

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MEMORY_LIMIT=-1
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install vendors early (cache) WITHOUT scripts (artisan not copied yet)
COPY composer.json composer.lock ./
# (Optional) GitHub token for private packages
ARG GITHUB_TOKEN=""
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com "$GITHUB_TOKEN"; fi
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader -vvv

# Now copy the full app so artisan is present
COPY . .

# Safe clears (don’t fail the image build if env/DB isn’t ready)
RUN php artisan config:clear  || true \
 && php artisan route:clear   || true \
 && php artisan view:clear    || true


# ---------- Runtime stage ----------
FROM php:8.1-fpm

# Runtime packages (Nginx + Supervisor + tools)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor ca-certificates wget curl tar \
 && rm -rf /var/lib/apt/lists/*

# ionCube for PHP 8.1 (trim extension_dir to avoid trailing space)
ARG IONCUBE_URL=https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz
RUN set -eux; \
    wget -O /tmp/ioncube.tgz "$IONCUBE_URL"; \
    tar -xzf /tmp/ioncube.tgz -C /tmp; \
    PHP_EXT_DIR="$(php -r 'echo rtrim(ini_get("extension_dir"));')"; \
    cp "/tmp/ioncube/ioncube_loader_lin_8.1.so" "$PHP_EXT_DIR/ioncube_loader_lin_8.1.so"; \
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

# App files from build stage
WORKDIR /var/www/html
COPY --from=build /var/www/html /var/www/html

# Nginx + Supervisor configs
COPY .deploy/nginx.conf /etc/nginx/nginx.conf
COPY .deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/health || exit 1

EXPOSE 80
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
