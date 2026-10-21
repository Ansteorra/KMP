"""Reject affected PHP branches, including an older higher-minor release."""
import json
import os
from pathlib import Path
import re
import subprocess
import unittest
from unittest.mock import patch

import test_checks

ROOT = Path(__file__).resolve().parents[1]
POLICY = ROOT / 'docker/verify-php-version.php'


class PhpRuntimeTest(unittest.TestCase):
    def php(self, *arguments, input=None):
        image = os.environ.get('KMP_TEST_PHP_IMAGE')
        if image:
            command = ['docker', 'run', '--rm', '-i', '--network', 'none', '--read-only',
                       '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
                       '--mount', f'type=bind,src={POLICY},dst=/policy.php,readonly',
                       '--entrypoint', 'php', image]
        else:
            command = ['php']
        return subprocess.run(command + list(arguments), input=input,
                              capture_output=True, text=True, timeout=30)

    def test_each_supported_branch_requires_its_own_fix(self):
        versions = {
            '8.3.0': False, '8.3.35': False, '8.3.36': True, '8.3.37': True,
            '8.4.0': False, '8.4.24': False, '8.4.26': False, '8.4.27': True, '8.4.28': True,
            '8.5.10': False, '8.5.11': False, '8.5.12': True, '8.5.13': True,
            '8.2.35': False, '8.1.99': False, '8.6.0': False, '8.6.0RC3': False,
            '8.5.12RC1': False, '8.5.12-dev': False, '9.0.0': False, '': False,
        }
        path = '/policy.php' if os.environ.get('KMP_TEST_PHP_IMAGE') else str(POLICY)
        script = ('$supportsPhpVersion = require $argv[1]; '
                  '$versions = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); '
                  'foreach ($versions as $version => $expected) { '
                  'if ($supportsPhpVersion($version) !== $expected) { '
                  'fwrite(STDERR, "Incorrect policy for: " . $version); exit(1); } }')
        result = self.php('-r', script, path, input=json.dumps(versions))
        self.assertEqual(0, result.returncode, result.stderr)

    def test_command_checks_the_actual_interpreter(self):
        version = self.php('-r', 'echo PHP_VERSION;')
        self.assertEqual(0, version.returncode, version.stderr)
        path = '/policy.php' if os.environ.get('KMP_TEST_PHP_IMAGE') else str(POLICY)
        result = self.php(path)
        match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)', version.stdout)
        minimums = {(8, 3): 36, (8, 4): 27, (8, 5): 12}
        parts = tuple(map(int, match.groups())) if match else ()
        supported = bool(parts and parts[:2] in minimums
                         and parts[2] >= minimums[parts[:2]])
        if not supported:
            self.assertNotEqual(0, result.returncode)
            self.assertIn('CVE-2026-109618', result.stderr)
        else:
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn(version.stdout, result.stdout)

    def test_devcontainer_requires_a_fixed_stable_php84_package(self):
        source = (ROOT / '.devcontainer/Dockerfile').read_text()
        command = re.search(r"RUN php -r '([^']+)'", source)
        self.assertIsNotNone(command)
        script = command[1].replace('PHP_VERSION', '$argv[1]')
        for version, expected in {'8.4.26': False, '8.4.27': True, '8.4.28': True,
                                  '8.4.27RC1': False, '8.5.12': False}.items():
            with self.subTest(version=version):
                result = self.php('-r', script, version)
                self.assertEqual(expected, result.returncode == 0, result.stderr)

    def test_all_image_builds_enforce_the_current_policy(self):
        for name in ['base', 'app', 'ci', 'prod']:
            with self.subTest(image=name):
                source = (ROOT / ('docker/Dockerfile.' + name)).read_text()
                self.assertIn('COPY docker/verify-php-version.php ', source)
                self.assertIn('RUN php /usr/local/bin/verify-kmp-php-version.php', source)
        production = (ROOT / 'docker/Dockerfile.prod').read_text().split('FROM ${BASE_IMAGE}', 1)[1]
        self.assertIn('RUN php /usr/local/bin/verify-kmp-php-version.php', production)
        self.assertIn("- 'docker/verify-php-version.php'",
                      (ROOT / '.github/workflows/base.yml').read_text())

    def test_final_image_check_executes_the_policy_without_its_entrypoint(self):
        with patch.object(test_checks.check.subprocess, 'run') as run:
            test_checks.check.verify_image_php('example/kmp@sha256:' + 'a' * 64)
        command = run.call_args.args[0]
        self.assertIn('--read-only', command)
        self.assertIn('no-new-privileges', command)
        self.assertEqual(['--entrypoint', 'php', 'example/kmp@sha256:' + 'a' * 64,
                          '/verify-kmp-php-version.php'], command[-4:])
        self.assertTrue(run.call_args.kwargs['check'])

    def test_failed_runtime_check_cannot_pass_the_image_gate(self):
        with patch.object(test_checks.check.subprocess, 'run',
                          side_effect=subprocess.CalledProcessError(1, ['docker'])):
            with self.assertRaises(subprocess.CalledProcessError):
                test_checks.check.verify_image_php('vulnerable-image')


if __name__ == '__main__':
    unittest.main()
