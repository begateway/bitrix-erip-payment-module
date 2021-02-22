#!/bin/bash

set -e

# if [ -d "/var/www/html/bitrix/modules/sale" ]; then
#    ln -sf /var/www/html/bitrix/modules/begateway.erip/install/sale_payment/begateway.erip \
#          /var/www/html/bitrix/php_interface/include/sale_payment/begateway.erip
# fi

echo "$(hostname -i) $(hostname) $(hostname).localhost" >> /etc/hosts
/usr/sbin/service sendmail restart

/usr/local/bin/apache2-foreground

exec "$@"
