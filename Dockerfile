FROM php:8.2-apache

# 安裝 mysqli / pdo_mysql + GD
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# 將程式碼放到 Apache 網站根目錄
WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 80