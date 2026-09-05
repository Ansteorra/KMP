#!/usr/bin/env python3
"""Fail closed before cutover when administrative credentials/identities overlap."""
import json
import pathlib
import sys


def validate(resources):
    runtime_ids = set()
    admin_ids = set()
    for name, resource in resources.items():
        identities = set(resource.get('identity', {}).get('userAssignedIdentities', {}))
        if not identities:
            raise ValueError('Every runtime requires an explicit managed identity.')
        administrative = name in ('migrate-job', 'admin-job') or name.startswith('privileged-')
        (admin_ids if administrative else runtime_ids).update(identities)
        env = {entry['name']: entry for entry in resource['properties']['template']['containers'][0].get('env', [])}
        admin_values = ('DATABASE_ADMIN_URL', 'PLATFORM_DATABASE_ADMIN_URL')
        if administrative:
            if env.get('KMP_ADMIN_JOB', {}).get('value') != 'true':
                raise ValueError('Administrative jobs must explicitly enable KMP_ADMIN_JOB.')
            if any(not env.get(key, {}).get('secretRef') for key in admin_values):
                raise ValueError('Administrative jobs must receive separate administrative URL secret references.')
        elif any(key in env for key in (*admin_values, 'KMP_ADMIN_JOB')):
            raise ValueError('Web and ordinary workers must have no administrative credentials or mode flag.')
        secrets = resource['properties'].get('configuration', {}).get('secrets', [])
        if not administrative and any('admin' in secret.get('name', '').lower() for secret in secrets):
            raise ValueError('Administrative secrets must not be attached to runtime resources.')
    if admin_ids & runtime_ids:
        raise ValueError('Administrative and ordinary runtime identities must be distinct.')


if __name__ == '__main__':
    try:
        folder = pathlib.Path(sys.argv[1])
        resources = {name: json.loads((folder / (name + '.json')).read_text())
                     for name in ('web', 'worker-job', 'migrate-job', 'admin-job')}
        resources.update({path.stem: json.loads(path.read_text()) for path in folder.glob('privileged-*.json')})
        validate(resources)
    except (ValueError, KeyError, IndexError, OSError):
        sys.exit('Database job isolation contract failed. Apply and verify the security infrastructure migration first.')
    print('Database job isolation contract verified.')
