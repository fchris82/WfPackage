{% for name, db_config in databases %}
DB_PASSWORD_{{ name }} := {{ db_config.password }}
DB_NAME_{{ name }}     := {{ db_config.database }}
DB_URL_{{ name }}      := mysql://root:$(DB_PASSWORD_{{ name }})@{{ name }}/$(DB_NAME_{{ name }})
{% endfor %}

ORIGINAL_CMD_DOCKER_ENV := $(CMD_DOCKER_ENV)
define CMD_DOCKER_ENV
    $(ORIGINAL_CMD_DOCKER_ENV) \
{% for name, db_config in databases %}
    MYSQL_ROOT_PASSWORD_{{ name }}=$(DB_PASSWORD_{{ name }}) \
    DB_NAME_{{ name }}=$(DB_NAME_{{ name }}) \
    DATABASE_URL_{{ name }}=$(DB_URL_{{ name }}) \
{% endfor %}
    MYSQL_ROOT_PASSWORD=$(DB_PASSWORD_{{ databases|keys|first }}) \
    DB_NAME=$(DB_NAME_{{ databases|keys|first }}) \
    DATABASE_URL=$(DB_URL_{{ databases|keys|first }})
endef

.PHONY: mysql
mysql: DOCKER_DB_NAME := $(if $(ARGS),$(ARGS),{{ databases|keys|first }})
mysql: DB_PASSWORD := $(DB_PASSWORD_$(DOCKER_DB_NAME))
mysql: DB_NAME := $(DB_NAME_$(DOCKER_DB_NAME))
mysql: DEFAULT_CHARSET := $(if $(DB_CHARSET),$(DB_CHARSET),utf8)
mysql: up
	$(CMD_DOCKER_EXEC) $(DOCKER_DB_NAME) mysql -u root --password="$(DB_PASSWORD)" -h localhost --default-character-set=$(DEFAULT_CHARSET) $(DB_NAME)

.PHONY: __mysql_connect
__mysql_connect: DOCKER_DB_NAME := $(if $(ARGS),$(ARGS),{{ databases|keys|first }})
__mysql_connect: DB_PASSWORD := $(DB_PASSWORD_$(DOCKER_DB_NAME))
__mysql_connect: DB_NAME := $(DB_NAME_$(DOCKER_DB_NAME))
__mysql_connect: DEFAULT_CHARSET := $(if $(DB_CHARSET),$(DB_CHARSET),utf8)
__mysql_connect: up
	$(CMD_DOCKER_EXEC) $(DOCKER_DB_NAME) mysql -u root --password="$(DB_PASSWORD)" -h localhost --default-character-set=$(DEFAULT_CHARSET) $(DB_NAME)

.PHONY: dbdump
dbdump: ARG_DB_NAME := $(word 1, $(ARGS))
dbdump: ARG_FILENAME := $(word 2, $(ARGS))
dbdump: DOCKER_DB_NAME := $(if $(ARG_DB_NAME),$(ARG_DB_NAME),{{ databases|keys|first }})
dbdump: FILE := $(if $(ARG_FILENAME),$(ARG_FILENAME),dbdump_$(DOCKER_DB_NAME).sql)
dbdump: DB_PASSWORD := $(DB_PASSWORD_$(DOCKER_DB_NAME))
dbdump: DB_NAME := $(DB_NAME_$(DOCKER_DB_NAME))
dbdump : up
	$(call DOCKER_EXEC,$(DOCKER_DB_NAME),\
		/usr/bin/mysqldump -u root --password=$(DB_PASSWORD) --add-drop-database -B $(DB_NAME)) > $(FILE)
	@echo -e "\n\033[32m✔ The \033[33m$(FILE)\033[32m has been created.\033[0m"

.PHONY: dbimport
dbimport: ARG_DB_NAME := $(word 1, $(ARGS))
dbimport: ARG_FILENAME := $(word 2, $(ARGS))
dbimport: DOCKER_DB_NAME := $(if $(ARG_DB_NAME),$(ARG_DB_NAME),{{ databases|keys|first }})
dbimport: FILE := $(if $(ARG_FILENAME),$(ARG_FILENAME),dbdump_$(DOCKER_DB_NAME).sql)
dbimport: DB_PASSWORD := $(DB_PASSWORD_$(DOCKER_DB_NAME))
dbimport: DB_NAME := $(DB_NAME_$(DOCKER_DB_NAME))
dbimport : up
	cat $(FILE) | $(call DOCKER_EXEC,$(DOCKER_DB_NAME),\
		/usr/bin/mysql -u root --password=$(DB_PASSWORD) $(DB_NAME))
