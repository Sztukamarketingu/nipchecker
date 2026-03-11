FROM php:8.2-apache

# GusApi wymaga ext-soap (SOAP client dla GUS BIR1)
RUN apt-get update && apt-get install -y libxml2-dev \
    && docker-php-ext-install soap \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Konfiguracja Apache (PassEnv dla GUS)
COPY docker-env.conf /etc/apache2/conf-available/
RUN a2enconf docker-env

# Źródła aplikacji + vendor (bez composer install – sieć może być niedostępna w buildzie)
COPY index.php handler.php install.php config.php app.js style.css status.php gus_test.php cleanup_placements.php /var/www/html/
COPY vendor/ /var/www/html/vendor/

RUN chown -R www-data:www-data /var/www/html
