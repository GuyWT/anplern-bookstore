# เว็บเซิร์ฟเวอร์ Apache + PHP 8.3 + PDO MySQL (เหมือน XAMPP) ใช้โดย docker-compose.yml
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && ln -sf /usr/share/zoneinfo/Asia/Bangkok /etc/localtime \
 && echo "date.timezone = Asia/Bangkok" > /usr/local/etc/php/conf.d/timezone.ini
