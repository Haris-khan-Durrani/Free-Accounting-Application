# ==============================================================================
# OneSol Invoice Manager — Enterprise Docker Image
# PHP 8.3-FPM + Nginx + GD + Redis + OPcache
# ==============================================================================

FROM php:8.3-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and build libraries
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    mysql-client \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        mbstring \
        intl \
        opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Configure OPcache for high-concurrency production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Copy Nginx & Supervisor configurations
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Copy application source code
COPY . /var/www/html/

# Create required directories and set proper ownership
RUN mkdir -p /var/www/html/storage/cache \
    /var/www/html/assets/uploads \
    /run/nginx \
    /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod +x /var/www/html/docker/entrypoint.sh

# Expose port 80
EXPOSE 80

# Entrypoint script handles DB readiness check, auto-migrations, and launches Supervisor
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
