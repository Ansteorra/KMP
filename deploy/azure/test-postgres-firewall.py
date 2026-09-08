import importlib.util
from pathlib import Path
import unittest
spec = importlib.util.spec_from_file_location('firewall', Path(__file__).with_name('reconcile-postgres-firewall.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class FirewallPlanTest(unittest.TestCase):
    def test_invalid_or_broad_source_rejected(self):
        for ip in ['0.0.0.0', '10.0.0.1', '::1', '8.8.8.0/24', 'garbage']:
            with self.subTest(ip=ip), self.assertRaises(ValueError):
                module.desired_rules([ip])

    def test_only_explicit_removals_and_no_automatic_stale_deletion(self):
        rules = [{'name': 'AllowAzureServices', 'startIpAddress': '0.0.0.0', 'endIpAddress': '0.0.0.0'},
                 {'name': 'kmp-egress-old', 'startIpAddress': '8.8.4.4', 'endIpAddress': '8.8.4.4'},
                 {'name': 'operator', 'startIpAddress': '1.1.1.1', 'endIpAddress': '1.1.1.1'}]
        additions, removals, stale, broad = module.plan(rules, module.desired_rules(['8.8.8.8']), [])
        self.assertEqual(1, len(additions))
        self.assertEqual([], removals)
        self.assertEqual(['kmp-egress-old'], stale)
        self.assertEqual(['AllowAzureServices'], broad)

    def test_repeated_plan_is_idempotent(self):
        desired = module.desired_rules(['8.8.8.8', '8.8.8.8'])
        existing = [{'name': name, 'startIpAddress': ip, 'endIpAddress': ip} for name, ip in desired.items()]
        self.assertEqual(([], [], [], []), module.plan(existing, desired, []))


if __name__ == '__main__':
    unittest.main()
