import argparse
import json
import os
import sys
import tempfile
import unittest
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parents[1]
PYTHON_DIR = ROOT_DIR / "python"
for candidate in (ROOT_DIR, PYTHON_DIR):
    candidate_str = str(candidate)
    if candidate_str not in sys.path:
        sys.path.insert(0, candidate_str)

from python import stage11_postcheck


class LoadImportReportTest(unittest.TestCase):
    def test_accepts_legacy_structure(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            report_path = Path(tmpdir) / "import-report.json"
            payload = {
                "created": [{"sku": "NEW"}],
                "updated": [{"sku": "UPD"}],
                "skipped": [],
                "metrics": {"processed": 2},
            }
            with report_path.open("w", encoding="utf-8") as fh:
                json.dump(payload, fh)

            report = stage11_postcheck.load_import_report(report_path)

            self.assertEqual(report["created"], payload["created"])
            self.assertEqual(report["updated"], payload["updated"])
            self.assertEqual(report["skipped"], payload["skipped"])
            self.assertEqual(report["metrics"], payload["metrics"])

    def test_accepts_fast_structure(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            report_path = Path(tmpdir) / "import-report.json"
            payload = {
                "results": [
                    {
                        "sku": "SKU-CREATED",
                        "actions": ["created", "cat_assigned"],
                        "skipped": [],
                    },
                    {
                        "sku": "SKU-UPDATED",
                        "actions": ["updated", "price_updated"],
                        "skipped": [],
                    },
                    {
                        "sku": "SKU-SKIPPED",
                        "actions": ["audit_meta"],
                        "skipped": ["no_category"],
                    },
                ],
                "metrics": {"processed": 3, "updated": 1},
            }
            with report_path.open("w", encoding="utf-8") as fh:
                json.dump(payload, fh)

            report = stage11_postcheck.load_import_report(report_path)

            self.assertEqual(len(report["created"]), 1)
            self.assertEqual(report["created"][0]["sku"], "SKU-CREATED")
            self.assertEqual(len(report["updated"]), 1)
            self.assertEqual(report["updated"][0]["sku"], "SKU-UPDATED")
            self.assertEqual(len(report["skipped"]), 1)
            self.assertEqual(report["skipped"][0]["sku"], "SKU-SKIPPED")
            self.assertEqual(report["metrics"], payload["metrics"])

    def test_rejects_unknown_structure(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            report_path = Path(tmpdir) / "import-report.json"
            payload = {"unexpected": []}
            with report_path.open("w", encoding="utf-8") as fh:
                json.dump(payload, fh)

            with self.assertRaisesRegex(ValueError, "keys=.*unexpected"):
                stage11_postcheck.load_import_report(report_path)


class Stage11SmokeTest(unittest.TestCase):
    def setUp(self) -> None:
        self._orig_env = os.environ.copy()

    def tearDown(self) -> None:
        os.environ.clear()
        os.environ.update(self._orig_env)

    def test_stage11_handles_fast_report(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            run_dir = Path(tmpdir) / "run"
            report_path = run_dir / "import-report.json"
            postcheck_path = run_dir / "out" / "postcheck.json"
            log_path = run_dir / "logs" / "stage11.log"

            run_dir.mkdir(parents=True, exist_ok=True)

            payload = {
                "results": [
                    {
                        "sku": "SKU-FAST",
                        "actions": ["updated", "price_updated", "stock_set"],
                        "skipped": [],
                        "after": {
                            "price": "123.45",
                            "stock": 7,
                            "category": ["cat-1"],
                            "brand": "FastBrand",
                            "weight": "1.2",
                        },
                    }
                ],
                "metrics": {"processed": 1, "updated": 1},
            }
            with report_path.open("w", encoding="utf-8") as fh:
                json.dump(payload, fh)

            args = argparse.Namespace(
                run_dir=str(run_dir),
                import_report=str(report_path),
                postcheck=str(postcheck_path),
                log=str(log_path),
                dry_run=1,
                summary=[],
                run_id="test",
                writer="sim",
                wp_path=None,
                wp_args=None,
            )

            stage11_postcheck.stage11(args)

            self.assertTrue(postcheck_path.exists())
            with postcheck_path.open("r", encoding="utf-8") as fh:
                postcheck_data = json.load(fh)

            self.assertEqual(postcheck_data["diffs"], 0)
            self.assertEqual(postcheck_data["wp_errors"], 0)
            self.assertTrue((run_dir / "summary.json").exists())


if __name__ == "__main__":
    unittest.main()
