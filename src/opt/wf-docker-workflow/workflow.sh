#!/bin/bash
# Debug mode:
#set -x

# DIRECTORIES
WORKDIR=$(pwd)
SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source ${DIR}/lib/_debug.sh
source ${DIR}/lib/_css.sh
source ${DIR}/lib/_workflow_help.sh
source ${DIR}/lib/_functions.sh

# WF options
# ----------
# First parameters are used by WF!
#
#   wf [OPTIONS] [COMMAND] [COMMAND_OPTIONS]
#
# Example, switch on debug modes:
#   wf -v sf docker:create:database -vvv
#      ^^                           ^^^^
#      Makefile debug mode          Symfony debug mode
#
# Example, add environment:
#   wf -e EXTENSION_DIRS=/var/extensions info
while [[ $# -gt 0 ]]
do
key="$1"

case $key in
    # Add or replace parameter from command line
    -e|--env)
        COMMAND_ENVS="${COMMAND_ENVS:-""} $2"
        shift
        shift
    ;;
    # Debug modes
    -v)
        MAKE_DISABLE_SILENCE=1
        shift
    ;;
    -vvv)
        MAKE_DISABLE_SILENCE=1
        MAKE_DEBUG_MODE=1
        shift
    ;;
    *)
        break
    ;;
esac
done

# WF command
# ----------
case $1 in
    ""|-h|--help)
        showHelp
    ;;
    --version)
        echo "WF Docker Workflow ${WF_VERSION}"
    ;;
    -ps|--docker-ps)
        docker inspect -f "{{printf \"%-30s\" .Name}} {{printf \"%.12s\t\" .Id}}{{index .Config.Labels \"com.wf.basedirectory\"}}" $(docker ps -a -q)
    ;;
    --composer-install)
        shift
        cd ${SYMFONY_PATH} && composer install ${@}
    ;;
    # Clean cache directory. You have to use after put a custom recipe!
    --reload|--clean-cache)
        rm -rf ${DIR}/symfony4/var/cache/*
    ;;
    --enter)
        /bin/bash
    ;;
    --run)
        shift
        ${@}
    ;;
    --sf-run)
        shift
        cd ${SYMFONY_PATH} && ${@}
    ;;
    --config-dump)
        shift
        #eval "$BASE_PROJECT_RUN cli php /opt/wf-docker-workflow/symfony4/bin/console app:config-dump ${@}"
        php ${SYMFONY_CONSOLE} app:config-dump ${@} ${SYMFONY_DISABLE_TTY} ${SYMFONY_COMMAND_DEBUG}
    ;;
    # You can call with symfony command verbose, like: wf reconfigure -v
    reconfigure)
        shift
        PROJECT_ROOT_DIR=$(get_project_root_dir)
        PROJECT_CONFIG_FILE=$(get_project_configuration_file "${PROJECT_ROOT_DIR}/${WF_CONFIGURATION_FILE_NAME}")

        if [ -f "${PROJECT_CONFIG_FILE}" ]; then
            FORCE_OVERRIDE=1
            create_makefile_from_config ${@}
        else
            echo "The ${PROJECT_ROOT_DIR}/${WF_CONFIGURATION_FILE_NAME} doesn't exist."
        fi
    ;;
    # For developing and testing
#    --test)
#        set -x
#        START_TIME=`date +%s%N`
#        PROJECT_ROOT_DIR=$(get_project_root_dir)
#        PROJECT_CONFIG_FILE=$(get_project_configuration_file "${PROJECT_ROOT_DIR}/${WF_CONFIGURATION_FILE_NAME}")
#        echo $(get_project_config_hash)
#        ELAPSED_TIME=$((`date +%s%N` - $START_TIME))
#        echo "$ELAPSED_TIME ns"
#    ;;
    # Project makefile
    *)
        COMMAND="$1"
        shift

        PROJECT_ROOT_DIR=$(get_project_root_dir)
        find_project_makefile || quit

        ARGS=$(escape $@)
        MAKE_EXTRA_PARAMS=$(make_params)

        make ${MAKE_EXTRA_PARAMS} -f ${PROJECT_MAKEFILE} -C ${PROJECT_ROOT_DIR} ${COMMAND} \
            ARGS="${ARGS}" \
            WORKFLOW_BINARY_DIRECTORY="${DIR}/bin" \
            WORKFLOW_MAKEFILE_PATH="${DIR}/versions/Makefile" \
            MAKE_EXTRA_PARAMS="${MAKE_EXTRA_PARAMS}" \
            COMMAND_ENVS="${COMMAND_ENVS:-""}" \
            WF_DEBUG="${WF_DEBUG}" || quit
    ;;
esac
