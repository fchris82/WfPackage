# Register ~/bin path
if [ -d "$HOME/bin" ] && [ "$(echo "$PATH" | grep $HOME/bin | wc -l)" -eq "0" ]; then
    export PATH="$HOME/bin:$PATH"
fi
