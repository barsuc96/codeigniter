FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Instalacja wymaganych pakietów i rozszerzeń PHP
RUN apt-get update && apt-get install -y \
    unzip zip git libicu-dev libzip-dev \
    && docker-php-ext-install intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite

# Konfiguracja Apache (zmiana ścieżek + włączenie nadpisywania)
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/default-ssl.conf

RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Dodanie Composera z oficjalnego obrazu
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ustawienie katalogu roboczego
WORKDIR /var/www/html

# (Opcjonalne: kopiowanie projektu + instalacja zależności)
# COPY . .
# RUN composer install --no-interaction
