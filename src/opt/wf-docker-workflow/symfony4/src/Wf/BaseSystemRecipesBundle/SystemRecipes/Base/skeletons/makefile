VERSION						:=	{{ config.version.base }}
PROJECT_DIRECOTRY			:=	.
DOCKER_BASENAME				:=	$${LOCAL_USER_NAME:-base}p{{ config.name | default('$${PWD\#\#*/}') }}
DOCKER_DATA_DIR				:=	{{ config.docker_data_dir }}
DOCKER_ENVIRONMENTS	:=
DOCKER_CLI_NAME		:=	engine
# The owner of the project
WWW_DATA_UID		:=	$$(stat -c '%u' .)
# Az Docker group
WWW_DATA_GID		:=	$$(stat -c '%g' /var/run/docker.sock)
# Docker user
SSH_PATH			:= ~/.ssh
DOCKER_USER			:=	$(WWW_DATA_UID):$(WWW_DATA_GID)
DOCKER_SHELL		:=	/bin/bash
SHARED_DIRS			:=

# Because of the Gitlab CI eg.
DOCKER_PSEUDO_TTY := $$(if [ "$$WF_TTY" == "0" ]; then  echo '-T'; fi)
# Load `.wf.env` config file. You have to use \ before # sign!
#   - grep -v '^ *#'      Get everything except the comment lines
#   - tr '\n' ' '         Replace all \n to space
#   - sed -r 's: +: :g'   Replace all multispace to one
FILE_ENVS         := $(shell if [ -f $(WF_ENV_FILE_NAME) ]; then cat $(WF_ENV_FILE_NAME) | grep -v '^ *\#' | tr '\n' ' ' | sed -r 's: +: :g'; fi)

# Ennek muszáj elől lennie, mert szeretnénk, ha ez lenne az első és alapértelmezett "target"
define PROJECT_HELP
\033[1;97m
This is a Docker Workflow project!
===================================
\033[0m
    \033[33mlist\033[0m          \033[32mShow all workflow commands of the project\033[0m
    \033[33mhelp\033[0m          \033[32mShow this help\033[0m
    \033[33minfo\033[0m          \033[32mShow some important project info\033[0m
\033[1;97m
Install the project
\033[0m
  1. \033[94m> wf init\033[0m
  2. \033[97mEdit the new files!\033[0m
  3. \033[94m> wf install\033[0m

  You can use the \033[33mdbreload\033[0m, \033[33mdbdump\033[0m and \033[33mdbimport\033[0m commands.
\033[1;97m
Working
\033[0m
    \033[33mfeature           \033[32mStart a new feature branch from $(GITFLOW_DEVELOP). Set the "suffix" only which is required.
                    Eg: \033[94m> wf feature [--from-this] [--disable-db] [--reload-db] PR-12\033[0m --> \033[97mfeature/PR-12\033[0m
                    Enabled parameters:
                        \033[33m--from-this\033[0m   Ha meg van adva, akkor nem a `develop` branch-ből indít, hanem az aktuálisból
                        \033[33m--disable-db\033[0m  Ha meg van adva, akkor az adatbázishoz nem nyúl hozzá.
                        \033[33m--reload-db\033[0m   Ha meg van adva, akkor újratölti a teljes adatbázist (drop -> create -> migrations -> fixtures)
    \033[33mhotfix            \033[32mStart a new hotfix branch from master branch. Set the "suffix" only which is required.
                    Eg: \033[94m> wf hotfix [--from-this] [--disable-db] [--reload-db] PR-12\033[0m --> \033[97mhotfix/PR-12\033[0m
                    Enabled parameters:
                        \033[33m--from-this\033[0m   Ha meg van adva, akkor nem a `develop` branch-ből indít, hanem az aktuálisból
                        \033[33m--disable-db\033[0m  Ha meg van adva, akkor az adatbázishoz nem nyúl hozzá.
                        \033[33m--reload-db\033[0m   Ha meg van adva, akkor újratölti a teljes adatbázist (drop -> create -> migrations -> fixtures)
    \033[33mpush              \033[32mPush the current branch.\033[0m
    \033[33mpublish           \033[32mPUSH and create merge requiest in Gitlab. You don't have to call push before it.\033[0m
    \033[33mgitlab-list-env   \033[32mList the available gitlab environments.\033[0m
    \033[33mgitlab-clear-env  \033[32mDelete the empty environments.\033[0m
\033[1;97m
Docker commands
\033[0m
    \033[33menter\033[0m                \033[32mOpen the project $(DOCKER_CLI_NAME) shell or you can connect other container\033[0m
    \033[33mexec\033[0m                 \033[32mExecute a command in running container. You can connect eg:\033[0m
                           wf exec <container> <shell>
                           \033[32mEg: \033[94m> wf exec mysql /bin/bash\033[0m
    \033[33mrun\033[0m                  \033[32mRun a command in container. Use the --rm!\033[0m
                           wf run --rm <container> <shell>
                           \033[32mEg: \033[94m> wf run --rm engine php -v\033[0m
    \033[33mdebug-docker-config\033[0m  \033[32mShow the current docker config settings\033[0m
    \033[33mps\033[0m                   \033[32mShow runing project containers\033[0m
    \033[33mup\033[0m                   \033[32mStart project containers\033[0m
    \033[33mdown\033[0m                 \033[32mStop project containers\033[0m
    \033[33mrebuild\033[0m              \033[32mRebuild project \033[0m$(DOCKER_CLI_NAME)\033[32m container\033[0m
    \033[33mlogs\033[0m                 \033[32mYou can show the container logs\033[0m
                           wf logs <container> [...]
                           \033[32mEg: \033[94m> wf logs web\033[32m --> you can show the web logs.\033[0m
    \033[33mdocker-compose\033[0m       \033[32mYou can run custom docker-compose command.\033[0m
                           wf docker-compose <command> [...]
                           \033[32mEg: \033[94m> wf docker-compose up web\033[32m --> you can debug the start\033[0m
\033[1;97m
Helper commands
\033[0m
    \033[33mphp\033[0m             \033[32mRUN php command. You have to use the docker container file structure\033[0m
    \033[33mcomposer\033[0m        \033[32mEXEC a composer command\033[0m
    \033[33msf\033[0m              \033[32mEXEC a symfony console command\033[0m
    \033[33mmysql\033[0m           \033[32mConnect to the database\033[0m
    \033[33mdbreload\033[0m        \033[32mReload the project database with fixtures\033[0m
    \033[33mdbdump\033[0m          \033[32mMake a database dump\033[0m
    \033[33mdbimport\033[0m        \033[32mImport database\033[0m

    \033[33mdeploy\033[0m          \033[32mDeploy...\033[0m

    \033[33mdebug-*\033[0m         \033[32mDebug commands\033[0m

endef
export PROJECT_HELP
.PHONY: help
help:
	@echo -e "$$PROJECT_HELP"

BASE_MAKEFILE_PATH	:=	$(WORKFLOW_MAKEFILE_PATH).$(VERSION)
include $(BASE_MAKEFILE_PATH)

BASE_MAKEFILE   := $(firstword $(MAKEFILE_LIST))
# The base makefile location is: `[PROJECT BASE DIRECTORY]/.wf/` directory.
BASE_DIRECTORY  := $$(realpath $$(dirname $(abspath $(BASE_MAKEFILE)))/..)
CMD_MAKE        := $(MAKE) $(MAKE_EXTRA_PARAMS) -C $(BASE_DIRECTORY) -f $(BASE_MAKEFILE) $(MAKEOVERRIDES)

# We are using `define` because we can override or extend them before make targets.
define CMD_DOCKER_ENV
    $(DOCKER_ENVIRONMENTS) \
    BASE_DIRECTORY=$(BASE_DIRECTORY) \
    PROJECT_DIR_NAME=$(PROJECT_DIRECOTRY) \
    PROJECT_COMPOSE_DIR=$(BASE_DIRECTORY)/$(DOCKER_PROVISIONING) \
    DOCKER_DATA_DIR=$(DOCKER_DATA_DIR) \
    DOCKER_DOC_ROOT=$(DOCKER_DOC_ROOT) \
    DOCKER_USER=$(DOCKER_USER) \
    LOCAL_USER_ID=$${LOCAL_USER_ID} \
    LOCAL_USER_NAME=$${LOCAL_USER_NAME} \
    LOCAL_USER_HOME=$${LOCAL_USER_HOME} \
    WF_HOST_TIMEZONE=$${WF_HOST_TIMEZONE} \
    WF_HOST_LOCALE=$${WF_HOST_LOCALE} \
    USER_GROUP=$${USER_GROUP} \
    APP_ENV=$${APP_ENV} \
    XDEBUG_ENABLED=$${XDEBUG_ENABLED} \
    WF_DOCKER_HOST_CHAIN="$${WF_DOCKER_HOST_CHAIN}$$(hostname) " \
    WF_DEBUG=$${WF_DEBUG} \
    SSH_PATH=$(SSH_PATH) \
    WWW_DATA_UID=$(WWW_DATA_UID) \
    WWW_DATA_GID=$(WWW_DATA_GID) \
    PHP_VERSION=$(PHP_VERSION) \
    SUDO=$(SUDO) \
    CI=$${CI} \
    DOCKER_RUN=$${DOCKER_RUN} \
    $(COMMAND_ENVS)
endef

.PHONY: logs
logs: __docker_logs

.PHONY: up
up: __docker_up

.PHONY: down
down: __docker_down

.PHONY: enter
enter: __docker_enter

.PHONY: debug-enter
debug-enter: __docker_debug_enter

.PHONY: exec
exec: __docker_exec

.PHONY: run
run: __docker_run

.PHONY: run
sudo-run: __docker_sudo_run

.PHONY: rebuild
rebuild: __docker_rebuild

.PHONY: ps
ps: __docker_ps

.PHONY: debug-docker-config
debug-docker-config: __docker_docker-config
