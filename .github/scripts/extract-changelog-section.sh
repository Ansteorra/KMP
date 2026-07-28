#!/usr/bin/env bash

set -euo pipefail

if [[ "$#" -ne 2 ]]; then
    echo "Usage: $0 <changelog-file> <version>" >&2
    exit 64
fi

changelog_file="$1"
version="$2"

if [[ ! -f "$changelog_file" ]]; then
    echo "Changelog file not found: $changelog_file" >&2
    exit 66
fi
if [[ ! "$version" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
    echo "Invalid changelog version: $version" >&2
    exit 64
fi

awk -v version="$version" '
    BEGIN {
        heading = "## KMP " version " "
        capturing = 0
        found = 0
        count = 0
    }
    index($0, "## KMP ") == 1 {
        if (capturing) {
            exit
        }
        if (index($0, heading) == 1) {
            capturing = 1
            found = 1
            next
        }
    }
    capturing {
        lines[++count] = $0
    }
    END {
        if (!found) {
            print "No changelog section found for KMP " version > "/dev/stderr"
            exit 1
        }
        while (count > 0 && lines[count] == "") {
            count--
        }
        if (count > 0 && lines[count] == "---") {
            count--
        }
        while (count > 0 && lines[count] == "") {
            count--
        }
        start = 1
        while (start <= count && lines[start] == "") {
            start++
        }
        if (start > count) {
            print "Changelog section for KMP " version " is empty" > "/dev/stderr"
            exit 1
        }
        for (line_number = start; line_number <= count; line_number++) {
            print lines[line_number]
        }
    }
' "$changelog_file"
