FROM php:8.0-apache

RUN apt-get update && apt-get install -y \
    libzip-dev unzip vim less libgd-dev \
    libpng-dev libwebp-dev libjpeg62-turbo-dev sendmail \
    libxpm-dev libfreetype6-dev && \
    docker-php-ext-configure gd --enable-gd --with-webp --with-jpeg --with-xpm --with-freetype && \
    docker-php-ext-install mysqli zip gd && \
    apt-get remove -y libzip-dev libgd-dev \
    libpng-dev libwebp-dev libjpeg62-turbo-dev \
    libxpm-dev libfreetype6-dev && \
    rm -rf /var/lib/apt/lists/* && \
    /usr/sbin/a2enmod rewrite

ADD ./configs/php*.ini /usr/local/etc/php/conf.d/

#COPY business_encode_24.tar.gz /var/www/html/business_encode.tar.gz
ADD https://www.1c-bitrix.ru/download/business_encode.tar.gz /var/www/html/

WORKDIR "/var/www/html/"

RUN tar xfz business_encode.tar.gz && \
    rm business_encode.tar.gz

RUN mkdir -p /var/www/html/bitrix/php_interface/include/sale_payment/ && \
    chown -R www-data.www-data /var/www/html

VOLUME ["/var/www/html"]

COPY docker-entrypoint.sh /usr/local/bin/

ENTRYPOINT ["docker-entrypoint.sh"]
