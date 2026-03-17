#!/bin/bash
set -e

chmod -R 777 /var/www/html
touch /etc/dnsmasq.conf
chmod 777 /etc/dnsmasq.conf

exec apache2-foreground