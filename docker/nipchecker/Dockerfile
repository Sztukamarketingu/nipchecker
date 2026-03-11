FROM php:8.2-apache

# GusApi wymaga ext-soap (SOAP client dla GUS BIR1)
RUN apt-get update && apt-get install -y libxml2-dev \
    && docker-php-ext-install soap \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalacja Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Konfiguracja Apache (PassEnv dla GUS)
COPY docker-env.conf /etc/apache2/conf-available/
RUN a2enconf docker-env

# Zależności Composer – instalacja w buildzie
COPY composer.json composer.lock /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --no-scripts --no-interaction

# Źródła aplikacji
COPY index.php handler.php install.php config.php app.js style.css status.php gus_test.php /var/www/html/

RUN chown -R www-data:www-data /var/www/html
