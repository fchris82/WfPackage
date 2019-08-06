_get_sf_xml() {
    local sf_cache_file=${wf_directory_name}/autocomplete.sf.xml
    # We load everything into an xml file
    [[ ! -f $sf_cache_file ]] || [[ -z $(cat $sf_cache_file) ]] && wf sf list --format=xml > $sf_cache_file
    sfxml=$(cat $sf_cache_file)
}

case $state in
    parameters)
        case $words[2] in
            sf)
                _get_sf_xml
                local sf_cache_commands=$(grep -oP "(?<=<command>)[^<]+(?=</command>)" <<< "$sfxml")

                # Commands
                _arguments '2: :($(echo ${sf_cache_commands:-""}))'

                if [ ! -z $words[3] ]; then
                    local sfcmd=${words[3]}
                    local sfcmd_cache_options=$(tr '\n' '\a' <<< "$sfxml" | grep -oP '<command id="'$sfcmd'".*?</command>' | grep -oP '(?<=<option name=")[^"]+(?=")')

                    # Command options
                    _arguments '*: :($(echo ${sfcmd_cache_options:-""}))'
                fi
            ;;
        esac
    ;;
esac
