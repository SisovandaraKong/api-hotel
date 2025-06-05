# ==============================
# Build stage
# ==============================
FROM php:8.2-fpm AS build

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libssl-dev \
    libsodium-dev \
    libpq-dev \
    default-mysql-client \
    default-libmysqlclient-dev \
    libjpeg62-turbo-dev \
  && rm -rf /var/lib/apt/lists/*

# Configure gd extension separately
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions in smaller groups to reduce memory load
RUN docker-php-ext-install pdo pdo_mysql mbstring
RUN docker-php-ext-install exif pcntl bcmath
RUN docker-php-ext-install gd zip sodium
RUN docker-php-ext-install curl

# OpenSSL is built-in and enabled by default, no need for docker-php-ext-install or -enable

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
  && apt-get install -y --no-install-recommends nodejs \
  && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy Laravel app
COPY . .

# Install PHP dependencies with Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build frontend if package.json exists
RUN if [ -f package.json ]; then npm install && npm run build; fi


# ==============================
# Final stage
# ==============================
FROM php:8.2-fpm

# Install runtime system dependencies with no install recommends, clean apt cache
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpng-dev \
    libssl-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libsodium-dev \
    libpq-dev \
    default-mysql-client \
    default-libmysqlclient-dev \
    libjpeg62-turbo-dev \
  && rm -rf /var/lib/apt/lists/*

# Configure gd extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions in smaller groups (same as build stage)
RUN docker-php-ext-install pdo pdo_mysql mbstring
RUN docker-php-ext-install exif pcntl bcmath
RUN docker-php-ext-install gd zip sodium
RUN docker-php-ext-install curl

# Set working directory
WORKDIR /var/www/html

# Copy built app from build stage
COPY --from=build /var/www/html /var/www/html

# Set proper permissions
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
