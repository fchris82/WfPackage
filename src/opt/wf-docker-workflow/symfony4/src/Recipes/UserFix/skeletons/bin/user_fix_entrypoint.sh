#!/bin/bash

usermod -u ${UID} ${CONTAINER_USER}
groupmod -g ${GID} ${CONTAINER_GROUP}

# Decode base64 encoded var
CONTAINER_ENTRYPOINT=$(echo "${CONTAINER_ENTRYPOINT_B64}" | base64 -d)
${CONTAINER_ENTRYPOINT} ${@}
