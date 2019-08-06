_base_init() {
    local cache_services_file=${wf_directory_name}/autocomplete.services
    [[ ! -f $cache_services_file ]] || [[ -z $(cat $cache_services_file) ]] && wf docker-compose config --services > $cache_services_file
    services=$(<$cache_services_file)
}

# Create autocomplete services
case $COMP_CWORD in
    1)
        # do nothing
    ;;
    *)
        case ${COMP_WORDS[1]} in
            connect | enter | debug-enter | logs)
                _base_init
                # Only the second argument
                [[ $COMP_CWORD == 2 ]] && words+=" ${services:-$(wf docker-compose config --services)}"
            ;;
            exec | run | sudo-run | docker-compose)
                _base_init
                # Not only the second argument
                words+=" ${services:-$(wf docker-compose config --services)}"
            ;;
        esac
    ;;
esac
