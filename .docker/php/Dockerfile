FROM php:8.3-fpm-alpine

LABEL maintainer="Notification Service Team"
LABEL description="PHP-FPM for Laravel Notification Service"

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    librdkafka-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    autoconf \
    g++ \
    make \
    icu-dev \
    openssl-dev \
    linux-headers

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    sockets

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Kafka extension (librdkafka)
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Configure OPcache
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Create directories
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/app/public \
    /var/www/bootstrap/cache \
    /var/log/supervisor \
    && chown -R www:www /var/www /var/log/supervisor \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/log/supervisor

# Copy supervisor configuration
COPY .docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www

# Copy application files
COPY --chown=www:www src/ .

# Install composer dependencies (production only)
RUN if [ -f "composer.json" ]; then \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress; \
    fi

# Switch to www user
# USER www

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]