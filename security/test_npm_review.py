"""A scoped build-only advisory review must not hide new or unreviewed findings."""
import copy
import datetime
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('security_check', Path(__file__).with_name('check.py'))
check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(check)
URL = 'https://github.com/advisories/GHSA-fc5q-39xc-vjpf'
APPROVED = {('picomatch', URL)}


class NpmReviewTest(unittest.TestCase):
    def setUp(self):
        self.report = {'metadata': {}, 'vulnerabilities': {
            'picomatch': {'severity': 'high', 'via': [
                {'name': 'picomatch', 'url': URL, 'severity': 'high'}]},
            'tool': {'severity': 'high', 'via': ['picomatch']},
        }}

    def test_only_reviewed_advisory_and_its_transitive_reports_are_allowed(self):
        self.assertEqual(['picomatch', 'tool'], check.check_report(self.report, 'npm'))
        self.assertEqual([], check.check_report(self.report, 'npm', APPROVED))

    def test_new_advisory_on_same_package_still_blocks_dependents(self):
        self.report['vulnerabilities']['picomatch']['via'].append(
            {'name': 'picomatch', 'url': 'https://github.com/advisories/GHSA-new', 'severity': 'high'})
        self.assertEqual(['picomatch', 'tool'], check.check_report(self.report, 'npm', APPROVED))

    def test_another_high_dependency_is_not_hidden(self):
        self.report['vulnerabilities']['other'] = {'severity': 'high', 'via': [
            {'name': 'other', 'url': URL, 'severity': 'high'}]}
        self.report['vulnerabilities']['tool']['via'].append('other')
        self.assertEqual(['other', 'tool'], check.check_report(self.report, 'npm', APPROVED))

    def test_critical_escalation_is_not_accepted(self):
        for location in ('package', 'advisory'):
            data = copy.deepcopy(self.report)
            issue = data['vulnerabilities']['picomatch']
            (issue if location == 'package' else issue['via'][0])['severity'] = 'critical'
            self.assertIn('picomatch', check.check_report(data, 'npm', APPROVED))

    def test_missing_edges_cycles_and_malformed_sources_fail_closed(self):
        for via in [[], ['missing'], ['tool'], [None], [{}]]:
            with self.subTest(via=via):
                self.report['vulnerabilities']['tool']['via'] = via
                self.assertIn('tool', check.check_report(self.report, 'npm', APPROVED))

    def review_fixture(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        root = Path(temporary.name)
        review = json.loads((check.ROOT / 'security/npm-picomatch-review.json').read_text())
        for relative in review['reviewed_files']:
            dest = root / relative
            dest.parent.mkdir(parents=True, exist_ok=True)
            dest.write_bytes((check.ROOT / relative).read_bytes())
        review['approvals'] = {'platform_owner': 'test-only approval', 'security_lead': 'test-only approval'}
        policy = root / 'security/npm-picomatch-review.json'
        policy.parent.mkdir(parents=True, exist_ok=True)
        policy.write_text(json.dumps(review))
        return root, policy, review

    def approved(self, root, today=datetime.date(2026, 11, 17)):
        return check.approved_npm_advisories(root / 'app', root=root, today=today)

    def test_review_requires_both_approvals(self):
        root, policy, review = self.review_fixture()
        self.assertEqual(APPROVED, self.approved(root))
        review['approvals'] = {}
        policy.write_text(json.dumps(review))
        self.assertEqual(set(), self.approved(root))
        review['approvals'] = {'platform_owner': 'test-only approval'}
        policy.write_text(json.dumps(review))
        with self.assertRaises(ValueError):
            self.approved(root)

    def test_review_expires_and_does_not_apply_to_other_locks(self):
        root, _, _ = self.review_fixture()
        self.assertEqual(APPROVED, self.approved(root, datetime.date(2026, 12, 1)))
        with self.assertRaises(ValueError):
            self.approved(root, datetime.date(2026, 12, 2))
        self.assertEqual(set(), check.approved_npm_advisories(root, root=root))

    def test_lock_and_build_boundary_drift_require_review(self):
        for path in ('app/package-lock.json', 'app/vite.config.js', 'docker/Dockerfile.prod'):
            with self.subTest(path=path):
                root, _, _ = self.review_fixture()
                with (root / path).open('a') as handle:
                    handle.write('\n')
                with self.assertRaises(ValueError):
                    self.approved(root)

    def test_missing_required_evidence_fails_closed(self):
        root, policy, review = self.review_fixture()
        del review['reviewed_files']['app/vite.config.js']
        policy.write_text(json.dumps(review))
        with self.assertRaises(ValueError):
            self.approved(root)


if __name__ == '__main__':
    unittest.main()
