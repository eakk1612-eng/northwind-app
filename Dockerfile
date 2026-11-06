FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
COPY . /var/www/html/
RUN chmod +x /var/www/html/start.sh
CMD ["/var/www/html/start.sh"]
