#!/bin/bash

LOCKFILE="/var/lock/`basename $0`"

function escape {
    C=();
    whitespace="[[:space:]]"
    for i in "$@"
    do
        if [[ $i =~ $whitespace ]]
        then
            i=\"$i\"
        fi
        C+=("$i")
    done
    echo "${C[*]//\"/\\\"}"
}

function lock {
    if [ -f $LOCKFILE ]; then
        CLASS=$'\x1B[31;107m'
        echo -e "\n${CLASS}${CLREOL}"
        echo -e "${CLREOL}"
        echo -e "\e[1m SCRIPT IS LOCKED!${CLREOL}"
        echo -e " =================\e[0m${CLASS}${CLREOL}"
        echo -e "${CLREOL}"
        echo -e " The script is running now by \e[30;42m$(stat -c \"%U\" $LOCKFILE)${CLASS}!${CLREOL}"
        echo -e " If you are sure that the lock file is 'wrong' - don't running a script - delete it by hand: \e[43mrm -f ${LOCKFILE}${CLASS}${CLREOL}"
        echo -e "${CLREOL}${RESTORE}\e[0m\n"
        exit 1
    else
        touch ${LOCKFILE} || quit
    fi
}

function cleanup {
    if [ -f ${LOCKFILE} ]; then
        rm -f ${LOCKFILE}
    fi
    return $?
}

function quit {
    exitcode=$?
    cleanup
    echo_block "31;107m" " Something went wrong! The script doesn't run down!"
    echo "${YELLOW}If you need some help call the ${BOLD}${WHITE}wf -h${RESTORE}${YELLOW} command!${RESTORE}"
    exit $exitcode
}

# You can manage some make parameters with these env variables
# Eg: MAKE_DISABLE_SILENCE=1 MAKE_DEBUG_MODE=1 MAKE_ONLY_PRINT=1 wf list
function make_params {
    PARAMS="";
    if [[ -z "$MAKE_DISABLE_SILENC" && ${WF_DEBUG:-0} -lt 1 ]]; then
        PARAMS="${PARAMS} -s --no-print-directory"
    fi
    if [[ "$MAKE_DEBUG_MODE" -eq "1" || ${WF_DEBUG:-0} -ge 3 ]]; then
        PARAMS="${PARAMS} -d"
    elif [ ! -z "$MAKE_DEBUG_MODE" ]; then
        PARAMS="${PARAMS} --debug=${MAKE_DEBUG_MODE}";
    elif [ ${WF_DEBUG:-0} -eq 2 ]; then
        PARAMS="${PARAMS} --debug=v";
    fi
    if [ ! -z "$MAKE_ONLY_PRINT" ]; then
        PARAMS="${PARAMS} -n"
    fi

    echo $PARAMS
}

# You can find a directory up (first, closer)
function find-up-first {
    local _path_=$(pwd)
    while [[ "$_path_" != "" && ! -d "$_path_/$1" ]]; do
        _path_=${_path_%/*}
    done
    echo "$_path_"
}

# You can find a directory up (last)
# We use it to find the top .svn or .git directory.
function find-up-last {
    local _path_=$(pwd)
    local _last_=""
    while [[ "$_path_" != "" ]]; do
        if [[ -d "$_path_/$1" ]]; then
            _last_=$_path_;
        fi
        _path_=${_path_%/*}
    done

    if [[ -z "$_last_" ]]; then
        false
    else
        echo "$_last_"
    fi
}

# Try to find the project root directory
function get_project_root_dir {
    echo $(find-up-last .git || find-up-last .hg || find-up-last .svn || pwd)
}

# Find the project makefile:
#  1. .wf.yml --> makefile
#  2. .wf.yml.dist --> makefile
#  3. .docker.env.makefile
#  4. .project.makefile
function find_project_makefile {
    PROJECT_CONFIG_FILE=$(get_project_configuration_file "${PROJECT_ROOT_DIR}/${WF_CONFIGURATION_FILE_NAME}")
    if [ "${PROJECT_CONFIG_FILE}" == "null" ]; then
        # If we are using "hidden" docker environment...
        local DOCKER_ENVIRONEMNT_MAKEFIILE="${PROJECT_ROOT_DIR}/.docker.env.makefile"
        # If we are using old version
        local OLD_PROJECT_MAKEFILE="${PROJECT_ROOT_DIR}/.project.makefile"
        if [ -f "${DOCKER_ENVIRONEMNT_MAKEFIILE}" ]; then
            PROJECT_MAKEFILE="${DOCKER_ENVIRONEMNT_MAKEFIILE}";
        elif [ -f "${OLD_PROJECT_MAKEFILE}" ]; then
            PROJECT_MAKEFILE="${OLD_PROJECT_MAKEFILE}";
        else
            echo_fail "We didn't find any project makefile in this path: ${PROJECT_ROOT_DIR}"
            quit
        fi
    else
        create_makefile_from_config
    fi

    # Deploy esetén nem biztos, hogy van .git könyvtár, ellenben ettől még a projekt fájl létezhet
    if [ "${PROJECT_ROOT_DIR}" == "." ] && [ ! -f "${PROJECT_MAKEFILE}" ]; then
        echo_fail "You are not in project directory! Git top level is missing!"
        quit
    fi
}

# If `.wf.yml` doesn't exist but the `.wf.yml.dist` does
function get_project_configuration_file {
    local _path_="null"
    if [ -f "${1}" ]; then
        _path_="${1}";
    elif [ -f "${1}.dist" ]; then
        _path_="${1}.dist";
    fi

    echo ${_path_};
}

function create_makefile_from_config {
    # Config version
    local CONFIG_HASH=$(get_project_config_hash)
    PROJECT_MAKEFILE="${PROJECT_ROOT_DIR}/${WF_WORKING_DIRECTORY_NAME}/${CONFIG_HASH}.${WF_VERSION}.mk"
    if [ ! -f "${PROJECT_MAKEFILE}" ] || [ "${FORCE_OVERRIDE}" == "1" ]; then
        php ${SYMFONY_CONSOLE} app:config \
            --file ${PROJECT_CONFIG_FILE} \
            --target-directory ${WF_WORKING_DIRECTORY_NAME} \
            --config-hash ${CONFIG_HASH}.${WF_VERSION} \
            --wf-version ${WF_VERSION} \
            ${PROJECT_ROOT_DIR} ${SYMFONY_DISABLE_TTY} ${SYMFONY_COMMAND_DEBUG} \
            ${@} || quit
    fi
}

# Try to find the all config files and environment file + calc checksum
# This function does call always. I created a cache to make faster. The test results:
#  - cache build (without cache): ~0.13s
#  - using cache (if exists): ~0.02s
# As you can see, the cache could be much faster.
# The cache file will be generated to `[project]/.wf/.chksum.cache`.
#  - first line: the configuration files list (base + import files)
#  - second line: calculated checksum
function get_project_config_hash {
    if ! _check_config_hash_cache; then
        local CONFIG_FILES=$(_parseConfigFileList ${PROJECT_CONFIG_FILE})
        local CKSUM=$(_calc_cksum ${CONFIG_FILES})

        _create_config_hash_cache "${CONFIG_FILES}" "${CKSUM}"

        echo ${CKSUM}
    else
        _get_hash_from_cache
    fi
}

# Calc the checksum: ENV + config files
# @param Config file list from `_parseConfigFileList()` function
function _calc_cksum {
    # Env file
    if [ -f "${PROJECT_ROOT_DIR}/${WF_ENV_FILE_NAME}" ]; then
        ENV_FILE="${PROJECT_ROOT_DIR}/${WF_ENV_FILE_NAME}"
    fi

    cksum ${@} ${ENV_FILE} | cksum | awk '{ print $1 }'
}

# Check: are the used config files changed?
function _check_config_hash_cache {
    local CACHE_FILE_PATH="${PROJECT_ROOT_DIR}/${WF_WORKING_DIRECTORY_NAME}/.chksum.cache"

    [[ "${FORCE_OVERRIDE}" != "1" ]] && [[ -f ${CACHE_FILE_PATH} ]] && [[ "$(_calc_cksum $(head -n 1 ${CACHE_FILE_PATH}))" == $(tail -n +2 ${CACHE_FILE_PATH}) ]]
}

# Create the hash cache file to `[project]/.wf/.chksum.cache`
#
# @param $1 Used config file list
# @param $2 HASH
function _create_config_hash_cache {
    mkdir -p ${PROJECT_ROOT_DIR}/${WF_WORKING_DIRECTORY_NAME};
    local CACHE_FILE_PATH="${PROJECT_ROOT_DIR}/${WF_WORKING_DIRECTORY_NAME}/.chksum.cache"
    echo -e "${1}\n${2}" > ${CACHE_FILE_PATH}
}

# Read HASH from the second line of the cache file
function _get_hash_from_cache {
    local CACHE_FILE_PATH="${PROJECT_ROOT_DIR}/${WF_WORKING_DIRECTORY_NAME}/.chksum.cache"
    tail -n +2 ${CACHE_FILE_PATH}
}

# Find all imported config files. Parsing YAML a little bit "slow"!
#
# @param $1 Parsing config file
function _parseConfigFileList {
    if [[ ! -z "$(grep -F "imports:" ${1})" ]] && [[ "null" != "$(yq r ${1} imports)" ]]; then
        local IMPORT_FILES=$(yq r ${1} imports \
            | while read -r value; do
                value=${value:2}
                # Absolute path
                if [[ "${value:0:1}" == "/" ]]; then
                    _parseConfigFileList ${value};
                # Relative path
                else
                    _parseConfigFileList ${PROJECT_ROOT_DIR}/${value};
                fi;
              done)
    fi

    echo "${1} ${IMPORT_FILES}";
}

# Handle CTRL + C
trap quit SIGINT
