#!/bin/bash

WF_DOCKER_HOST_CHAIN+="$(hostname) "
if [ ${WF_DEBUG:-0} -ge 1 ]; then
    [[ -f /.dockerenv ]] && echo -e "\033[1mDocker: \033[33m${WF_DOCKER_HOST_CHAIN}\033[0m"
    echo -e "\033[1mDEBUG\033[33m $(realpath "$0")\033[0m"
    SYMFONY_COMMAND_DEBUG="-vvv"
    DOCKER_DEBUG="-e WF_DEBUG=${WF_DEBUG}"
fi
[[ ${WF_DEBUG:-0} -ge 2 ]] && set -x

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    echo "xdebug.remote_host = $(/sbin/ip route|awk '/default/ { print $3 }')" >> ${XDEBUG_CONFIG_FILE}
    cp ${XDEBUG_CONFIG_FILE} ${XDEBUG_CONFIG_FILE%.*}
    export XDEBUG_CONFIG="idekey=Docker"
    export PHP_IDE_CONFIG="serverName=Docker"
fi

if [ ! -z "${LOCAL_USER_NAME}" ]; then
    # USER
    USER_ID=${LOCAL_USER_ID:-9001}
    CURRENT_USER=$(id -u)
    # default docker group name
    DOCKER_GROUP_NAME='docker'
    if [ $USER_ID != 0 ] && [ "$USER_ID" != "$CURRENT_USER" ]; then
        # If the sock exist, we try to find the correct user group to can use docker
        if [ -S /var/run/docker.sock ]; then
            # docker group ID from docker.sock file
            dockergid=$(stat -c '%g' /var/run/docker.sock)
            # try to find an existing group name by GID
            gname=$(getent group $dockergid | cut -d: -f1)
            # if there isn't existing group by GID then we set it for the docker group
            if [ -z $gname ]; then
                groupmod -g $dockergid $DOCKER_GROUP_NAME
            # if there is an existing group then we set that
            else
                DOCKER_GROUP_NAME=$gname
            fi
        fi

        adduser -u $USER_ID -D -S -H ${LOCAL_USER_NAME} -G $DOCKER_GROUP_NAME
    fi
    export HOME=${LOCAL_USER_HOME}

    if [ "$USER_ID" != "$CURRENT_USER" ]; then
        [[ -f /opt/wf-docker-workflow/symfony4/.env ]] && chown -R ${USER_ID} /opt/wf-docker-workflow/symfony4/.env
        [[ -f /opt/wf-docker-workflow/symfony4/var ]] && chown -R ${USER_ID} /opt/wf-docker-workflow/symfony4/var

        su-exec ${LOCAL_USER_NAME} "$@"
    else
        $@
    fi
else
    $@
fi
