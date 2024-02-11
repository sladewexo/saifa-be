# Use the specified base image
FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
        libssl-dev \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN apt-get install -y vim-common

# Install Composer globally
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

# Set the working directory inside the container
WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install

# Make sure files are owned by the web server user
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80

# Configure Apache Document Root to point to the API directory
ENV APACHE_DOCUMENT_ROOT /var/www/html

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Optionally, enable Apache mod_rewrite if you need it
RUN a2enmod rewrite

# Define the volume that maps your local project directory to the container
VOLUME /var/www/html

COPY ./script/init-script.sh /usr/local/bin/init-script.sh
COPY ./script/setup-env.sh /usr/local/bin/setup-env.sh
COPY ./script/setup-migration.sh /usr/local/bin/setup-migration.sh
RUN chmod +x /usr/local/bin/init-script.sh /usr/local/bin/setup-env.sh /usr/local/bin/setup-migration.sh
ENTRYPOINT ["init-script.sh"]

RUN a2enmod headers

CMD ["apache2-foreground"]
