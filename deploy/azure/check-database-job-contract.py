#!/usr/bin/env python3
"""Fail closed before cutover when administrative credentials/identities overlap."""
import argparse
import json
import os
import pathlib
import sys
import subprocess


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


def discover_azure_resources(environ):
    """Read the resources in memory; never export credential-bearing payloads."""
    names = {
        'web': 'AZURE_WEB_APP_NAME',
        'worker-job': 'AZURE_QUEUE_JOB_NAME',
        'migrate-job': 'AZURE_MIGRATE_JOB_NAME',
        'admin-job': 'AZURE_ADMIN_JOB_NAME',
    }
    for variable in ('AZURE_RESOURCE_GROUP', *names.values()):
        if not environ.get(variable):
            raise ValueError(f'Missing required environment variable: {variable}.')
    for variable in ('AZURE_RESTORE_JOB_NAME', 'AZURE_PROVISION_JOB_NAME'):
        if environ.get(variable):
            names[f'privileged-{environ[variable]}'] = variable
    resources = {}
    for label, variable in names.items():
        name = environ[variable]
        command = ['az', 'containerapp'] + ([] if label == 'web' else ['job'])
        command += ['show', '--resource-group', environ['AZURE_RESOURCE_GROUP'],
                    '--name', name, '--output', 'json', '--only-show-errors']
        try:
            payload = subprocess.check_output(command, stderr=subprocess.PIPE, text=True)
        except subprocess.CalledProcessError:
            raise ValueError(
                f'Cannot read required Azure resource {name}. Check its existence and deployment identity access.'
            ) from None
        resources[label] = json.loads(payload)
    return resources


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('folder', nargs='?', help='Existing cutover snapshot directory')
    parser.add_argument('--azure', action='store_true', help='Read live Azure resources using AZURE_* environment names')
    args = parser.parse_args()
    if bool(args.folder) == args.azure:
        parser.error('Choose a snapshot folder or --azure.')
    try:
        if args.azure:
            resources = discover_azure_resources(os.environ)
        else:
            folder = pathlib.Path(args.folder)
            resources = {name: json.loads((folder / (name + '.json')).read_text())
                         for name in ('web', 'worker-job', 'migrate-job', 'admin-job')}
            resources.update({path.stem: json.loads(path.read_text()) for path in folder.glob('privileged-*.json')})
        validate(resources)
    except (ValueError, KeyError, IndexError, OSError) as error:
        if isinstance(error, ValueError) and not isinstance(error, json.JSONDecodeError):
            print(str(error), file=sys.stderr)
        sys.exit('Database job isolation contract failed. Apply and verify deploy/azure/security-rollout.md first.')
    print('Database job isolation contract verified.')
