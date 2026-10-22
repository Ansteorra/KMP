"""PostgreSQL startup validates configuration and isolates CakePHP URL options."""
import os
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]


class PostgresStartupTest(unittest.TestCase):
    def run_production_probe(self, configuration):
        source = (ROOT / 'docker/entrypoint.prod.sh').read_text()
        probe = re.search(r'^check_postgres\(\) \{\n.*?^\}', source, re.MULTILINE | re.DOTALL)
        self.assertIsNotNone(probe)
        environment = dict(os.environ)
        for key in ('DATABASE_URL', 'DB_HOST', 'DB_PORT', 'PGHOST', 'PGPORT'):
            environment.pop(key, None)
        environment.update(configuration)
        # Capture only the readiness client's arguments; never connect to a real host.
        script = probe[0] + '\npg_isready() { printf "%s\\n" "$@"; }\ncheck_postgres\n'
        return subprocess.run(['bash', '-c', script], env=environment,
                              capture_output=True, text=True, timeout=5)

    def test_readiness_accepts_cake_ssl_options_without_forwarding_credentials(self):
        cases = (
            ('postgres://user:sensitive-password@db.example:6432/kmp?ssl=true&ssl_mode=require',
             'db.example', '6432'),
            ('pgsql://user:sensitive-password@db.example/kmp?ssl=true&ssl_mode=require',
             'db.example', '5432'),
            ('postgresql://user:sensitive-password@[::1]:6433/kmp?ssl=true&ssl_mode=require',
             '::1', '6433'),
        )
        for url, host, port in cases:
            with self.subTest(scheme=url.split(':', 1)[0]):
                result = self.run_production_probe({'DATABASE_URL': url})
                self.assertEqual(0, result.returncode, result.stderr)
                self.assertEqual(['-h', host, '-p', port, '-q'], result.stdout.splitlines())
                self.assertNotIn('sensitive-password', result.stdout + result.stderr)

    def test_readiness_rejects_invalid_urls_without_disclosing_credentials(self):
        result = self.run_production_probe({
            'DATABASE_URL': 'postgres://user:sensitive-password@db.example:invalid/kmp',
        })
        self.assertNotEqual(0, result.returncode)
        self.assertEqual('', result.stdout)
        self.assertNotIn('sensitive-password', result.stderr)

    def test_readiness_uses_configured_host_and_port_without_a_url(self):
        result = self.run_production_probe({'DB_HOST': 'db.example', 'DB_PORT': '6432'})
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(['-h', 'db.example', '-p', '6432', '-q'], result.stdout.splitlines())

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
