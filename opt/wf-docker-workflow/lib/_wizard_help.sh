read -r -d '' HELP <<-EOM
You can create or decorate custom project to use docker, Gitlab CI or other tool:

  -h --help               ${GREEN}Help, show this${RESTORE}

  Without any parameter just start the wizard!

Special argument:

  ${CYAN}--dev${RESTORE}            ${GREEN}For debugging. You can use this before every command! It can switch on ${BOLD}xdebug${GREEN} and ${BOLD}SF dev${GREEN} mode.${RESTORE}
\n
EOM

function showHelp {
    echo -e "${HELP}"
}
