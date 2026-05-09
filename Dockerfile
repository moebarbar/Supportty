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

                                                                                RUN a2enmod rewrite headers deflate expires

                                                                                RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

                                                                                # UseCanonicalName Off: Apache uses incoming Host: header for redirect URLs (no :8080 leak)
                                                                                ENV RAILWAY_PUBLIC_DOMAIN=supportty-production.up.railway.app
                                                                                RUN sed -i 's|<VirtualHost \*:80>|<VirtualHost *:80>\n    ServerName supportty-production.up.railway.app\n    UseCanonicalName Off\n|' \
                                                                                    /etc/apache2/sites-available/000-default.conf

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
                                                                                                                                
                                                                                                                                    RUN mkdir -p /var/www/html/script/uploads /var/www/html/script/config /var/www/html/script/apps \
                                                                                                                                        && chown -R www-data:www-data /var/www/html
                                                                                                                                        
                                                                                                                                            VOLUME /var/www/html/script
                                                                                                                                                RUN chmod +x /var/www/html/entrypoint.sh
                                                                                                                                                
                                                                                                                                                    EXPOSE ${PORT}
                                                                                                                                                        ENTRYPOINT ["/var/www/html/entrypoint.sh"]
