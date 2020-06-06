define DEV_SH_FILE_CONTENT
#!/bin/bash
# Debug mode
#set -x

DEV="--dev"
WF_DEBUG=0
while [ "$${1:0:1}" == "-" ]; do
    case "$$1" in
        --no-dev)
            DEV=""
            ;;
        -v)
            WF_DEBUG=1
            ;;
        -vv)
            WF_DEBUG=2
            ;;
        -vvv)
            WF_DEBUG=3
            ;;
        *)
            echo "Invalid argument: '$$1' Usage: $$0 {--no-dev|-v|-vv|-vvv} ..."
            exit 1
    esac
    shift
done

# COMMAND
CMD=$$1
shift

WF_DEBUG=$${WF_DEBUG} $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))/src/opt/wf-docker-workflow/host/bin/workflow_runner.sh --develop $$CMD $$DEV $$@
endef
export DEV_SH_FILE_CONTENT

# Create a symlink file to user ~/bin directory.
.PHONY: init-developing
init-developing: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
init-developing:
	mkdir -p ~/bin
	@echo "$$DEV_SH_FILE_CONTENT" > ~/bin/wfdev && chmod +x ~/bin/wfdev
	$(MAKEFILE_PATH)/src/opt/wf-docker-workflow/host/bin/workflow_runner.sh --develop wf --dev --composer-install --dev

# Upgrade the version number.
.PHONY: __versionupgrade_wf
__versionupgrade_wf: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
__versionupgrade_wf:
    # We automatically change in master and develop branch!
    # Don't use variable in ifeq! The $(shell) is only way!
    ifneq ($(shell git rev-parse --abbrev-ref HEAD),master)
        ifneq ($(shell git rev-parse --abbrev-ref HEAD),develop)
			$(eval nochange = 1)
        endif
    endif
    ifeq (,$(KEEPVERSION))
        ifeq (,$(VERSION))
            # Original Version + New Version
			@if [ -z "$(nochange)" ]; then ov=$$(grep WF_VERSION $(MAKEFILE_PATH)/Dockerfile | egrep -o '[0-9\.]*'); \
				nv=$$(echo "$${ov%.*}.$$(($${ov##*.}+1))"); \
				sed -i -e "s/ENV WF_VERSION=*$${ov}/ENV WF_VERSION=$${nv}/" $(MAKEFILE_PATH)/Dockerfile; \
				echo "Version: $${nv}"; \
			fi
        else
			sed -i -e "s/ENV WF_VERSION=*$${ov}/ENV WF_VERSION=$(VERSION)/" $(MAKEFILE_PATH)/Dockerfile; \
				echo "Version: $(VERSION)"
        endif
    endif

# We skip the ".gitignored" files. We copy everything to a tmp directory, and we will delete it in the `__build_cleanup` command
# @see https://stackoverflow.com/a/50059607/99834
.PHONY: __build_rsync
__build_rsync: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
__build_rsync: GIT_ROOT := $$(git rev-parse --show-toplevel)
__build_rsync: RELATIVE_SRC_PATH=$(shell realpath --relative-to="$(CURDIR)" "$(MAKEFILE_PATH)/src")
__build_rsync:
	mkdir -p $(MAKEFILE_PATH)/.tmp
	rsync -r --delete --delete-excluded --delete-before --force \
        --exclude=.git \
        --exclude-from="$$(git -C $(RELATIVE_SRC_PATH) ls-files \
            --exclude-standard -oi --directory >$(GIT_ROOT)/.git/ignores.tmp && \
            echo $(GIT_ROOT)/.git/ignores.tmp)" \
        $(MAKEFILE_PATH)/src/* $(MAKEFILE_PATH)/.tmp

.PHONY: __build_cleanup
__build_cleanup: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
__build_cleanup:
	rm -rf $(MAKEFILE_PATH)/.tmp

.PHONY: __get_image_tag
# Don't use this variable in ifeq!!!
__get_image_tag: GIT_BRANCH := $(shell git rev-parse --abbrev-ref HEAD)
__get_image_tag:
    # Don't use variable in ifeq! The $(shell) is only way!
    ifeq ($(shell git rev-parse --abbrev-ref HEAD),master)
		$(eval IMAGE=fchris82/wf)
    else
		$(eval IMAGE=$(shell echo "fchris82/wf:$$(basename $(GIT_BRANCH))"))
    endif

# Create a docker image
.PHONY: __build_docker
__build_docker: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
__build_docker: __versionupgrade_wf __get_image_tag
	docker build --no-cache -t $(IMAGE) $(MAKEFILE_PATH)

# Create a docker image
.PHONY: build_docker
build_docker: __build_rsync __build_docker __build_cleanup

# Create a docker image with cache
.PHONY: fast_build_docker
fast_build_docker: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
fast_build_docker: __get_image_tag
	docker build -t $(IMAGE) $(MAKEFILE_PATH)

# Push docker image
.PHONY: push_docker
push_docker: USER_IS_LOGGED_IN := `cat ~/.docker/config.json | jq '.auths."https://index.docker.io/v1/"'`
push_docker: __get_image_tag
	if [ ! -f ~/.docker/config.json ] || [ "$(USER_IS_LOGGED_IN)" = "null" ]; then \
		docker login; \
	fi
	docker push $(IMAGE)

# DEV!
.PHONY: enter
enter: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
enter:
	$(MAKEFILE_PATH)/src/opt/wf-docker-workflow/host/bin/workflow_runner.sh /bin/bash

.PHONY: phpunit
phpunit:
	~/bin/wfdev wf --sf-run vendor/bin/phpunit

phpunit-coverage: MAKEFILE_PATH := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
phpunit-coverage:
	~/bin/wfdev wf --sf-run vendor/bin/phpunit --coverage-html phpcoverage \
	&& echo "Coverage dir: \033[33m$(MAKEFILE_PATH)/src/opt/wf-docker-workflow/symfony4/phpcoverage\033[0m"

.PHONY: phpcsfix
phpcsfix:
	~/bin/wfdev wf --sf-run vendor/bin/php-cs-fixer fix
