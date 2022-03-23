FROM madpeter/phpapachepreload:latest

MAINTAINER Madpeter

COPY --chown=www-data:www-data . /srv/website
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /srv/website

ENV ENDPOINT_URL='http://ip:9000/' \
    API_USER='admin' \
    API_PASSWORD='passwordhere' \
    ENDPOINT_ID='1' \
    IGNORE_CONTAINERS='portainer|#|portainerbackup'

RUN apt-get update -y