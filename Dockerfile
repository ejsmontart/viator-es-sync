FROM php:7.4-cli

COPY ./composer.json  /usr/src/viatorsync/
COPY ./composer.lock  /usr/src/viatorsync/

COPY ./src     /usr/src/viatorsync/src
COPY ./scripts /usr/src/viatorsync/scripts
COPY ./vendor  /usr/src/viatorsync/vendor

WORKDIR /usr/src/viatorsync

# install composer and fetch all dependencies into the container 
# (folder is not mounted we copy vendor files. Having files outside of the container speeds up container build but is not necessary.
RUN curl -sS https://getcomposer.org/installer | php \
  && chmod +x composer.phar && mv composer.phar /usr/local/bin/composer
RUN composer install --no-scripts --no-autoloader

CMD [ "/usr/src/viatorsync/scripts/repeat_en.php", "php" ]

ENTRYPOINT ["/usr/src/viatorsync/scripts/entrypoint.sh"]