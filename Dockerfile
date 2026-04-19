FROM php:8.1-apache



# copy files to render
COPY . /var/www/html/

# give Apache user read and write permissions
RUN chown -R www-data:www-data /var/www/html

# enable rewrite module for .htaccess
RUN a2enmod rewrite

# expose port 80
EXPOSE 80

# run Apache in foreground
CMD ["apache2-foreground"]