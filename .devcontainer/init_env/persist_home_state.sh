#!/bin/bash
set -euo pipefail

# Persist user auth and SSH material into the workspace so rebuilds can restore it.
: "${REPO_PATH:?REPO_PATH must be set}"

PERSIST_ROOT="$REPO_PATH/.container-home/vscode"

mkdir -p "$PERSIST_ROOT"
chmod 700 "$PERSIST_ROOT"

persist_directory() {
    local source_path="$1"
    local persist_path="$2"
    local mode="$3"

    mkdir -p "$(dirname "$persist_path")"

    if [ -L "$source_path" ]; then
        if [ "$(readlink -f "$source_path")" = "$persist_path" ]; then
            chmod "$mode" "$persist_path" 2>/dev/null || true
            return
        fi
        rm -f "$source_path"
    elif [ -d "$source_path" ]; then
        mkdir -p "$persist_path"
        cp -an "$source_path"/. "$persist_path"/ 2>/dev/null || true
        rm -rf "$source_path"
    fi

    mkdir -p "$(dirname "$source_path")"
    mkdir -p "$persist_path"
    ln -s "$persist_path" "$source_path"
    chmod "$mode" "$persist_path"
}

persist_file() {
    local source_path="$1"
    local persist_path="$2"
    local mode="$3"

    mkdir -p "$(dirname "$persist_path")"

    if [ -L "$source_path" ]; then
        if [ "$(readlink -f "$source_path")" = "$persist_path" ]; then
            chmod "$mode" "$persist_path" 2>/dev/null || true
            return
        fi
        rm -f "$source_path"
    elif [ -f "$source_path" ]; then
        if [ ! -e "$persist_path" ]; then
            cp -a "$source_path" "$persist_path"
        fi
        rm -f "$source_path"
    elif [ ! -f "$persist_path" ]; then
        return
    fi

    mkdir -p "$(dirname "$source_path")"
    ln -s "$persist_path" "$source_path"
    chmod "$mode" "$persist_path"
}

persist_directory "$HOME/.ssh" "$PERSIST_ROOT/.ssh" 700
find "$PERSIST_ROOT/.ssh" -type f -exec chmod 600 {} + 2>/dev/null || true

persist_directory "$HOME/.config/gh" "$PERSIST_ROOT/.config/gh" 700
find "$PERSIST_ROOT/.config/gh" -type f -exec chmod 600 {} + 2>/dev/null || true

persist_file "$HOME/.copilot/config.json" "$PERSIST_ROOT/.copilot/config.json" 600
