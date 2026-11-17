#!/usr/bin/env python3
"""Fail-closed dependency and immutable-image checks. Reports contain no application records."""
import argparse
import datetime
import hashlib
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


def npm_reviewed(name, vulnerabilities, approved, visiting=frozenset()):
    """Follow npm's advisory graph; unknown edges, cycles and new findings still block."""
    if name in visiting or name not in vulnerabilities:
        return False
    issue = vulnerabilities[name]
    via = issue.get('via')
    if not isinstance(via, list) or not via:
        return False
    for source in via:
        if isinstance(source, str):
            if not npm_reviewed(source, vulnerabilities, approved, visiting | {name}):
                return False
        elif isinstance(source, dict):
            severity = source.get('severity')
            if severity in ('info', 'low', 'moderate'):
                continue
            key = (source.get('name'), source.get('url'))
            if severity != 'high' or key not in approved or source.get('name') != name:
                return False
        else:
            return False
    return True


def approved_npm_advisories(directory, root=ROOT, today=None):
    """An unapproved, expired or changed reachability review cannot waive a finding."""
    if directory != root / 'app':
        return set()
    review = json.loads((root / 'security/npm-picomatch-review.json').read_text())
    approvals = review.get('approvals', {})
    if not approvals.get('platform_owner') and not approvals.get('security_lead'):
        print('Picomatch review is pending acceptance; npm High/Critical findings still block.')
        return set()
    if not all(approvals.get(role) for role in ('platform_owner', 'security_lead')):
        raise ValueError('npm review requires Platform Owner and Security Lead acceptance')
    if not all(review.get(key) for key in ('owner', 'reason', 'evidence', 'expires', 'reviewed_files')):
        raise ValueError('npm review lacks required metadata')
    if datetime.date.fromisoformat(review['expires']) < (
            today or datetime.datetime.now(datetime.timezone.utc).date()):
        raise ValueError('Picomatch reachability review has expired')
    required = {'app/package.json', 'app/package-lock.json', 'app/vite.config.js',
                'docker/Dockerfile.prod', 'services/live-updates/package-lock.json'}
    if not required.issubset(review['reviewed_files']):
        raise ValueError('npm review lacks dependency/build boundary evidence')
    for relative, digest in review['reviewed_files'].items():
        path = root / relative
        if not path.resolve().is_relative_to(root.resolve()):
            raise ValueError('npm review path leaves repository')
        if hashlib.sha256(path.read_bytes()).hexdigest() != digest:
            raise ValueError('Picomatch review must be renewed after changing ' + relative)
    lock = json.loads((directory / 'package-lock.json').read_text())
    copies = [value for path, value in lock['packages'].items()
              if path.endswith('node_modules/picomatch')]
    if not copies or any(value.get('dev') is not True or value['version'] not in ('2.3.2', '4.0.7')
                         for value in copies):
        raise ValueError('Picomatch must remain a reviewed build/test-only dependency')
    print('Applying accepted picomatch reachability review until ' + review['expires'])
    return {('picomatch', 'https://github.com/advisories/GHSA-fc5q-39xc-vjpf')}


def check_report(data, kind, approved=frozenset()):
    if not isinstance(data, dict) or data.get('error') or data.get('errors'):
        raise ValueError('Scanner returned an invalid/error report')
    if kind == 'npm':
        if not isinstance(data.get('vulnerabilities'), dict) or not isinstance(data.get('metadata'), dict):
            raise ValueError('npm audit report is incomplete')
        return sorted(name for name, value in data['vulnerabilities'].items()
                      if value.get('severity') in ('high', 'critical')
                      and (value.get('severity') == 'critical' or not approved
                           or not npm_reviewed(name, data['vulnerabilities'], approved)))
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
    approved = approved_npm_advisories(directory) if kind == 'npm' else set()
    failures = check_report(data, kind, approved)
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


def verify_image_php(image):
    """Check PHP itself; OS package scanners can miss PHP compiled from source."""
    subprocess.run([
        'docker', 'run', '--rm', '--network', 'none', '--read-only',
        '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
        '--mount', 'type=bind,src=' + str(ROOT / 'docker/verify-php-version.php')
        + ',dst=/verify-kmp-php-version.php,readonly',
        '--entrypoint', 'php', image, '/verify-kmp-php-version.php',
    ], check=True, timeout=180)


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
            verify_image_php(args.image)
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
