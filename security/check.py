#!/usr/bin/env python3
"""Fail-closed dependency and immutable-image checks. Reports contain no application records."""
import argparse
import datetime
import json
import os
import re
import urllib.request
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def check_report(data, kind):
    if not isinstance(data, dict) or data.get('error') or data.get('errors'):
        raise ValueError('Scanner returned an invalid/error report')
    if kind == 'npm':
        if not isinstance(data.get('vulnerabilities'), dict) or not isinstance(data.get('metadata'), dict):
            raise ValueError('npm audit report is incomplete')
        return sorted(name for name, value in data['vulnerabilities'].items()
                      if value.get('severity') in ('high', 'critical'))
    if kind == 'composer':
        if 'advisories' not in data or not isinstance(data['advisories'], (dict, list)):
            raise ValueError('Composer audit report is incomplete')
        return sorted(data['advisories']) + sorted(data.get('abandoned', {}))
    raise ValueError('Unknown scanner report kind')


def run_report(command, directory, output, kind):
    if shutil.which(command[0]) is None:
        raise RuntimeError('Required scanner is missing: ' + command[0])
    result = subprocess.run(command, cwd=directory, capture_output=True, text=True, timeout=300)
    output.write_text(result.stdout)
    if result.returncode not in (0, 1, 2, 3):
        raise RuntimeError('Scanner failed: ' + command[0])
    data = json.loads(result.stdout)
    failures = check_report(data, kind)
    if result.returncode and not failures and kind == 'composer':
        raise RuntimeError('Composer failed without a recognized advisory report')
    if failures:
        raise RuntimeError(kind + ' security gate failed: ' + ', '.join(failures))
    print(kind + ' audit passed: ' + str(directory.relative_to(ROOT) or '.'))


def ruby_packages(lock):
    """Ruby platform suffixes describe a build, not a Gem::Version prerelease."""
    packages = set()
    for name, version in re.findall(r'^    ([A-Za-z0-9_.-]+) \(([^ )]+)\)$', lock, re.M):
        version = re.sub(r'-(?:aarch64|arm64|x86_64|x64|x86|universal|java)(?:[-\w.]*)$', '', version)
        packages.add((name, version))
    return sorted(packages)


def audit_ruby_locks(reports):
    manifests = [ROOT / 'docs' / 'Gemfile']
    if (ROOT / 'Gemfile').exists():
        manifests.append(ROOT / 'Gemfile')
    for manifest in manifests:
        path = manifest.with_name('Gemfile.lock')
        if not path.exists():
            raise RuntimeError('Required documentation lockfile is missing')
        packages = ruby_packages(path.read_text())
        if not packages:
            raise ValueError('Ruby dependency inventory is empty')
        request = urllib.request.Request('https://api.osv.dev/v1/querybatch',
            data=json.dumps({'queries': [{'package': {'ecosystem': 'RubyGems', 'name': name}, 'version': version}
                for name, version in packages]}).encode(), headers={'Content-Type': 'application/json'})
        with urllib.request.urlopen(request, timeout=90) as response:
            data = json.load(response)
        if not isinstance(data.get('results'), list) or len(data['results']) != len(packages):
            raise ValueError('Ruby advisory service returned an incomplete report')
        (reports / ('ruby-' + path.parent.name + '.json')).write_text(json.dumps(data))
        if any(result.get('vulns') for result in data['results']):
            raise RuntimeError('Ruby documentation dependencies have advisory matches requiring review')
        print('Ruby lock audit passed: ' + str(path.relative_to(ROOT)))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--image', help='Optional final image to scan (local or immutable registry reference)')
    parser.add_argument('--reports', type=Path)
    args = parser.parse_args()
    os.umask(0o077)
    reports = args.reports or Path(tempfile.mkdtemp(prefix='kmp-security-'))
    reports.mkdir(parents=True, exist_ok=True, mode=0o700)
    errors = []
    for kind, directory, command in [
        ('composer', ROOT / 'app', ['composer', 'audit', '--locked', '--format=json']),
        ('npm', ROOT, ['npm', 'audit', '--json']),
        ('npm', ROOT / 'app', ['npm', 'audit', '--json']),
    ]:
        try:
            run_report(command, directory, reports / (kind + '-' + directory.name + '.json'), kind)
        except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
            errors.append(str(error))
    try:
        audit_ruby_locks(reports)
    except (OSError, ValueError, RuntimeError) as error:
        errors.append(str(error))
    if args.image:
        try:
            if shutil.which('trivy') is None:
                raise RuntimeError('Required final-image scanner is missing: trivy')
            image_report = reports / 'image.json'
            subprocess.run(['trivy', 'image', '--scanners', 'vuln', '--disable-telemetry', '--offline-scan',
                            '--format', 'json', '--output', str(image_report), args.image], check=True, timeout=900)
            data = json.loads(image_report.read_text())
            if not isinstance(data.get('Results'), list) or not data.get('Metadata'):
                raise ValueError('Image scanner report is incomplete')
            exceptions = json.loads((ROOT / 'security' / 'exceptions.json').read_text())
            approved = set()
            for exception in exceptions:
                if not all(exception.get(k) for k in ['id', 'package', 'owner', 'reason', 'expires']):
                    raise ValueError('Security exception lacks required review metadata')
                if datetime.date.fromisoformat(exception['expires']) <= datetime.date.today():
                    raise ValueError('Security exception has expired: ' + exception['id'])
                approved.add((exception['id'], exception['package']))
            blockers = set()
            for result in data['Results']:
                for issue in result.get('Vulnerabilities', []):
                    key = (issue['VulnerabilityID'], issue['PkgName'])
                    if issue.get('FixedVersion') and issue.get('Severity') in ('HIGH', 'CRITICAL') and key not in approved:
                        blockers.add(key)
            subprocess.run(['trivy', 'convert', '--format', 'cyclonedx', '--output',
                            str(reports / 'image.cdx.json'), str(image_report)], check=True, timeout=120)
            if blockers:
                raise RuntimeError('Fixable image High/Critical findings: ' + ', '.join(':'.join(x) for x in sorted(blockers)))
            print('Final-image gate passed; unfixed/lower-severity findings remain in the report for triage.')
        except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
            errors.append(str(error))
    print('Security reports: ' + str(reports))
    for error in errors:
        print(error, file=sys.stderr)
    return bool(errors)


if __name__ == '__main__':
    sys.exit(main())
