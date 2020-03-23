#!/bin/sh

#artisan shortcut
echo '#! /bin/sh'                >> /bin/art
echo '/usr/local/bin/php artisan "$@"' >> /bin/art
chmod 777 /bin/art
