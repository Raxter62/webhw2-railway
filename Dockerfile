FROM php:8.2-apache

# 安裝 mysqli / pdo_mysql + GD
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# 🔧 建置階段：清掉所有 MPM，只留下 mpm_prefork + rewrite
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite

# 將程式碼放到 Apache 網站根目錄
WORKDIR /var/www/html
COPY . /var/www/html/

EXPOSE 80

#railway 容器設定

# 🔧 啟動階段：再保險一次，把 event/worker 關掉後才啟動 Apache
CMD ["bash", "-c", "a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true && apache2-foreground"]