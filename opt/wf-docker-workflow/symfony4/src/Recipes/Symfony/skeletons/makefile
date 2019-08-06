DOCKER_CLI_NAME		:= {{ names.engine }}
DOCKER_DOC_ROOT		:= {{ project_path }}
SF_CONSOLE_CMD		:= {{ sf_console_command }}
SF_BIN_DIR			:= {{ sf_bin_dir }}
SHARED_DIRS			:= var

XDEBUG_IDE_SERVER_NAME := {{ environment.XDEBUG_IDE_SERVER_NAME | default(server.xdebug_ide_server_name) }}
PHP_IDE_CONFIG := "serverName=${XDEBUG_IDE_SERVER_NAME}"
WF_XDEBUG_ENABLED := {{ server.xdebug ? '1' : '0' }}

ORIGINAL_CMD_DOCKER_ENV := $(CMD_DOCKER_ENV)
define CMD_DOCKER_ENV
    $(ORIGINAL_CMD_DOCKER_ENV) \
    WF_XDEBUG_ENABLED=$(WF_XDEBUG_ENABLED) \
    PHP_IDE_CONFIG=$(PHP_IDE_CONFIG) \
    SYMFONY_DEBUG=$(SYMFONY_DEBUG) \
    SYMFONY_CLASSLOADER_FILE=$(SYMFONY_CLASSLOADER_FILE) \
    SYMFONY_HTTP_CACHE=$(SYMFONY_HTTP_CACHE) \
    SYMFONY_HTTP_CACHE_CLASS=$(SYMFONY_HTTP_CACHE_CLASS) \
    SYMFONY_TRUSTED_PROXIES=$(SYMFONY_TRUSTED_PROXIES) \
    SYMFONY_DEPRECATIONS_HELPER=$(SYMFONY_DEPRECATIONS_HELPER)
endef

.PHONY: php
php: DOCKER_USER="www-data"
php:
	$(CMD_DOCKER_RUN_CLI) php $(ARGS)

.PHONY: php-exec
php-exec: DOCKER_USER="www-data"
php-exec: up
	$(CMD_DOCKER_EXEC_CLI) php $(ARGS)

.PHONY: sf
sf: DOCKER_USER="www-data"
sf: up
	$(CMD_DOCKER_EXEC_CLI) php $(SF_CONSOLE_CMD) $(ARGS)

.PHONY: composer
composer: DOCKER_USER="www-data"
composer:
	$(CMD_DOCKER_RUN_CLI) composer $(ARGS)
