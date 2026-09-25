FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    curl \
    ca-certificates \
    ffmpeg \
    python3-pip \
    libzip-dev \
    && update-ca-certificates \
    && docker-php-ext-install pdo_mysql

RUN python3 -m pip install --break-system-packages --no-cache-dir yt-dlp

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
