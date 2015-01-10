FROM ubuntu:latest

ENV DEBIAN_FRONTEND noninteractive

# Update the package repository
RUN apt-get clean &&  apt-get update

RUN apt-get install -y wget curl locales

# Configure timezone and locale
RUN echo "Europe/Madrid" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata
RUN export LANGUAGE=es_ES.UTF-8 && export LANG=es_ES.UTF-8 && export LC_ALL=es_ES.UTF-8 && locale-gen es_ES.UTF-8 && dpkg-reconfigure locales

RUN apt-get clean && apt-get update

RUN apt-get -y install gettext \
    apache2 \
    apache2-mpm-prefork \
    php5 \
    php5-mysqlnd

# Actually this is needed because on boot2docker it's not posible to write to a
# data volume or to a data-only volume container if the user who tryes to write does
# not have root permissions. This lines, should remain here until it does get fixed
# on boot2docker.
RUN useradd -m -u 1000 symfony
RUN echo "symfony ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers.d/symfony
RUN chmod 0440 /etc/sudoers.d/symfony

RUN sed -i 's/export APACHE_RUN_USER=www\-data/export APACHE_RUN_USER=symfony/g' /etc/apache2/envvars

ADD virtual-host.apache.conf /etc/apache2/sites-available/000-default.conf
ADD php.ini /etc/php5/apache2/php.ini
RUN a2enmod php5 rewrite

# Forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/apache2/access.log
RUN ln -sf /dev/stderr /var/log/apache2/error.log

EXPOSE 80
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]