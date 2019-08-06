_base_init() {
    local cache_services_file=${wf_directory_name}/autocomplete.services
    [[ ! -f $cache_services_file ]] || [[ -z $(cat $cache_services_file) ]] && wf docker-compose config --services > $cache_services_file
    services=$(<$cache_services_file)
}

# Create autocomplete services
case $state in
    parameters)
        case $words[2] in
            connect | enter | debug-enter | logs)
                _base_init
                _arguments '2: :($(echo ${services:-$(wf docker-compose config --services)}))'
            ;;
            exec | run | sudo-run | docker-compose)
                _base_init
                _arguments '*: :($(echo ${services:-$(wf docker-compose config --services)}))'
            ;;
        esac
    ;;
esac
