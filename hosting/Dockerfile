FROM php:8.2-apache

# Instalar extensiones requeridas para MySQL / MariaDB
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar modulo rewrite de Apache
RUN a2enmod rewrite

# Configurar Apache DocumentRoot a /var/www/html/public y permitir .htaccess
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar el codigo del proyecto
COPY . /var/www/html/

# Ajustar permisos para el usuario www-data de Apache
RUN chown -R www-data:www-data /var/www/html

