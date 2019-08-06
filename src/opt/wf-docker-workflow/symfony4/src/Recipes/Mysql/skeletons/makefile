DOCKER_DB_NAME := mysql

DB_PASSWORD := {{ environment.MYSQL_ROOT_PASSWORD | default(password) }}
DB_NAME     := {{ environment.MYSQL_DATABASE | default(database) }}
DB_URL      := mysql://root:$(DB_PASSWORD)@$(DOCKER_DB_NAME)/$(DB_NAME)

ORIGINAL_CMD_DOCKER_ENV := $(CMD_DOCKER_ENV)
define CMD_DOCKER_ENV
    $(ORIGINAL_CMD_DOCKER_ENV) \
    MYSQL_ROOT_PASSWORD=$(DB_PASSWORD) \
    DB_NAME=$(DB_NAME) \
    DATABASE_URL=$(DB_URL)
endef

.PHONY: mysql
mysql: DEFAULT_CHARSET := $(if $(DB_CHARSET),$(DB_CHARSET),utf8)
mysql: up
	$(CMD_DOCKER_EXEC) $(DOCKER_DB_NAME) mysql -u root --password="$(DB_PASSWORD)" -h localhost --default-character-set=$(DEFAULT_CHARSET) $(DB_NAME)

.PHONY: __mysql_connect
__mysql_connect: DEFAULT_CHARSET := $(if $(DB_CHARSET),$(DB_CHARSET),utf8)
__mysql_connect: up
	$(CMD_DOCKER_EXEC) $(DOCKER_DB_NAME) mysql -u root --password="$(DB_PASSWORD)" -h localhost --default-character-set=$(DEFAULT_CHARSET) $(DB_NAME)

.PHONY: dbdump
dbdump : FILE := $(if $(ARGS),$(ARGS),dbdump.sql)
dbdump : up
	$(call DOCKER_EXEC,$(DOCKER_DB_NAME),\
		/usr/bin/mysqldump -u root --password=$(DB_PASSWORD) --add-drop-database -B $(DB_NAME)) > $(FILE)
	@echo -e "\n\033[32m✔ The \033[33m$(FILE)\033[32m has been created.\033[0m"

.PHONY: dbimport
dbimport : FILE := $(if $(ARGS),$(ARGS),dbdump.sql)
dbimport : up
	cat $(FILE) | $(call DOCKER_EXEC,$(DOCKER_DB_NAME),\
		/usr/bin/mysql -u root --password=$(DB_PASSWORD) $(DB_NAME))
