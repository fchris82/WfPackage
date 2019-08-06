#!/usr/bin/env bash

# Here we created an autocomplete bash extension. There are defaults and you can use additional recipe autocompletes.
# If you want to test it while you are developing, you must to edit the installed file directly in the
# `~/.wf-docker-workflow/bash/autocomplete.sh` file.
# Reload to test: `source ~/.wf-docker-workflow/bin/bash/autocomplete.sh`

_wf() {
    # Get config file
    local config_file=${HOME}/.wf-docker-workflow/config/env
    # We have to reset the words variable
    local words=''
    local cur="${COMP_WORDS[COMP_CWORD]}"
    local first="${COMP_WORDS[1]}"
    local prev="${COMP_WORDS[COMP_CWORD-1]}"
    if [ -f $config_file ]; then
        local wf_directory_name=$(awk '/^'WF_WORKING_DIRECTORY_NAME'/{split($1,a,"="); print a[2]}' "${config_file}")

        if [ -d $wf_directory_name ]; then
            # Create autocomplete list
            local cache_list_file=${wf_directory_name}/autocomplete.list
            [[ ! -f $cache_list_file ]] || [[ -z $(cat $cache_list_file) ]] && wf list > $cache_list_file
            list=$(<$cache_list_file)
        fi
    fi

    case $COMP_CWORD in
        1)
            words='--help --version --docker-ps --composer-install --reload --clean-cache --enter --run --sf-run --extensions --config-dump reconfigure --update --rebuild'
            # Last character must be 'space'
            words+=' '
            [[ -f $config_file ]] && [[ -d $wf_directory_name ]] && words+=$(echo ${list:-$(wf list)})
        ;;
        2)
            case ${prev} in
                --config-dump)
                    words='--only-recipes --no-ansi --recipe='
                ;;
                --rebuild)
                    words='--no-pull'
                ;;
            esac
        ;;
    esac

    # Here we try to find recipes autocompletes.
    if [ -f $config_file ] && [ -d $wf_directory_name ]; then
        local recipe_autocompletes_file=${wf_directory_name}/autocomplete.recipes
        if [ ! -f $recipe_autocompletes_file ]; then
            # find all autocomplete.zsh file in recipes!
            find -L ${wf_directory_name} -mindepth 2 -maxdepth 2 -type f -name 'autocomplete.bash' -printf "source %p\n" > $recipe_autocompletes_file
        fi
        source $recipe_autocompletes_file
    fi

    # Default autoconfig is the directories and files
    if [ -z "$words" ]; then
        compopt -o nospace
        compopt -o filenames
        COMPREPLY=($(compgen -f -- ${cur}))
    else
        COMPREPLY=($(compgen -W "${words}" -- ${cur}))
    fi
}

complete -o filenames -F _wf wf
