FROM php:8.2-apache
COPY docker-env.conf /etc/apache2/conf-available/
RUN a2enconf docker-env
COPY index.php handler.php install.php config.php app.js style.css status.php /var/www/html/
RUN chown -R www-data:www-data /var/www/html
