"""Retired database configuration must fail before container startup side effects."""
import os
from pathlib import Path
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]


class PostgresStartupTest(unittest.TestCase):
    def run_entrypoint(self, name, configuration):
        environment = dict(os.environ)
        for key in ('KMP_DB_DRIVER', 'DATABASE_URL', 'DATABASE_TEST_URL',
                    'DATABASE_ADMIN_URL', 'PLATFORM_DATABASE_URL',
                    'PLATFORM_DATABASE_ADMIN_URL', 'PLATFORM_DATABASE_TEST_URL'):
            environment.pop(key, None)
        environment.update(configuration)
        return subprocess.run(['bash', str(ROOT / 'docker' / name)],
                              env=environment, capture_output=True, text=True, timeout=5)

    def test_retired_selectors_fail_before_startup(self):
        for entrypoint in ('entrypoint.sh', 'entrypoint.prod.sh'):
            for driver in ('mysql', 'mariadb', 'sqlite'):
                with self.subTest(entrypoint=entrypoint, driver=driver):
                    result = self.run_entrypoint(entrypoint, {'KMP_DB_DRIVER': driver})
                    self.assertNotEqual(0, result.returncode)
                    self.assertIn('PostgreSQL only', result.stderr)
                    self.assertNotIn('Waiting for database', result.stdout)
                    self.assertNotIn('Copying Docker app_local', result.stdout)

    def test_production_rejects_every_retired_url_without_disclosing_credentials(self):
        for variable in ('DATABASE_URL', 'DATABASE_TEST_URL', 'DATABASE_ADMIN_URL',
                         'PLATFORM_DATABASE_URL', 'PLATFORM_DATABASE_ADMIN_URL',
                         'PLATFORM_DATABASE_TEST_URL'):
            with self.subTest(variable=variable):
                result = self.run_entrypoint('entrypoint.prod.sh', {
                    variable: 'mysql://user:sensitive-password@localhost/kmp',
                })
                self.assertNotEqual(0, result.returncode)
                self.assertIn(variable + ' must use PostgreSQL', result.stderr)
                self.assertNotIn('sensitive-password', result.stdout + result.stderr)
                self.assertNotIn('Starting', result.stdout)


if __name__ == '__main__':
    unittest.main()
