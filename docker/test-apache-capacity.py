#!/usr/bin/env python3
"""Prove the final image bounds concurrent PHP work without a database or open ports."""

import argparse
import json
from pathlib import Path
import subprocess
import tempfile
import time
import uuid


def docker(*args, timeout=30, check=True):
    result = subprocess.run(['docker', *args], capture_output=True, text=True, timeout=timeout)
    if check and result.returncode:
        raise RuntimeError('Isolated Docker check failed: ' + result.stderr.strip())
    return result


PROBE = '''<?php
$file = fopen('/tmp/kmp-apache-capacity.json', 'c+');
flock($file, LOCK_EX);
$state = json_decode(stream_get_contents($file), true) ?: ['active' => 0, 'peak' => 0];
$state['active']++;
$state['peak'] = max($state['peak'], $state['active']);
rewind($file); ftruncate($file, 0); fwrite($file, json_encode($state)); fflush($file);
flock($file, LOCK_UN);
usleep(200000);
flock($file, LOCK_EX); rewind($file); $state = json_decode(stream_get_contents($file), true);
$state['active']--; rewind($file); ftruncate($file, 0); fwrite($file, json_encode($state));
flock($file, LOCK_UN); fclose($file);
echo 'bounded';
'''

CLIENT = '''
$multi = curl_multi_init(); $handles = [];
for ($i = 0; $i < 24; $i++) {
    $c = curl_init('http://127.0.0.1/index.php');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_multi_add_handle($multi, $c); $handles[] = $c;
}
do { curl_multi_exec($multi, $running); if ($running) curl_multi_select($multi, .1); } while ($running);
foreach ($handles as $c) {
    if (curl_getinfo($c, CURLINFO_HTTP_CODE) !== 200 || curl_multi_getcontent($c) !== 'bounded') {
        fwrite(STDERR, 'Queued probe failed: HTTP ' . curl_getinfo($c, CURLINFO_HTTP_CODE) . ' ' . curl_error($c)); exit(1);
    }
}
echo file_get_contents('/tmp/kmp-apache-capacity.json');
'''


def verify(image):
    for value in ['0', '3', '129', '999', '-1', '4; exit 0', '04']:
        result = docker('run', '--rm', '--network', 'none', '-e', f'APACHE_MAX_REQUEST_WORKERS={value}',
                        image, 'true', check=False)
        if result.returncode != 64 or 'APACHE_MAX_REQUEST_WORKERS must be' not in result.stderr:
            raise RuntimeError('Invalid worker limit did not fail before application bootstrap.')
    name = 'kmp-apache-capacity-' + uuid.uuid4().hex[:12]
    with tempfile.TemporaryDirectory(prefix='kmp-apache-capacity-') as directory:
        root = Path(directory)
        root.chmod(0o755)
        (root / 'webroot').mkdir(mode=0o755)
        (root / 'webroot').chmod(0o755)
        for relative in ['logs', 'tmp', 'images/uploaded', 'images/cache']:
            (root / relative).mkdir(parents=True, exist_ok=True)
        for path in [root / 'index.php', root / 'webroot/index.php']:
            path.write_text(PROBE)
            path.chmod(0o644)
        try:
            docker('run', '-d', '--rm', '--network', 'none', '--name', name,
                   '--entrypoint', 'apache2-foreground', '-e', 'APACHE_MAX_REQUEST_WORKERS=4',
                   '--mount', f'type=bind,source={root},target=/var/www/html,readonly', image)
            deadline = time.monotonic() + 20
            while True:
                result = docker('exec', name, 'curl', '-fsS', '--max-time', '2', 'http://127.0.0.1/index.php', check=False)
                if result.returncode == 0:
                    break
                if time.monotonic() > deadline:
                    raise RuntimeError('Isolated Apache probe did not start.')
                time.sleep(.2)
            state = json.loads(docker('exec', name, 'php', '-r', CLIENT).stdout)
            if state['active'] != 0 or not 2 <= state['peak'] <= 4:
                raise RuntimeError(f'Concurrent PHP work exceeded its configured budget: {state}')
            print(f'PASS: 24 concurrent requests queued successfully, peak PHP work {state["peak"]}; invalid limits rejected.')
        finally:
            docker('rm', '-f', name, check=False)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--image', required=True)
    verify(parser.parse_args().image)
