#compdef wf

# Here we created an autocomplete zsh extension. There are defaults and you can use additional recipe autocompletes.
# If you want to test it while you are developing, you must to edit the installed file directly in the
# `~/.wf-docker-workflow/bin/zsh/autocomplete.sh` file.
# Reload to test: `unfunction _wf && autoload -U _wf`

_wf() {
    local state

    # Get config file
    local config_file=${HOME}/.wf-docker-workflow/config/env
    if [ -f $config_file ]; then
        local wf_directory_name=$(awk '/^'WF_WORKING_DIRECTORY_NAME'/{split($1,a,"="); print a[2]}' "${config_file}")

        if [ -d $wf_directory_name ]; then
            # Create autocomplete list
            local cache_list_file=${wf_directory_name}/autocomplete.list
            [[ ! -f $cache_list_file ]] || [[ -z $(cat $cache_list_file) ]] && wf list > $cache_list_file
            list=$(<$cache_list_file)
        fi
    fi

    _arguments \
        '1: :->command'\
        '*: :->parameters'

    case $state in
        command)
            _arguments '1: :(--help --version --docker-ps --composer-install --reload --clean-cache --enter --run --sf-run --extensions --config-dump reconfigure --update --rebuild)'
            [[ -f $config_file ]] && [[ -d $wf_directory_name ]] && compadd $(echo ${list:-$(wf list)})
        ;;
        parameters)
            case $words[2] in
                --config-dump)
                    _arguments '*: :(--only-recipes --no-ansi --recipe=)'
                ;;
                --rebuild)
                    _arguments '*: :(--no-pull)'
                ;;
            esac
            # Allow files from third parameter
            [[ ! -z $words[3] ]] && _alternative 'files:filename:_files'
        ;;
    esac

    # Here we try to find recipes autocompletes.
    if [ -f $config_file ] && [ -d $wf_directory_name ]; then
        local recipe_autocompletes_file=${wf_directory_name}/autocomplete.recipes
        if [ ! -f $recipe_autocompletes_file ]; then
            # find all autocomplete.zsh file in recipes!
            find -L ${wf_directory_name} -mindepth 2 -maxdepth 2 -type f -name 'autocomplete.zsh' -printf "source %p\n" > $recipe_autocompletes_file
        fi
        source $recipe_autocompletes_file
    fi
}

_wf "$@"
