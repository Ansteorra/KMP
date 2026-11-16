#!/usr/bin/env python3
"""Verify live PHP source replacements through an isolated Apache container."""

import argparse
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import uuid


PROBE = """<?php
header('Content-Type: application/json');
$template = '/var/www/html/template.php';
$value = require $template;
echo json_encode([
    'value' => $value,
    'sapi' => PHP_SAPI,
    'opcache' => ini_get('opcache.enable'),
    'cached' => opcache_is_script_cached($template),
], JSON_THROW_ON_ERROR);
"""


def docker(*args, timeout=30):
    return subprocess.run(
        ['docker', *args], check=True, capture_output=True,
        text=True, timeout=timeout,
    ).stdout.strip()


def verify(image, ini):
    container = 'kmp-php-cache-test-' + uuid.uuid4().hex[:12]
    with tempfile.TemporaryDirectory(prefix='kmp-php-cache-test-') as directory:
        root = Path(directory)
        root.chmod(0o755)
        # Support both development and production-base Apache document roots.
        (root / 'webroot').mkdir(mode=0o755)
        (root / 'webroot').chmod(0o755)
        for path in [root / 'index.php', root / 'webroot/index.php']:
            path.write_text(PROBE)
            path.chmod(0o644)
        template = root / 'template.php'
        original_time = int(time.time()) - 60

        def replace(value, atomic=False):
            destination = root / 'replacement.php' if atomic else template
            destination.write_text(f"<?php return '{value}';\n")
            destination.chmod(0o644)
            os.utime(destination, (original_time, original_time))
            if atomic:
                destination.replace(template)

        def request():
            return json.loads(docker(
                'exec', container, 'curl', '--fail', '--silent', '--show-error',
                '--max-time', '5', 'http://127.0.0.1/index.php',
            ))

        def expect(value):
            response = request()
            if response['sapi'] != 'apache2handler':
                raise RuntimeError(f'Expected Apache PHP, received {response["sapi"]}')
            if response['opcache'] not in ('', '0') or response['cached']:
                raise RuntimeError('Development Apache must disable OPcache for live-mounted code.')
            if response['value'] != value:
                raise RuntimeError(f'Stale PHP: expected {value}, received {response["value"]}')

        replace('alpha')
        arguments = [
            'run', '--detach', '--rm', '--network', 'none', '--name', container,
            '--entrypoint', 'apache2-foreground',
            '--mount', f'type=bind,source={root},target=/var/www/html,readonly',
        ]
        if ini:
            arguments += [
                '--mount', f'type=bind,source={ini},target=/usr/local/etc/php/conf.d/zz-kmp-development.ini,readonly',
            ]
        try:
            docker(*arguments, image)
            deadline = time.monotonic() + 30
            while True:
                try:
                    request()
                    break
                except (subprocess.CalledProcessError, json.JSONDecodeError):
                    if time.monotonic() >= deadline:
                        raise RuntimeError('Apache did not become ready within 30 seconds.') from None
                    time.sleep(0.2)
            expect('alpha')
            replace('bravo')
            expect('bravo')
            replace('cider', atomic=True)
            expect('cider')
            expect('cider')
            print('PASS: Apache serves current PHP after in-place and atomic replacements,')
            print('      including equal-length source with unchanged modification times.')
        finally:
            subprocess.run(
                ['docker', 'rm', '--force', container],
                capture_output=True, timeout=30,
            )


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--image', default='kmp-app:dev', help='Development or browser-test image to verify')
    parser.add_argument('--ini', type=Path, help='Mount a development INI override, as Compose does for older images')
    args = parser.parse_args()
    ini = args.ini.resolve(strict=True) if args.ini else None
    verify(args.image, ini)


if __name__ == '__main__':
    main()
