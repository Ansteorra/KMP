"""Exercise paired evidence and gateway rollback with isolated CLI doubles."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
COMMIT = "a" * 40
WEB = "registry.example/kmp@sha256:" + "b" * 64
LIVE = "registry.example/kmp-live@sha256:" + "c" * 64
OLD = "registry.example/kmp-live@sha256:" + "d" * 64


class LiveReleaseContract(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.folder = Path(self.directory.name)
        self.env = dict(os.environ, PATH=str(self.folder) + os.pathsep + os.environ["PATH"])
        self.env.update(KMP_TEST_STATE=str(self.folder / "state.json"),
                        KMP_TEST_PAIR=json.dumps(dict(commit=COMMIT, web=WEB, live=LIVE)),
                        KMP_REVISION_VERIFY_ATTEMPTS="1", KMP_REVISION_VERIFY_DELAY_SECONDS="0")
        self.stub("gh", r'''
import json, os, pathlib, sys
args = sys.argv[1:]
if args[0] == 'api':
    print('7' if '/workflows/' in args[1] else ('' if os.getenv('KMP_TEST_NO_EVIDENCE') else '9'))
elif args[:2] == ['run', 'download']:
    folder = pathlib.Path(args[args.index('--dir') + 1])
    (folder / 'poc-image-pair.json').write_text(os.environ['KMP_TEST_PAIR'])
else: raise SystemExit('Unexpected GitHub command')
''')
        self.stub("az", r'''
import json, os, pathlib, sys
args = sys.argv[1:]
path = pathlib.Path(os.environ['KMP_TEST_STATE'])
state = json.loads(path.read_text()) if path.exists() else {'image': os.environ['KMP_TEST_OLD'], 'revision': 'old'}
if args[:2] == ['containerapp', 'update']:
    image = args[args.index('--image') + 1]
    state = {'image': image, 'revision': 'rollback' if image == os.environ['KMP_TEST_OLD'] else 'candidate'}
    path.write_text(json.dumps(state)); print(state['revision'])
elif args[:2] == ['containerapp', 'show']:
    if '--query' in args:
        failed = os.getenv('KMP_TEST_FAIL_CANDIDATE') and state['revision'] == 'candidate'
        print(json.dumps({'provisioningState': 'Failed' if failed else 'Succeeded', 'latestRevision': state['revision'], 'readyRevision': state['revision']}))
    else: print(json.dumps({'properties': {'template': {'containers': [{'name': 'live', 'image': state['image']}]}}}))
elif args[:3] == ['containerapp', 'revision', 'show']: print(state['image'])
else: raise SystemExit('Unexpected Azure command')
''')
        self.env["KMP_TEST_OLD"] = OLD

    def stub(self, name, content):
        script = self.folder / name
        script.write_text("#!/usr/bin/env python3\n" + content)
        script.chmod(0o755)

    def run_script(self, script, *args):
        return subprocess.run(["bash", str(ROOT / script), *args], env=self.env,
                              text=True, capture_output=True, check=False)

    def pair(self):
        return self.run_script(".github/scripts/require-poc-image-pair.sh", COMMIT, WEB, LIVE)

    def gateway(self):
        return self.run_script("deploy/azure/cutover-live-gateway.sh", "--resource-group", "test",
                               "--app", "test-live", "--image", LIVE,
                               "--snapshot-dir", str(self.folder / "snapshot"))

    def test_requires_exact_successful_poc_pair(self):
        result = self.pair()
        self.assertEqual(0, result.returncode, result.stderr)
        self.env["KMP_TEST_PAIR"] = json.dumps(dict(commit=COMMIT, web=WEB, live=OLD))
        result = self.pair()
        self.assertNotEqual(0, result.returncode)
        self.assertIn("does not match", result.stderr)

    def test_missing_poc_evidence_fails_closed(self):
        self.env["KMP_TEST_NO_EVIDENCE"] = "1"
        result = self.pair()
        self.assertNotEqual(0, result.returncode)
        self.assertIn("No successful POC", result.stderr)

    def test_gateway_verifies_ready_revision_and_exact_image(self):
        result = self.gateway()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(LIVE, json.loads((self.folder / "state.json").read_text())["image"])
        self.assertTrue((self.folder / "snapshot/live.json").exists())

    def test_gateway_failure_restores_previous_image_and_stops_promotion(self):
        self.env["KMP_TEST_FAIL_CANDIDATE"] = "1"
        result = self.gateway()
        self.assertNotEqual(0, result.returncode)
        self.assertEqual(OLD, json.loads((self.folder / "state.json").read_text())["image"])
        self.assertIn("Web cutover will not proceed", result.stderr)

    def test_gateway_requires_an_immutable_rollback_image_before_mutating_azure(self):
        self.env["KMP_TEST_OLD"] = "registry.example/kmp-live:latest"
        result = self.gateway()
        self.assertNotEqual(0, result.returncode)
        self.assertIn("Existing gateway image must be immutable", result.stderr)
        self.assertFalse((self.folder / "state.json").exists())


if __name__ == "__main__":
    unittest.main()
