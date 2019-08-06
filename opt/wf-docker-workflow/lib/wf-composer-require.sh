#!/bin/bash
# Debug mode:
#set -x

cd ${SYMFONY_PATH}
composer require --optimize-autoloader --update-no-dev ${@}
chmod -R 777 /opt/wf-docker-workflow/symfony4/var/cache
