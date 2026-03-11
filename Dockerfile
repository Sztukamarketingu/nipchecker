FROM php:8.2-apache
COPY index.php handler.php install.php config.php app.js style.css /var/www/html/
RUN chown -R www-data:www-data /var/www/html
