# Use the official PHP image with necessary extensions
FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
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
    libsodium-dev \
    libpq -dev \
    default-mysql-client \
    default-libmysqlclient-dev \
    libfreetype6 \
    libjpeg62-turbo -dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip sodium  \

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# install nodejs and npm 
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash && \
    && apt-get update && apt-get install -y nodejs 
# Set working directory
WORKDIR /var/www/html

# Copy existing application files
COPY . .

# Expose port 8000 for PHP-FPM
EXPOSE 8000

# Install PHP and JS dependencies
RUN composer install
RUN npm install


# Run Laravel migrations and start the server
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000
