#!/bin/bash

if [ ${WF_DEBUG:-0} -ge 1 ]; then
    [[ -f /.dockerenv ]] && echo -e "\033[1mDocker: \033[33m${WF_DOCKER_HOST_CHAIN}\033[0m"
    echo -e "\033[1mDEBUG\033[33m $(realpath "$0")\033[0m"
    SYMFONY_COMMAND_DEBUG="-vvv"
    DOCKER_DEBUG="-e WF_DEBUG=${WF_DEBUG}"
fi
[[ ${WF_DEBUG:-0} -ge 2 ]] && set -x

SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

# CREATE DIRECTORIES
echo "Install binaries..."
mkdir -p ${HOME}/.wf-docker-workflow/bin
mkdir -p ${HOME}/.wf-docker-workflow/config

# COPY files, except config (overwrite if exists/replace to the newest)
find ${DIR} -mindepth 1 -maxdepth 1 -type d ! -name config -exec cp -f -R {} "${HOME}/.wf-docker-workflow/" \;
# COPY config directory and Dockerfile (keeps existing files!)
#   1. Create dist files
for f in ${DIR}/config/*; do cp -f "$f" "${HOME}/.wf-docker-workflow/config/$(basename $f).dist"; done
cp -f "${DIR}/Dockerfile" "${HOME}/.wf-docker-workflow/Dockerfile.dist"
#   2. Copy if doesn't exist
cp -an "${DIR}/config/." "${HOME}/.wf-docker-workflow/config/"
cp -an "${DIR}/Dockerfile" "${HOME}/.wf-docker-workflow/Dockerfile"
# Replace base image in Dockerfile
sed -i -e "s|FROM fchris82/wf|FROM ${BASE_IMAGE:-fchris82/wf}|" "${HOME}/.wf-docker-workflow/Dockerfile"

# Symfony cache directory
mkdir -p "${HOME}/.wf-docker-workflow/cache"
rm -rf "${HOME}/.wf-docker-workflow/cache/*"
chmod 777 "${HOME}/.wf-docker-workflow/cache"
