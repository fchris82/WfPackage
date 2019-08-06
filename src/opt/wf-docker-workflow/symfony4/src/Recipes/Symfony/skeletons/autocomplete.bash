_get_sf_xml() {
    local sf_cache_file=${wf_directory_name}/autocomplete.sf.xml
    # We load everything into an xml file
    [[ ! -f $sf_cache_file ]] || [[ -z $(cat $sf_cache_file) ]] && wf sf list --format=xml > $sf_cache_file
    echo $(cat $sf_cache_file)
}

case $COMP_CWORD in
    1)
        # do nothing
    ;;
    2)
        # Load commands
        case ${first} in
            sf)
                local sf_cache_commands=$(_get_sf_xml | grep -oP "(?<=<command>)[^<]+(?=</command>)")

                words+=" ${sf_cache_commands:-""}"
            ;;
        esac
    ;;
    *)
        # Load command options
        case ${first} in
            sf)
                local sfcmd=${COMP_WORDS[2]}
                local sfcmd_cache_options=$(_get_sf_xml | tr '\n' '\a' | grep -oP '<command id="'$sfcmd'".*?</command>' | grep -oP '(?<=<option name=")[^"]+(?=")')

                words+=" ${sfcmd_cache_options:-""}"
            ;;
        esac
esac
