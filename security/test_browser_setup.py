"""Hosted browser setup must retain package trust and propagate installation failures."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
INSTALLER = ROOT / '.github/scripts/install-playwright-browser.sh'


class BrowserSetupTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.path = Path(self.directory.name)
        self.bin = self.path / 'bin'
        self.bin.mkdir()
        self.env = os.environ | {
            'PATH': str(self.bin) + os.pathsep + os.environ['PATH'],
            'GITHUB_ACTIONS': 'true', 'RUNNER_OS': 'Linux', 'BROWSER_SETUP_RECORD': str(self.path),
        }
        self.command('sudo', '''
import os, pathlib, sys
p = pathlib.Path(os.environ['BROWSER_SETUP_RECORD'])
command = sys.argv[1:]
if command[0] in ['test', 'grep']:
    sys.exit(1 if os.environ.get('MISSING_MIRROR') else 0)
if command[0] == 'tee':
    if os.environ.get('CONFIG_WRITE_FAILURE'):
        sys.exit(17)
    (p / pathlib.Path(command[1]).name).write_text(sys.stdin.read())
    sys.exit(0)
raise SystemExit('Unexpected privileged command: ' + repr(command))
''')
        self.command('npx', '''
import json, os, pathlib, sys
p = pathlib.Path(os.environ['BROWSER_SETUP_RECORD'])
(p / 'cli.json').write_text(json.dumps({'cwd': os.getcwd(), 'args': sys.argv[1:]}))
sys.exit(int(os.environ.get('INSTALL_EXIT', '0')))
''')

    def command(self, name, script):
        path = self.bin / name
        path.write_text('#!/usr/bin/env python3\n' + script)
        path.chmod(0o755)

    def run_installer(self, *browsers, **environment):
        return subprocess.run(['bash', str(INSTALLER), *browsers], cwd=self.path,
                              env=self.env | environment, capture_output=True, text=True, timeout=10)

    def test_browser_arguments_and_locked_cli_are_preserved(self):
        result = self.run_installer('chromium', 'firefox', 'webkit')
        self.assertEqual(0, result.returncode, result.stderr)
        cli = json.loads((self.path / 'cli.json').read_text())
        self.assertEqual(str(ROOT / 'app'), cli['cwd'])
        self.assertEqual(['--no-install', 'playwright', 'install', '--with-deps',
                          'chromium', 'firefox', 'webkit'], cli['args'])

    def test_only_mirror_and_network_configuration_are_written(self):
        self.assertEqual(0, self.run_installer('chromium').returncode)
        self.assertEqual({'bin', 'cli.json', 'apt-mirrors.txt', '80-kmp-ci-browser-network'},
                         {p.name for p in self.path.iterdir()})
        mirrors = (self.path / 'apt-mirrors.txt').read_text().splitlines()
        self.assertEqual('https://mirrors.edge.kernel.org/ubuntu/\tpriority:1', mirrors[0])
        self.assertEqual(4, len(mirrors))
        self.assertTrue(any('security.ubuntu.com/ubuntu/' in value for value in mirrors))
        options = (self.path / '80-kmp-ci-browser-network').read_text()
        self.assertIn('APT::Update::Error-Mode "any";', options)
        self.assertNotIn('AllowUnauthenticated', options)
        self.assertNotIn('AllowInsecure', options)

    def test_dependency_or_download_failure_blocks_the_job(self):
        result = self.run_installer('chromium', INSTALL_EXIT='37')
        self.assertEqual(37, result.returncode)

    def test_missing_runner_mirror_configuration_blocks_before_installation(self):
        result = self.run_installer('chromium', MISSING_MIRROR='1')
        self.assertNotEqual(0, result.returncode)
        self.assertFalse((self.path / 'cli.json').exists())
        self.assertFalse((self.path / 'apt-mirrors.txt').exists())

    def test_configuration_write_failure_blocks_before_installation(self):
        result = self.run_installer('chromium', CONFIG_WRITE_FAILURE='1')
        self.assertEqual(17, result.returncode)
        self.assertFalse((self.path / 'cli.json').exists())

    def test_local_invocation_and_invalid_browser_are_rejected_before_mutations(self):
        for browsers, environment in [
            (['chromium'], {'GITHUB_ACTIONS': 'false'}),
            (['chromium'], {'RUNNER_OS': 'Windows'}),
            ([], {}),
            (['--force'], {}),
        ]:
            with self.subTest(browsers=browsers, environment=environment):
                self.assertEqual(64, self.run_installer(*browsers, **environment).returncode)
                self.assertEqual({'bin'}, {p.name for p in self.path.iterdir()})


if __name__ == '__main__':
    unittest.main()
