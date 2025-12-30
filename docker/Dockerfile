FROM php:8.4-fpm-alpine3.23

# Install common extensions your app will likely need.
# - curl: for OAuth/token calls (cURL extension is commonly used)
# - openssl: for encryption routines (usually already available)
RUN apk add --no-cache \
    bash \
    curl \
    openssl \
  && docker-php-ext-install -j$(nproc) \
    opcache

# Create a non-root working directory (default is fine, but explicit is nice)
WORKDIR /var/www/html

# PHP-FPM listens on 9000 by default in this image
EXPOSE 9000
