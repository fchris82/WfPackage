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

# Build command
function build {
    docker build --no-cache --pull -t ${USER}/wf-user ~/.wf-docker-workflow
}

# Colors
RED=$'\x1B[00;31m'
GREEN=$'\x1B[00;32m'
YELLOW=$'\x1B[00;33m'
WHITE=$'\x1B[01;37m'
BOLD=$'\x1B[1m'
# Clear to end of line: http://www.isthe.com/chongo/tech/comp/ansi_escapes.html
CLREOL=$'\x1B[K'
#-- Vars
RESTORE=$'\x1B[0m'

# --> PRE CHECK
# Docker is installed?
if ! $(dpkg -l | grep -E '^ii' | grep -qw docker); then
    echo "${RED}${BOLD}You need installed ${YELLOW}${BOLD}docker${RED}${BOLD}! First install it!${RESTORE}";
    echo "${RED}${BOLD}Installation failed!${RESTORE}"
    exit 1
fi
# Current user is member in docker group?
if ! $(id -Gn ${USER} | grep -qw "docker"); then
    echo "${RED}${BOLD}You have to be member in ${GREEN}${BOLD}docker${RED}${BOLD} group!${RESTORE}";
    echo "${YELLOW}  1. Run the ${WHITE}sudo usermod -aG docker \$USER${YELLOW} command.${RESTORE}"
    echo "${YELLOW}  2. ${BOLD}Logout${YELLOW} and ${BOLD}login${YELLOW} again! If it doesn't help, you should restart the computer.${RESTORE}"
    echo ""
    echo "${RED}${BOLD}Installation failed!${RESTORE}"
    exit 1
fi
# <-- PRE CHECK

BASE_IMAGE=${1:-"fchris82/wf"}
# Refresh
if [ -f ~/.wf-docker-workflow/Dockerfile ]; then
    build
    IMAGE=${USER}/wf-user
else
    docker pull ${BASE_IMAGE}
    IMAGE=${BASE_IMAGE}
fi

# If we want to use the local and fresh files
if [ -f ${DIR}/packages/wf-docker-workflow/opt/wf-docker-workflow/host/copy_binaries_to_host.sh ]; then
    ${DIR}/packages/wf-docker-workflow/opt/wf-docker-workflow/host/copy_binaries_to_host.sh
# If the docker is available
elif [ -S /var/run/docker.sock ]; then
    # Copy files from image to host. YOU CAN'T USE docker cp COMMAND, because it doesn't work with image name, it works with containers!
    docker run -i \
     -v ~/:${HOME} \
     -e LOCAL_USER_ID=$(id -u) -e LOCAL_USER_NAME=${USER} -e LOCAL_USER_HOME=${HOME} -e USER_GROUP=$(stat -c '%g' /var/run/docker.sock) \
     -e BASE_IMAGE=${BASE_IMAGE} \
     ${IMAGE} \
     /opt/wf-docker-workflow/host/copy_binaries_to_host.sh
fi

# Add commands to path!
COMMAND_PATH=~/.wf-docker-workflow/bin/commands
mkdir -p ~/bin
ln -sf $COMMAND_PATH/* ~/bin

# Build if we haven't done it yet.
if [ "${BASE_IMAGE}" == "${IMAGE}" ]; then
    build
fi

# Install BASH init script
# @todo On mac: dtruss instead of strace
BASH_FILE_TRACES=$(echo exit | strace bash -li |& less | grep "^open.*\"$HOME" | cut -d'"' -f2);
OLDIFS=$IFS
IFS=$'\n'
for file in $BASH_FILE_TRACES; do
    if [ -f "$file" ]; then
        BASH_PROFILE_FILE="$file"
        break
    fi
done
IFS=$OLDIFS
if [ -f "$BASH_PROFILE_FILE" ] && [ "$(basename "$BASH_PROFILE_FILE")" != ".bash_history" ] \
    && [ $(cat $BASH_PROFILE_FILE | egrep "^[^#]*source[^#]*/.wf-docker-workflow/bin/bash/extension.sh" | wc -l) == 0 ]; then
        echo -e "\n# WF extension\nsource ~/.wf-docker-workflow/bin/bash/extension.sh\nsource ~/.wf-docker-workflow/bin/bash/autocomplete.sh\n" >> $BASH_PROFILE_FILE
        # Reload the shell if it needs
        if [ "$(basename "$SHELL")" == "bash" ]; then
            echo -e "${GREEN}We register the the BASH autoload extension in the ${YELLOW}${BASH_PROFILE_FILE}${GREEN} file!${RESTORE}"
            echo -e "${GREEN}You have to run to reload shell: ${WHITE}${BOLD}source ${BASH_PROFILE_FILE}${RESTORE}"
        else
            echo "INFO: We register the the BASH autoload extension in the ${BASH_PROFILE_FILE} file!"
        fi
fi

# Install ZSH init script and autocomplete
if [ -f ~/.zshrc ]; then
    mkdir -p ~/.zsh/completion
    ln -sf ~/.wf-docker-workflow/bin/zsh/autocomplete.sh ~/.zsh/completion/_wf
    if [ $(echo $fpath | egrep ~/.zsh/completion | wc -l) == 0 ] \
        && [ $(cat ~/.zshrc | egrep "^[^#]*source[^#]*/.wf-docker-workflow/bin/zsh/extension.sh" | wc -l) == 0 ]; then
            echo -e "\n# WF extension\nsource ~/.wf-docker-workflow/bin/zsh/extension.sh\n" >> ~/.zshrc
            echo -e "${GREEN}We register the the ZSH autoload extension in the ${YELLOW}~/.zshrc${GREEN} file!${RESTORE}"
            # Reload the shell if it needs
            [[ "$(basename "$SHELL")" == "zsh" ]] && echo -e "${GREEN}You have to run to reload shell: ${WHITE}${BOLD}source ~/.zshrc${RESTORE}"
    fi
else
    echo "INFO: You don't have installed the zsh! Nothing changed."
fi

GLOBAL_IGNORE=(/.wf /.wf.yml /.docker.env)
# Install gitignore
git --version 2>&1 >/dev/null # improvement by tripleee
GIT_IS_AVAILABLE=$?
if [ $GIT_IS_AVAILABLE -eq 0 ]; then
    GITIGNORE_FILE=$(bash -c "echo $(git config --get core.excludesfile)")
    # if it doesn't exist, create global gitignore file
    if [ -z $GITIGNORE_FILE ]; then
        touch ~/.gitignore
        git config --global core.excludesfile '~/.gitignore'
        GITIGNORE_FILE=$(bash -c "echo $(git config --get core.excludesfile)")
    fi
    if [ ! -z $GITIGNORE_FILE ] && [ -f $GITIGNORE_FILE ]; then
        for ignore in "${GLOBAL_IGNORE[@]}"
        do
            if ! grep -q ^${ignore}$ $GITIGNORE_FILE; then
                echo $ignore >> $GITIGNORE_FILE
                echo -e "${GREEN}We added the ${YELLOW}${ignore}${GREEN} path to ${YELLOW}${GITIGNORE_FILE}${GREEN} file${RESTORE}"
            fi
        done
    else
        echo -e "${YELLOW}You don't have global ${GREEN}.gitignore${YELLOW} file! Nothing changed.${RESTORE}"
    fi
else
    echo -e "INFO: You don't have installed the git."
fi

# Clean / Old version upgrade
[[ -f ~/.wf-docker-workflow/config/config ]] && rm -f ~/.wf-docker-workflow/config/config*
[[ -d ~/.wf-docker-workflow/recipes ]] \
    && rsync --remove-source-files -a -v ~/.wf-docker-workflow/recipes/* ~/.wf-docker-workflow/extensions/recipes \
    && rm -rf ~/.wf-docker-workflow/recipes
[[ -d ~/.wf-docker-workflow/wizards ]] \
    && rsync --remove-source-files -a -v ~/.wf-docker-workflow/wizards/* ~/.wf-docker-workflow/extensions/wizards \
    && rm -rf ~/.wf-docker-workflow/wizards
[[ -f ~/.wf-docker-workflow/bin/zsh_autocomplete.sh ]] && rm -f ~/.wf-docker-workflow/bin/zsh_autocomplete.sh
[[ -f ~/.wf-docker-workflow/bin/zsh/zsh_autocomplete.sh ]] && rm -f ~/.wf-docker-workflow/bin/zsh/zsh_autocomplete.sh
# todo Remove autocomplete from ~/.zshrc file

echo -e "${GREEN}Install success${RESTORE}"
