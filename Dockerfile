FROM php:8.2-apache

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    default-mysql-client \
    libmariadb-dev \
    libsqlite3-dev \
    sqlite3 \
    pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions separately to avoid conflicts
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install mysqli
RUN docker-php-ext-install pdo_sqlite
RUN docker-php-ext-install zip
RUN docker-php-ext-install gd

# Enable apache rewrite
RUN a2enmod rewrite

# Copy project files
COPY . .

EXPOSE 80