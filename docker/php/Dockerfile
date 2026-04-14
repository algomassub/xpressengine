FROM php:7.4-fpm

# 시스템 패키지 설치
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP 확장 설치
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    fileinfo

# Composer 설치 (curl로 직접 다운로드)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP 설정
RUN echo "upload_max_filesize = 64M" >> /usr/local/etc/php/conf.d/xe.ini && \
    echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/xe.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/xe.ini && \
    echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/xe.ini

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
