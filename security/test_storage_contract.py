"""Deployment permission invariants, alongside separate live Azure rollout probes."""
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('inventory', ROOT / 'deploy/azure/check-storage-inventory.py')
inventory = importlib.util.module_from_spec(spec)
spec.loader.exec_module(inventory)


class StorageContractTest(unittest.TestCase):
    def test_inventory_requires_reviewed_names_and_excludes_archive(self):
        self.assertEqual(['documents', 'documents-acme'],
                         inventory.validate('["documents","documents-acme"]', 'kmp-backups'))
        for value in ('', '{}', '[]', '["kmp-backups"]', '["documents","documents"]',
                      '["invalid/path"]', '["Bad_name"]', '[null]'):
            with self.subTest(value=value), self.assertRaises((ValueError, TypeError)):
                inventory.validate(value, 'kmp-backups')

    def test_compiled_roles_do_not_reintroduce_container_or_sas_access(self):
        template = json.loads((ROOT / 'deploy/azure/main.json').read_text())
        resources = template['resources']
        roles = {item['properties']['description']: item['properties'] for item in resources
                 if item['type'] == 'Microsoft.Authorization/roleDefinitions'}
        runtime = next(role for description, role in roles.items() if description.startswith('Document blob'))
        permissions = runtime['permissions'][0]
        self.assertEqual(['Microsoft.Storage/storageAccounts/blobServices/containers/read'], permissions['actions'])
        self.assertEqual({'read', 'write', 'delete'},
                         {action.rsplit('/', 1)[1] for action in permissions['dataActions']})
        reader = next(role for description, role in roles.items() if description.startswith('Read encrypted'))
        self.assertEqual(['Microsoft.Storage/storageAccounts/blobServices/containers/blobs/read'],
                         reader['permissions'][0]['dataActions'])
        delegator = next(role for description, role in roles.items() if description.startswith('Create/read document'))
        self.assertEqual({'Microsoft.Authorization/roleAssignments/write', 'Microsoft.Authorization/roleAssignments/read'},
                         set(delegator['permissions'][0]['actions']))
        for role in roles.values():
            for permission in role['permissions']:
                for action in permission.get('actions', []) + permission.get('dataActions', []):
                    self.assertNotIn('generateUserDelegationKey', action)
                    self.assertNotEqual('*', action)

    def test_delegation_is_constrained_and_document_assignment_excludes_archives(self):
        template = json.loads((ROOT / 'deploy/azure/main.json').read_text())
        assignments = [item for item in template['resources']
                       if item['type'] == 'Microsoft.Authorization/roleAssignments']
        delegation = next(item for item in assignments if 'document-grant-delegation-v2' in item['name'])
        condition = delegation['properties']['condition']
        for attribute in ('RoleDefinitionId', 'PrincipalId', 'PrincipalType', 'ServicePrincipal'):
            self.assertIn(attribute, condition)
        documents = next(item for item in assignments if 'document-blobs-v2' in item['name'])
        self.assertIn('/blobServices/containers', documents['scope'])
        self.assertEqual('2.0', documents['properties']['conditionVersion'])
        condition = (ROOT / 'app/resources/security/document-blob-condition.txt').read_text()
        self.assertIn("StringLike 'backups/*'", condition)
        self.assertIn("!(SubOperationMatches{'Blob.List'})", condition)


if __name__ == '__main__':
    unittest.main()
