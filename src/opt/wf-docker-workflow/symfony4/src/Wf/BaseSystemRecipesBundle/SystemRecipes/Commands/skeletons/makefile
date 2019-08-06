# It will get like a `/home/user/www/project/.wf/bin/` value with ending `/`
_makefile_path_ := $(abspath $(lastword $(MAKEFILE_LIST)))

{% for commandName, commandPath in makefileTargets %}
.PHONY: {{ commandName }}
{{ commandName }}:
	$(COMMAND_ENVS) $(dir $(_makefile_path_)){{ commandPath }} $(ARGS)

{% endfor %}
