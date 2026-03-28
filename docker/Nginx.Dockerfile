FROM nginx:1.27-alpine

ADD docker/conf/vhost.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/TelegramBot_Kratom
