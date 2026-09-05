import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('security_check', Path(__file__).with_name('check.py'))
check = importlib.util.module_from_spec(spec)
spec.loader.exec_module(check)


class ScannerContractTest(unittest.TestCase):
    def test_error_response_is_not_a_clean_audit(self):
        for kind in ['npm', 'composer']:
            for value in [{}, {'error': {'code': 'NETWORK'}}, [], None]:
                with self.subTest(kind=kind, value=value), self.assertRaises(ValueError):
                    check.check_report(value, kind)

    def test_npm_high_and_critical_block(self):
        data = {'metadata': {}, 'vulnerabilities': {'critical-parser': {'severity': 'critical'},
                'high-parser': {'severity': 'high'}, 'reviewed-tool': {'severity': 'moderate'}}}
        self.assertEqual(['critical-parser', 'high-parser'], check.check_report(data, 'npm'))

    def test_composer_advisories_and_abandonment_block(self):
        self.assertEqual(['parser', 'old-package'], check.check_report(
            {'advisories': {'parser': [{}]}, 'abandoned': {'old-package': None}}, 'composer'))

    def test_ruby_platform_builds_share_the_actual_gem_version(self):
        lock = ("    nokogiri (1.19.4-aarch64-linux-gnu)\n"
                "    nokogiri (1.19.4-x86_64-darwin)\n"
                "    parser (2.0.0.rc1)\n")
        self.assertEqual([('nokogiri', '1.19.4'), ('parser', '2.0.0.rc1')], check.ruby_packages(lock))

    def test_production_builds_preserve_the_hardened_exclusions(self):
        self.assertEqual((check.ROOT / '.dockerignore').read_text(),
                         (check.ROOT / 'docker/.dockerignore.prod').read_text())

    def test_clean_reports(self):
        self.assertEqual([], check.check_report({'metadata': {}, 'vulnerabilities': {}}, 'npm'))
        self.assertEqual([], check.check_report({'advisories': [], 'abandoned': {}}, 'composer'))


if __name__ == '__main__':
    unittest.main()
