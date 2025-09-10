# Imagem base do PHP com Apache
FROM php:8.1-apache

# Instalar extensões PHP necessárias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Ativar mod_rewrite do Apache
RUN a2enmod rewrite

# Copiar os arquivos para o container
COPY . /var/www/html/

# Definir a pasta raiz
WORKDIR /var/www/html/

# Definir permissões
RUN chown -R www-data:www-data /var/www/html

# Expor a porta padrão do Apache
EXPOSE 80

# Iniciar o Apache
CMD ["apache2-foreground"]
