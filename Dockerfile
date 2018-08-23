FROM php:7.2.8-apache
RUN mkdir -p /dummyfile
COPY ./dummy /dummyfile
RUN apt-get update && apt-get install -y apt-utils wget vim libjpeg-dev libpq-dev libldap2-dev libedit-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libpng16-16 libxpm-dev libfreetype6-dev libpng-dev libmcrypt-dev libmcrypt4 libcurl3-dev libxml2-dev libfreetype6 libjpeg62-turbo libfreetype6-dev libjpeg62-turbo-dev curl fonts-nanum* fonts-unfonts-core fonts-unfonts-extra fonts-baekmuk unzip\
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) iconv 
RUN docker-php-ext-configure gd --with-gd --with-webp-dir --with-jpeg-dir \
    --with-png-dir --with-zlib-dir --with-xpm-dir --with-freetype-dir \
    --enable-gd-native-ttf
#RUN docker-php-ext-install gd pdo pdo_mysql
RUN echo '\n'|pecl install mcrypt-1.0.1 && docker-php-ext-enable mcrypt
RUN docker-php-ext-install gd pdo pdo_mysql mbstring soap mysqli iconv pcntl zip curl bcmath opcache simplexml xmlrpc xml session readline ldap pdo_pgsql\
&& docker-php-ext-enable mysqli mcrypt gd pdo_mysql pdo_pgsql pcntl zip curl bcmath opcache simplexml xmlrpc xml soap session readline ldap mbstring
#RUN docker-php-ext-install iconv pcntl zip curl bcmath opcache simplexml xmlrpc xml session readline ldap pdo_pgsql \
#&& docker-php-ext-enable iconv mcrypt gd pdo_mysql pdo_pgsql pcntl zip curl bcmath opcache simplexml xmlrpc xml soap session readline ldap mbstring 

RUN pecl install redis-4.0.1 \
    && pecl install xdebug-2.6.0 \
    && docker-php-ext-enable redis xdebug

RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/ssl-cert-snakeoil.key -out /etc/ssl/certs/ssl-cert-snakeoil.pem -subj "/C=AT/ST=Vienna/L=Vienna/O=Security/OU=Development/CN=example.com"

RUN a2enmod rewrite \
&& a2ensite default-ssl \
&& a2enmod ssl

# Memory Limit
RUN echo "memory_limit=-1" > $PHP_INI_DIR/conf.d/memory-limit.ini

# Time Zone
RUN echo "date.timezone=Asia/Seoul" > $PHP_INI_DIR/conf.d/date_timezone.ini

#한글폰트추가
RUN mkdir -p /usr/share/fonts/truetype/ms \
&& cp /dummyfile/fonts.zip /usr/share/fonts/truetype/ms/ \
&& unzip /usr/share/fonts/truetype/ms/fonts.zip
#wkhtmltox 인스톨 html 2 img,pdf
RUN apt-get update
RUN dpkg -i /dummyfile/wkhtmltox_0.12.5-1.stretch_amd64.deb || true
RUN apt-get -f -y install
RUN dpkg -i /dummyfile/wkhtmltox_0.12.5-1.stretch_amd64.deb
#composer 인스톨
RUN /usr/bin/curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && /bin/rm /tmp/composer-setup.php
# Set up the command arguments
EXPOSE 80
EXPOSE 443