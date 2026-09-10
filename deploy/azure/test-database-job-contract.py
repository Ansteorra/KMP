#!/usr/bin/env python3
"""Synthetic deployment payload regressions; never reads Azure credentials."""
import copy
import importlib.util
import json
import subprocess
import pathlib
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('contract', pathlib.Path(__file__).with_name('check-database-job-contract.py'))
contract = importlib.util.module_from_spec(spec)
spec.loader.exec_module(contract)


def resource(administrative=False):
    env = []
    if administrative:
        env = [{'name': 'KMP_ADMIN_JOB', 'value': 'true'},
               {'name': 'DATABASE_ADMIN_URL', 'secretRef': 'admin-url'},
               {'name': 'PLATFORM_DATABASE_ADMIN_URL', 'secretRef': 'platform-admin-url'}]
    return {'identity': {'userAssignedIdentities': {'admin' if administrative else 'runtime': {}}},
            'properties': {'configuration': {'secrets': []}, 'template': {'containers': [{'env': env}]}}}


class ContractTest(unittest.TestCase):
    def setUp(self):
        self.resources = {name: resource(admin) for name, admin in
                          [('web', False), ('worker-job', False), ('admin-job', True), ('migrate-job', True)]}

    def test_isolated_jobs_pass(self):
        contract.validate(self.resources)

    def test_runtime_credentials_and_shared_identity_fail(self):
        for mutation in ('credential', 'identity', 'secret'):
            with self.subTest(mutation=mutation):
                resources = copy.deepcopy(self.resources)
                if mutation == 'credential':
                    resources['web']['properties']['template']['containers'][0]['env'].append(
                        {'name': 'DATABASE_ADMIN_URL', 'secretRef': 'hidden'})
                elif mutation == 'identity':
                    resources['web']['identity']['userAssignedIdentities']['admin'] = {}
                else:
                    resources['web']['properties']['configuration']['secrets'].append({'name': 'postgres-admin-password'})
                with self.assertRaises(ValueError):
                    contract.validate(resources)

    def test_missing_admin_credentials_fail(self):
        self.resources['admin-job']['properties']['template']['containers'][0]['env'].pop()
        with self.assertRaises(ValueError):
            contract.validate(self.resources)


class AzureDiscoveryTest(unittest.TestCase):
    def setUp(self):
        self.environ = {
            'AZURE_RESOURCE_GROUP': 'test-rg',
            'AZURE_WEB_APP_NAME': 'test-web',
            'AZURE_QUEUE_JOB_NAME': 'test-queue',
            'AZURE_MIGRATE_JOB_NAME': 'test-migrate',
            'AZURE_ADMIN_JOB_NAME': 'test-admin',
            'AZURE_RESTORE_JOB_NAME': 'test-restore',
            'AZURE_PROVISION_JOB_NAME': '',
        }

    def test_missing_admin_configuration_stops_before_azure(self):
        self.environ['AZURE_ADMIN_JOB_NAME'] = ''
        with patch.object(contract.subprocess, 'check_output') as cli:
            with self.assertRaisesRegex(ValueError, 'AZURE_ADMIN_JOB_NAME'):
                contract.discover_azure_resources(self.environ)
            cli.assert_not_called()

    def test_live_discovery_is_read_only_and_checks_retained_jobs(self):
        def show(command, **kwargs):
            self.assertEqual('az', command[0])
            self.assertIn('show', command)
            self.assertNotIn('secret', command)
            name = command[command.index('--name') + 1]
            return json.dumps(resource(name not in ('test-web', 'test-queue')))
        with patch.object(contract.subprocess, 'check_output', side_effect=show) as cli:
            resources = contract.discover_azure_resources(self.environ)
        self.assertEqual(5, cli.call_count)
        self.assertIn('privileged-test-restore', resources)
        contract.validate(resources)

    def test_unreadable_resource_reports_name_without_cli_output(self):
        with patch.object(contract.subprocess, 'check_output', side_effect=
                          subprocess.CalledProcessError(1, ['az'], output='sensitive output', stderr='secret')):
            with self.assertRaisesRegex(ValueError, 'test-web') as raised:
                contract.discover_azure_resources(self.environ)
        self.assertNotIn('sensitive', str(raised.exception))
        self.assertNotIn('secret', str(raised.exception))


if __name__ == '__main__':
    unittest.main()
