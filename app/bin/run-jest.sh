#!/usr/bin/env bash
# Guard Jest configuration loading and every inherited Node worker.
set -euo pipefail

cd "$(dirname "$0")/.."
export NODE_OPTIONS="${NODE_OPTIONS:+$NODE_OPTIONS }--disable-proto=throw"
exec node_modules/.bin/jest "$@"
