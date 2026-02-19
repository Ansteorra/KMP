#!/usr/bin/env bash
# KMP Installer Script
#
# Downloads and installs the kmp binary for your platform.
# Safe to pipe from curl:
#   curl -fsSL https://raw.githubusercontent.com/jhandel/KMP/main/installer/scripts/install.sh | bash
#
# Options:
#   --version <version>   Install a specific version (e.g. v1.0.0)
#   --dir <directory>     Install to a custom directory
#
set -euo pipefail

REPO="jhandel/KMP"
BINARY_NAME="kmp"
DEFAULT_INSTALL_DIR="/usr/local/bin"
FALLBACK_INSTALL_DIR="$HOME/.local/bin"

# --- Color helpers ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

info()  { printf "${CYAN}ℹ${RESET}  %s\n" "$*"; }
ok()    { printf "${GREEN}✔${RESET}  %s\n" "$*"; }
warn()  { printf "${YELLOW}⚠${RESET}  %s\n" "$*"; }
error() { printf "${RED}✖${RESET}  %s\n" "$*" >&2; }

# --- Parse arguments ---
VERSION="latest"
CUSTOM_DIR=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --version)
            VERSION="$2"
            shift 2
            ;;
        --dir)
            CUSTOM_DIR="$2"
            shift 2
            ;;
        *)
            error "Unknown option: $1"
            echo "Usage: install.sh [--version <version>] [--dir <directory>]"
            exit 1
            ;;
    esac
done

# --- Detect OS ---
detect_os() {
    local os
    os="$(uname -s)"
    case "$os" in
        Linux*)  echo "linux" ;;
        Darwin*) echo "darwin" ;;
        *)
            error "Unsupported operating system: $os"
            echo "Supported: Linux, macOS"
            exit 1
            ;;
    esac
}

# --- Detect architecture ---
detect_arch() {
    local arch
    arch="$(uname -m)"
    case "$arch" in
        x86_64|amd64)   echo "amd64" ;;
        aarch64|arm64)   echo "arm64" ;;
        *)
            error "Unsupported architecture: $arch"
            echo "Supported: x86_64/amd64, aarch64/arm64"
            exit 1
            ;;
    esac
}

# --- Build download URL ---
build_url() {
    local os="$1" arch="$2"
    local base_url="https://github.com/${REPO}/releases"

    if [[ "$VERSION" == "latest" ]]; then
        echo "${base_url}/latest/download/${BINARY_NAME}-${os}-${arch}"
    else
        echo "${base_url}/download/${VERSION}/${BINARY_NAME}-${os}-${arch}"
    fi
}

# --- Determine install directory ---
determine_install_dir() {
    if [[ -n "$CUSTOM_DIR" ]]; then
        echo "$CUSTOM_DIR"
        return
    fi

    if [[ -w "$DEFAULT_INSTALL_DIR" ]]; then
        echo "$DEFAULT_INSTALL_DIR"
    elif command -v sudo &>/dev/null; then
        echo "$DEFAULT_INSTALL_DIR"
    else
        echo "$FALLBACK_INSTALL_DIR"
    fi
}

# --- Download binary ---
download_binary() {
    local url="$1" dest="$2"

    info "Downloading from: $url"

    local http_code
    if command -v curl &>/dev/null; then
        http_code=$(curl -fsSL -w "%{http_code}" -o "$dest" "$url" 2>/dev/null) || true
    elif command -v wget &>/dev/null; then
        wget -q -O "$dest" "$url" 2>/dev/null && http_code="200" || http_code="000"
    else
        error "Neither curl nor wget found. Please install one and try again."
        print_manual_instructions
        exit 1
    fi

    if [[ "$http_code" != "200" ]]; then
        error "Download failed (HTTP $http_code)"
        print_manual_instructions
        return 1
    fi

    if [[ ! -s "$dest" ]]; then
        error "Downloaded file is empty"
        print_manual_instructions
        return 1
    fi

    return 0
}

# --- Print manual download instructions ---
print_manual_instructions() {
    local os arch url
    os="$(detect_os)"
    arch="$(detect_arch)"
    url="$(build_url "$os" "$arch")"

    echo ""
    warn "Automatic installation failed. You can install manually:"
    echo ""
    echo "  1. Download the binary:"
    printf "     ${BOLD}curl -fsSL -o kmp '%s'${RESET}\n" "$url"
    echo ""
    echo "  2. Make it executable:"
    printf "     ${BOLD}chmod +x kmp${RESET}\n"
    echo ""
    echo "  3. Move it to your PATH:"
    printf "     ${BOLD}sudo mv kmp /usr/local/bin/kmp${RESET}\n"
    echo ""
    echo "  4. Verify installation:"
    printf "     ${BOLD}kmp --version${RESET}\n"
    echo ""
}

# --- Check if directory is in PATH ---
is_in_path() {
    local dir="$1"
    echo "$PATH" | tr ':' '\n' | grep -qx "$dir"
}

# --- Main ---
main() {
    echo ""
    printf "${BOLD}KMP Installer${RESET}\n"
    echo "─────────────────────────────"
    echo ""

    local os arch
    os="$(detect_os)"
    arch="$(detect_arch)"

    info "Detected platform: ${os}/${arch}"
    if [[ "$VERSION" != "latest" ]]; then
        info "Requested version: ${VERSION}"
    fi

    local url
    url="$(build_url "$os" "$arch")"

    # Create temp file for download
    local tmpfile
    tmpfile="$(mktemp)"
    trap 'rm -f "$tmpfile"' EXIT

    if ! download_binary "$url" "$tmpfile"; then
        exit 1
    fi

    ok "Download complete"

    # Make executable
    chmod +x "$tmpfile"

    # Determine install location
    local install_dir
    install_dir="$(determine_install_dir)"
    local install_path="${install_dir}/${BINARY_NAME}"

    # Ensure install directory exists
    if [[ ! -d "$install_dir" ]]; then
        info "Creating directory: $install_dir"
        mkdir -p "$install_dir" 2>/dev/null || {
            if command -v sudo &>/dev/null; then
                sudo mkdir -p "$install_dir"
            else
                error "Cannot create directory: $install_dir"
                exit 1
            fi
        }
    fi

    # Move binary to install location
    if [[ -w "$install_dir" ]]; then
        mv "$tmpfile" "$install_path"
    elif command -v sudo &>/dev/null; then
        info "Requesting sudo to install to $install_dir"
        sudo mv "$tmpfile" "$install_path"
        sudo chmod +x "$install_path"
    else
        # Fall back to user-local directory
        install_dir="$FALLBACK_INSTALL_DIR"
        install_path="${install_dir}/${BINARY_NAME}"
        mkdir -p "$install_dir"
        mv "$tmpfile" "$install_path"
    fi

    ok "Installed to: ${install_path}"

    # Check if install dir is in PATH
    if ! is_in_path "$install_dir"; then
        echo ""
        warn "$install_dir is not in your PATH."
        echo ""
        echo "  Add it by running:"
        echo ""
        local shell_name
        shell_name="$(basename "${SHELL:-/bin/bash}")"
        case "$shell_name" in
            zsh)
                printf "    ${BOLD}echo 'export PATH=\"%s:\$PATH\"' >> ~/.zshrc && source ~/.zshrc${RESET}\n" "$install_dir"
                ;;
            fish)
                printf "    ${BOLD}fish_add_path %s${RESET}\n" "$install_dir"
                ;;
            *)
                printf "    ${BOLD}echo 'export PATH=\"%s:\$PATH\"' >> ~/.bashrc && source ~/.bashrc${RESET}\n" "$install_dir"
                ;;
        esac
        echo ""
    fi

    # Success
    echo ""
    printf "${GREEN}${BOLD}Installation complete!${RESET}\n"
    echo ""
    echo "  Next steps:"
    printf "    ${BOLD}kmp install${RESET}      – Set up your KMP environment\n"
    printf "    ${BOLD}kmp --help${RESET}       – See available commands\n"
    echo ""
}

main
