FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
libpng-dev \
libjpeg-dev \
libfreetype6-dev \
libzip-dev \
libonig-dev \
libicu-dev \
libcurl4-openssl-dev \
libssl-dev \
unzip \
&& rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install -j"$(nproc)" \
mysqli \
pdo_mysql \
gd \
zip \
mbstring \
intl \
bcmath \
exif \
opcache

# Debug: show what MPM files exist BEFORE our fix
RUN echo '=== MODS-ENABLED BEFORE ===' && ls -la /etc/apache2/mods-enabled/ | grep mpm

# Fix Apache MPM: remove all MPM load/conf files, enable ONLY mpm_prefork
RUN find /etc/apache2/ -name 'mpm_*' -path '*/mods-enabled/*' -delete \
 && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
  && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

  # Debug: show what MPM files exist AFTER our fix
  RUN echo '=== MODS-ENABLED AFTER ===' && ls -la /etc/apache2/mods-enabled/ | grep mpm

  RUN a2enmod rewrite headers deflate expires

  RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

  ENV PORT=8080
  RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
   && sed -i 's/:80>/:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

   RUN { \
   echo 'upload_max_filesize = 32M'; \
   echo 'post_max_size = 32M'; \
   echo 'memory_limit = 256M'; \
   echo 'max_execution_time = 120'; \
   } > /usr/local/etc/php/conf.d/supportty.ini

   WORKDIR /var/www/html
   COPY . /var/www/html/

   RUN mkdir -p /var/www/html/script/uploads /var/www/html/script/config \
    && chown -R www-data:www-data /var/www/html

    EXPOSE 8080

    CMD ["apache2-foreground"]
