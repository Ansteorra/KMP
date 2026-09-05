#!/usr/bin/env python3
"""Synthetic deployment payload regressions; never reads Azure credentials."""
import copy
import importlib.util
import pathlib
import unittest

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


if __name__ == '__main__':
    unittest.main()
