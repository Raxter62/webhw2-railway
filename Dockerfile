FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# 將程式碼放到 Apache 網站根目錄
WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 80