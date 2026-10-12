FROM php:8.4-apache

# Install the PHP extensions required by UserBase
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Install Composer from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure Apache to serve only the public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy dependency manifests first to improve build caching
COPY composer.json composer.lock ./

# Install production dependencies inside the image
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy the application source
COPY . .

EXPOSE 80