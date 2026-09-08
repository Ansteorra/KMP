#!/usr/bin/env python3
"""Validate the reviewed document inventory before a deployment can grant access."""
import json
import os
import re
import sys


def validate(raw: str, backup: str) -> list[str]:
    def valid(value):
        return (isinstance(value, str) and re.fullmatch(r'[a-z0-9][a-z0-9-]{1,61}[a-z0-9]', value)
                and '--' not in value)
    containers = json.loads(raw)
    if not valid(backup):
        raise ValueError('Invalid archive container name')
    if not isinstance(containers, list) or not containers:
        raise ValueError('A nonempty reviewed document container inventory is required')
    if any(not valid(item) or item == backup for item in containers):
        raise ValueError('Document inventory contains an invalid or reserved container')
    if len(set(containers)) != len(containers):
        raise ValueError('Document inventory contains duplicates')
    return containers


if __name__ == '__main__':
    try:
        validate(os.environ.get('DOCUMENT_CONTAINERS_JSON', ''),
                 os.environ.get('AZURE_BACKUP_STORAGE_CONTAINER', 'kmp-backups'))
    except (ValueError, TypeError) as exc:
        print(f'Storage inventory rejected: {exc}', file=sys.stderr)
        sys.exit(1)
