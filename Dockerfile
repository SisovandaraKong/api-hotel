# ==============================
# Build stage
# ==============================
FROM php:8.2-fpm AS build

# Install system dependencies (split for stability)
RUN apt-get update && apt-get install -y git curl zip unzip
RUN apt-get install -y libonig-dev libxml2-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libssl-dev libsodium-dev libpq-dev default-mysql-client default-libmysqlclient-dev libjpeg62-turbo-dev

# Configure and install PHP extensions (split for stability)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip sodium curl

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
RUN apt-get install -y nodejs

# Set working directory
WORKDIR /var/www/html

# Copy Laravel app source
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build frontend if package.json exists
RUN if [ -f package.json ]; then npm install && npm run build; fi

# ==============================
# Final stage
# ==============================
FROM php:8.2-fpm

# Install runtime dependencies (split for stability)
RUN apt-get update && apt-get install -y libonig-dev libxml2-dev libzip-dev libpng-dev libssl-dev libjpeg-dev libfreetype6-dev libsodium-dev libpq-dev default-mysql-client default-libmysqlclient-dev libjpeg62-turbo-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip sodium curl

# Set working directory
WORKDIR /var/www/html

# Copy app from build stage
COPY --from=build /var/www/html /var/www/html

# Set correct permissions for Laravel storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Start Laravel app
CMD php artisan storage:link && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan config:clear && \
    php artisan cache:clear && \
    php artisan route:clear && \
    php artisan view:clear && \
    php artisan serve --host=0.0.0.0 --port=8000
