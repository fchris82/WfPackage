#!/bin/bash

if [ ${WF_DEBUG:-0} -ge 1 ]; then
    [[ -f /.dockerenv ]] && echo -e "\033[1mDocker: \033[33m${WF_DOCKER_HOST_CHAIN}\033[0m"
    echo -e "\033[1mDEBUG\033[33m $(realpath "$0")\033[0m"
    SYMFONY_COMMAND_DEBUG="-vvv"
    DOCKER_DEBUG="-e WF_DEBUG=${WF_DEBUG}"
fi
[[ ${WF_DEBUG:-0} -ge 2 ]] && set -x

if [ "${CI:-0}" != "0" ] || [ "${WF_TTY}" == "0" ]; then
    SYMFONY_DISABLE_TTY="--no-ansi --no-interaction"
fi

# You can use the `--dev` to enable it without edit config
WF_SYMFONY_ENV=${WF_SYMFONY_ENV:-prod}
WF_XDEBUG_ENABLED=${WF_XDEBUG_ENABLED:-0}
if [ "$1" == "--dev" ]; then
    shift
    WF_SYMFONY_ENV="dev"
    WF_XDEBUG_ENABLED="1"
fi
