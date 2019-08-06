#!/usr/bin/env bash

[[ ${WF_DEBUG:-0} -ge 2 ]] && set -x

cd {{ project_path }}
{{ commands | join("\n") }}
