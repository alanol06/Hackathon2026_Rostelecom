FROM php:8.3-apache

# mod_rewrite нужен, чтобы все запросы /api/... шли в один файл public/api/index.php
RUN a2enmod rewrite

# Корень сайта - папка public (всё остальное, включая data, снаружи недоступно)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/app.conf && a2enconf app

COPY . /var/www/html
