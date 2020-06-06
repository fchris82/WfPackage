read -r -d '' HELP <<-EOM
${BOLD}${WHITE}
↻ WF Docker Workflow
====================
${RESTORE}
Debug modes:

  -v                                ${GREEN}Set ${RESTORE}MAKE_DISABLE_SILENCE=1${GREEN} ${BOLD}You must set it first!${RESTORE}
  -vvv                              ${GREEN}Set ${RESTORE}MAKE_DISABLE_SILENCE=1 MAKE_DEBUG_MODE=1${GREEN} ${BOLD}You must set it first!${RESTORE}

Eg: ${YELLOW}wf ${BOLD}-v${YELLOW} help${RESTORE}

Anywhere:

  -h    --help                      ${GREEN}Show this help${RESTORE}
  -u    --update                    ${GREEN}Self update. ${BOLD}You need sudo permission!${RESTORE}
        --reload | --clean-cache    ${GREEN}Clean WF cache.${RESTORE}
        --config-dump               ${GREEN}List all configuration. Modifiers:${RESTORE}
                ${YELLOW}--only-recipes${RESTORE}      List only the recipe names.
                ${YELLOW}--recipe=symfony3${RESTORE}   List only the selected recipe.
                ${YELLOW}--no-ansi${RESTORE}           Disable colors (if you want to put the response in a file)

${BOLD}${WHITE}Only any project directory:${RESTORE}

  ${YELLOW}help${RESTORE}                      ${GREEN}Show project workflow help. ${BOLD}Not this help!${RESTORE}
  ${YELLOW}list${RESTORE}                      ${GREEN}Show available commands in project${RESTORE}
  ${YELLOW}info${RESTORE}                      ${GREEN}Show some important project information${RESTORE}
  ${YELLOW}reconfigure${RESTORE}               ${GREEN}Rebuild the project config. ${BOLD}You can use symfony args: wf reconfigure -v${RESTORE}

Eg: ${YELLOW}wf help${RESTORE}

${BOLD}${WHITE}Developer options:${RESTORE}
        --composer-install          ${GREEN}"Composer install" in symfony directory!${RESTORE}
EOM

function showHelp {
    echo -e "${HELP}"
}
