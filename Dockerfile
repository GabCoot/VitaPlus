FROM php:8.2-apache

# Copia tu archivo webhook.php a la carpeta pública del servidor
COPY webhook.php /var/www/html/

# Configura Apache para que escuche en el puerto que Render le asigne
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 80