FROM php:8.2-apache

# Habilita o rewrite do Apache
RUN a2enmod rewrite

# Instala dependências do sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        libssl-dev \
        unzip \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mysqli zip intl \
    && docker-php-ext-enable gd pdo_mysql mysqli zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# O sistema usa rotas fixas /cobranca/... no código, então
# publicamos a aplicação em um subdiretório home do Apache
WORKDIR /var/www/html/cobranca

# Copia os arquivos da aplicação
COPY . /var/www/html/cobranca/

# Permissões de escrita para uploads e logs
RUN mkdir -p /var/www/html/cobranca/assets/img/avatars \
             /var/www/html/cobranca/assets/img/banners \
             /var/www/html/cobranca/assets/boletos_inter \
             /var/www/html/cobranca/assets/boletos_pagbank \
             /var/www/html/cobranca/config/inter_certs \
    && chown -R www-data:www-data /var/www/html/cobranca

# Apache expõe a aplicação pela URL /cobranca
COPY docker/vhost.conf /etc/apache2/conf-available/cobranca.conf
RUN a2enconf cobranca

EXPOSE 80

CMD ["apache2-foreground"]