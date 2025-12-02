# Базовый образ с PHP 8.4
FROM php:8.4-fpm-alpine

# Устанавливаем необходимые системные зависимости
RUN apk update && apk add \
    git \
    zip \
    unzip \
    postgresql-dev \
    # Дополнительные пакеты, необходимые для Symfony и некоторых расширений
    libpq \
    libxml2-dev \
    # Убираем лишнее
    && rm -rf /var/cache/apk/*

# Устанавливаем расширения PHP, необходимые для Symfony и PostgreSQL
RUN docker-php-ext-install pdo_pgsql opcache intl
RUN docker-php-ext-enable opcache

# Установка Xdebug (Добавьте linux-headers и другие зависимости)
RUN apk add --no-cache $PHPIZE_DEPS libtool linux-headers \
    && pecl install xdebug-3.4.7 \
    && apk del $PHPIZE_DEPS libtool linux-headers

# Активация Xdebug с помощью официального скрипта
RUN docker-php-ext-enable xdebug

# Копирование INI-файла. Используем стандартное имя для INI (20-xdebug.ini).
COPY ./docker/20-xdebug.ini /usr/local/etc/php/conf.d/20-xdebug.ini

# Устанавливаем Composer (менеджер зависимостей PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Разрешаем запуск команд от имени root (для установки пакетов)
USER root

# Добавление настройки safe.directory для Git, чтобы избежать ошибки ownership при использовании volumes
# (Поскольку /var/www/html — это корень монтирования)
RUN git config --global --add safe.directory /var/www/html
RUN git config --global --add safe.directory /var/www/html/app
