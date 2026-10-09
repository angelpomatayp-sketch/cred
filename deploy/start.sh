#!/bin/sh
set -eu
# Railway may restore base-image module links; normalize them at runtime.
a2dismod mpm_event mpm_worker
a2enmod mpm_prefork
php /var/www/html/scripts/setup-cloud.php
apache2ctl -t
exec apache2-foreground
