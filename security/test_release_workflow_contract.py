"""Exact-commit branch evidence remains required before image publication."""
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
SHA = 'a' * 40


class ReleaseWorkflowContractTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.bin = Path(self.temporary.name)
        gh = self.bin / 'gh'
        gh.write_text('''#!/usr/bin/env python3
import json, os, sys
args = sys.argv[1:]
assert args[:3] == ['api', '--method', 'GET'], args
assert 'repos/example/kmp/actions/workflows/tests.yml/runs' in args, args
assert 'branch=' + os.environ['EXPECTED_BRANCH'] in args, args
assert 'head_sha=' + os.environ['GITHUB_SHA'] in args, args
if os.environ.get('API_FAILURE'):
    sys.exit(1)
print(os.environ['WORKFLOW_RESPONSE'])
''')
        gh.chmod(0o755)

    def environment(self, branch, runs):
        env = os.environ.copy()
        env.update({
            'PATH': str(self.bin) + os.pathsep + env['PATH'],
            'GITHUB_REPOSITORY': 'example/kmp',
            'GITHUB_SHA': SHA,
            'GITHUB_REF_NAME': branch,
            'EXPECTED_BRANCH': branch,
            'GITHUB_OUTPUT': str(self.bin / 'output'),
            'WORKFLOW_RESPONSE': json.dumps({'workflow_runs': runs}),
        })
        return env

    def run_record(self, event='push', status='completed', conclusion='success'):
        return {'event': event, 'status': status, 'conclusion': conclusion,
                'html_url': 'https://github.com/example/kmp/actions/runs/123'}

    def gate_command(self):
        workflow = (ROOT / '.github/workflows/nightly.yml').read_text()
        step = workflow.split('      - name: Require successful gates for this commit\n', 1)[1]
        step = step.split('\n  build-and-push:', 1)[0]
        self.assertIn('        run: |\n', step)
        return textwrap.dedent(step.split('        run: |\n', 1)[1])

    def test_both_official_branches_run_push_quality_gates(self):
        workflow = (ROOT / '.github/workflows/tests.yml').read_text()
        branches = re.search(r'  push:\n    branches: \[([^\]]+)\]', workflow)
        self.assertIsNotNone(branches)
        self.assertEqual({'main', 'dev'}, {v.strip() for v in branches[1].split(',')})

    def test_image_gate_requires_its_own_branch_and_exact_sha(self):
        for branch in ['dev', 'main']:
            with self.subTest(branch=branch):
                result = subprocess.run(['bash', '-c', self.gate_command()], cwd=ROOT,
                                        env=self.environment(branch, [self.run_record()]),
                                        capture_output=True, text=True, timeout=5)
                self.assertEqual(0, result.returncode, result.stderr)
                self.assertIn('Verified successful tests.yml run for ' + SHA, result.stdout)

    def test_image_gate_rejects_other_refs(self):
        result = subprocess.run(['bash', '-c', self.gate_command()], cwd=ROOT,
                                env=self.environment('feature/unreviewed', [self.run_record()]),
                                capture_output=True, text=True, timeout=5)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('require official main or dev', result.stderr)

    def test_failed_push_blocks_image_gate(self):
        result = subprocess.run(['bash', '-c', self.gate_command()], cwd=ROOT,
                                env=self.environment('dev', [self.run_record(conclusion='failure')]),
                                capture_output=True, text=True, timeout=5)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('did not pass', result.stderr)

    def test_pr_only_missing_pending_and_failed_evidence_cannot_pass(self):
        for runs in [[], [self.run_record(event='pull_request')],
                     [self.run_record(status='in_progress', conclusion=None)],
                     [self.run_record(conclusion='cancelled')],
                     [self.run_record(conclusion='skipped')],
                     [self.run_record(conclusion='failure'), self.run_record(event='pull_request')]]:
            with self.subTest(runs=runs):
                result = subprocess.run(
                    ['bash', '.github/scripts/require-workflow-success.sh',
                     'tests.yml', SHA, 'dev', 'push', '0'], cwd=ROOT,
                    env=self.environment('dev', runs), capture_output=True, text=True, timeout=5)
                self.assertNotEqual(0, result.returncode)
                self.assertNotIn('Verified successful', result.stdout)

    def test_api_failure_cannot_pass(self):
        env = self.environment('dev', [self.run_record()])
        env['API_FAILURE'] = '1'
        result = subprocess.run(['bash', '-c', self.gate_command()], cwd=ROOT,
                                env=env, capture_output=True, text=True, timeout=5)
        self.assertNotEqual(0, result.returncode)

    def test_security_build_refreshes_native_packages_for_pushes_as_well_as_schedules(self):
        security = (ROOT / '.github/workflows/security.yml').read_text()
        self.assertRegex(security, r'(?m)^          no-cache-filters: runtime-base$')
        self.assertIn('cache-from: type=gha,scope=kmp-security', security)

    def poc_image_command(self):
        workflow = (ROOT / '.github/workflows/nightly-deploy-azure.yml').read_text()
        step = workflow.split('      - name: Resolve immutable source and target tags\n', 1)[1]
        step = step.split('\n  candidate-security:', 1)[0]
        return textwrap.dedent(step.split('        run: |\n', 1)[1])

    def resolve_poc_image(self, event, digest='sha256:' + 'b' * 64):
        docker = self.bin / 'docker'
        docker.write_text('''#!/usr/bin/env python3
import os, sys
assert sys.argv[1:5] == ['buildx', 'imagetools', 'inspect',
                        'ghcr.io/ansteorra/kmp:dev-aaaaaaa'], sys.argv
assert sys.argv[5:] == ['--format', '{{.Manifest.Digest}}'], sys.argv
print(os.environ['TEST_IMAGE_DIGEST'])
''')
        docker.chmod(0o755)
        env = self.environment('main' if event == 'workflow_run' else 'dev', [])
        env.update({
            'EVENT_NAME': event,
            'IMAGE_TAG': 'dev-aaaaaaa',
            'WORKFLOW_RUN_HEAD_SHA': SHA if event == 'workflow_run' else '',
            # workflow_run's checkout context belongs to main, not the dev image.
            'GITHUB_SHA': 'c' * 40 if event == 'workflow_run' else SHA,
            'GITHUB_RUN_ID': '123',
            'TEST_IMAGE_DIGEST': digest,
        })
        result = subprocess.run(['bash', '-c', self.poc_image_command()], cwd=ROOT,
                                env=env, capture_output=True, text=True, timeout=5)
        output = Path(env['GITHUB_OUTPUT'])
        values = dict(line.split('=', 1) for line in output.read_text().splitlines()) \
            if output.exists() else {}
        return result, values

    def test_automatic_poc_uses_image_source_sha_and_digest_instead_of_main_context(self):
        result, outputs = self.resolve_poc_image('workflow_run')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(SHA, outputs['source_sha'])
        self.assertEqual('poc-validated-' + SHA[:12], outputs['evidence_tag'])
        self.assertEqual('ghcr.io/ansteorra/kmp@sha256:' + 'b' * 64, outputs['source_image'])
        poc = (ROOT / '.github/workflows/nightly-deploy-azure.yml').read_text()
        ref = '      checkout_ref: ${{ needs.prepare.outputs.source_sha || github.sha }}'
        self.assertEqual(2, poc.count(ref), 'Security and deployment must use the same source commit')
        self.assertIn("github.event.workflow_run.conclusion == 'success'", poc)
        self.assertIn('    needs: [prepare, candidate-security]', poc)
        security = (ROOT / '.github/workflows/security.yml').read_text()
        self.assertRegex(security, r'workflow_call:\n    inputs:\n      checkout_ref:')
        self.assertRegex(security, r'(?m)^          ref: \$\{\{ inputs.checkout_ref \|\| github.sha \}\}$')

    def test_manual_poc_dispatch_keeps_context_checkout_without_promotion_evidence(self):
        result, outputs = self.resolve_poc_image('workflow_dispatch')
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('', outputs['source_sha'])
        self.assertEqual('', outputs['evidence_tag'])
        self.assertEqual('poc-dev-aaaaaaa-123', outputs['target_tag'])

    def test_poc_cannot_resolve_a_mutable_or_invalid_digest(self):
        result, outputs = self.resolve_poc_image('workflow_run', 'dev')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Unable to resolve an immutable digest', result.stderr)
        self.assertEqual({}, outputs)

    def test_image_build_still_depends_on_evidence_and_production_requires_main(self):
        nightly = (ROOT / '.github/workflows/nightly.yml').read_text()
        self.assertRegex(nightly, r'  build-and-push:\n    needs: \[quality-gate-evidence\]')
        self.assertIn("if: needs.quality-gate-evidence.result == 'success'", nightly)
        release = (ROOT / '.github/workflows/release.yml').read_text()
        self.assertIn('main push', release)
        self.assertIn('git merge-base --is-ancestor "$SOURCE_SHA" origin/main', release)
        self.assertIn('poc-validated-', release)


if __name__ == '__main__':
    unittest.main()
