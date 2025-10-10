import json
import os
import sys
import tempfile
import unittest
from pathlib import Path
from typing import List
from unittest.mock import patch

ROOT_DIR = Path(__file__).resolve().parents[1]
PYTHON_DIR = ROOT_DIR / "python"
for candidate in (ROOT_DIR, PYTHON_DIR):
    candidate_str = str(candidate)
    if candidate_str not in sys.path:
        sys.path.insert(0, candidate_str)

from python import stage10_import


class Stage10ImportFastApplyTest(unittest.TestCase):
    def setUp(self) -> None:
        self._orig_env = os.environ.copy()

    def tearDown(self) -> None:
        os.environ.clear()
        os.environ.update(self._orig_env)

    def test_stage10_builds_payloads_and_updates_metrics(self) -> None:
        with tempfile.TemporaryDirectory() as tmpdir:
            run_dir = Path(tmpdir) / "run"
            input_path = run_dir / "input.jsonl"
            log_path = run_dir / "logs" / "stage10.log"
            report_path = run_dir / "reports" / "stage10-report.json"

            run_dir.mkdir(parents=True, exist_ok=True)
            (run_dir / "logs").mkdir(parents=True, exist_ok=True)
            (run_dir / "reports").mkdir(parents=True, exist_ok=True)

            records = [
                {
                    "sku": "SKU-LOWER",
                    "price_16_final": 900,
                    "Marca": "Acme",
                    "stock_total_mayoristas": 12,
                    "woo_product_id": 101,
                    "category_term_id": 501,
                    "Descripcion": "Desc one",
                    "Imagen_Principal": "https://example.com/one.jpg",
                    "audit_hash": "hash-lower",
                },
                {
                    "sku": "SKU-EQUAL",
                    "price_16_final": 1000,
                    "Marca": "Acme",
                    "stock_total_mayoristas": 8,
                    "woo_product_id": 102,
                    "category_term_id": 502,
                    "Descripcion": "Desc two",
                    "Imagen_Principal": "https://example.com/two.jpg",
                    "audit_hash": "hash-equal",
                },
                {
                    "sku": "SKU-HIGHER",
                    "price_16_final": 1100,
                    "Marca": "Acme",
                    "stock_total_mayoristas": 5,
                    "woo_product_id": 103,
                    "category_term_id": 503,
                    "Descripcion": "Desc three",
                    "Imagen_Principal": "https://example.com/three.jpg",
                    "audit_hash": "hash-higher",
                },
                {
                    "sku": "SKU-NEW-GOOD",
                    "price_16_final": 1200,
                    "Marca": "NewBrand",
                    "stock_total_mayoristas": 20,
                    "category_term_id": 504,
                    "Descripcion": "Desc four",
                    "Imagen_Principal": "https://example.com/four.jpg",
                    "audit_hash": "hash-new",
                },
                {
                    "sku": "SKU-NEW-NOCAT",
                    "price_16_final": 700,
                    "Marca": "Other",
                    "stock_total_mayoristas": 7,
                    "Descripcion": "Desc five",
                    "Imagen_Principal": "https://example.com/five.jpg",
                    "audit_hash": "hash-nocat",
                },
            ]

            with input_path.open("w", encoding="utf-8") as fh:
                for record in records:
                    fh.write(json.dumps(record, ensure_ascii=False) + "\n")

            os.environ["ST10_EXECUTOR_PATH"] = "/fake/stage10_apply_fast.php"
            os.environ["WP_ROOT"] = "/fake/wp"

            args = stage10_import.argparse.Namespace(
                run_dir=str(run_dir),
                input=str(input_path),
                log=str(log_path),
                report=str(report_path),
                dry_run=0,
                summary=[],
                writer="wp",
                wp_path="/usr/local/bin/wp",
                wp_args="",
                run_id="test-run",
            )

            responses: List[dict] = [
                {
                    "returncode": 0,
                    "stdout": json.dumps(
                        {
                            "sku": "SKU-LOWER",
                            "id": 101,
                            "actions": [
                                "price_updated",
                                "stock_set",
                                "cat_assigned",
                                "audit_meta",
                                "published",
                                "updated",
                            ],
                            "skipped": [],
                            "errors": [],
                        }
                    )
                    + "\n",
                    "stderr": "",
                },
                {
                    "returncode": 0,
                    "stdout": json.dumps(
                        {
                            "sku": "SKU-EQUAL",
                            "id": 102,
                            "actions": [
                                "stock_set",
                                "cat_assigned",
                                "audit_meta",
                                "published",
                                "updated",
                            ],
                            "skipped": ["price_not_lower"],
                            "errors": [],
                        }
                    )
                    + "\n",
                    "stderr": "",
                },
                {
                    "returncode": 1,
                    "stdout": "",
                    "stderr": "simulated failure",
                },
                {
                    "returncode": 0,
                    "stdout": json.dumps(
                        {
                            "sku": "SKU-HIGHER",
                            "id": 103,
                            "actions": [
                                "stock_set",
                                "cat_assigned",
                                "audit_meta",
                                "published",
                                "updated",
                            ],
                            "skipped": ["price_not_lower"],
                            "errors": [],
                        }
                    )
                    + "\n",
                    "stderr": "",
                },
                {
                    "returncode": 0,
                    "stdout": json.dumps(
                        {
                            "sku": "SKU-NEW-GOOD",
                            "id": 999,
                            "actions": [
                                "stock_set",
                                "cat_assigned",
                                "price_updated",
                                "audit_meta",
                                "published",
                                "featured_image_set",
                                "brand_assigned",
                                "created",
                            ],
                            "skipped": [],
                            "errors": [],
                        }
                    )
                    + "\n",
                    "stderr": "",
                },
                {
                    "returncode": 0,
                    "stdout": json.dumps(
                        {
                            "sku": "SKU-NEW-NOCAT",
                            "id": None,
                            "actions": [],
                            "skipped": ["missing_category"],
                            "errors": [],
                        }
                    )
                    + "\n",
                    "stderr": "",
                },
            ]

            call_args: List[List[str]] = []
            env_records: List[dict] = []

            def fake_run(cmd, capture_output, text, env, timeout):
                self.assertTrue(capture_output)
                self.assertTrue(text)
                self.assertEqual(timeout, 15)
                call_args.append(cmd)
                env_records.append(env)
                response = responses.pop(0)
                return stage10_import.subprocess.CompletedProcess(
                    cmd, response["returncode"], response["stdout"], response["stderr"]
                )

            with patch("python.stage10_import.subprocess.run", side_effect=fake_run):
                with patch("python.stage10_import.time.sleep") as fake_sleep:
                    fake_sleep.return_value = None
                    stage10_import.stage10(args)

            self.assertFalse(responses)
            self.assertGreaterEqual(len(call_args), 5)

            for recorded_env in env_records:
                self.assertEqual(recorded_env.get("ST10_SKIP_GALLERY"), "1")
                self.assertEqual(recorded_env.get("ST10_SKIP_ATTRS"), "1")
                self.assertEqual(recorded_env.get("ST10_BRAND_ONLY_ON_NEW"), "1")
                self.assertEqual(recorded_env.get("ST10_ASSIGN_CATS_ONLY"), "1")
                self.assertEqual(recorded_env.get("ST10_PRICE_RULE"), "LOWER_ONLY_META")
                self.assertEqual(recorded_env.get("RUN_DIR"), str(run_dir))

            self.assertTrue(all("--skip-themes" in cmd for cmd in call_args))
            self.assertTrue(any("stage10_apply_fast.php" in " ".join(cmd) for cmd in call_args))

            with log_path.open("r", encoding="utf-8") as fh:
                log_lines = [json.loads(line) for line in fh if line.strip()]
            self.assertEqual(len(log_lines), 5)

            fast_log_path = run_dir / "logs" / "stage-10.log"
            with fast_log_path.open("r", encoding="utf-8") as fh:
                fast_lines = [json.loads(line) for line in fh if line.strip()]
            self.assertEqual(log_lines, fast_lines)

            with report_path.open("r", encoding="utf-8") as fh:
                report_payload = json.load(fh)
            metrics = report_payload["metrics"]

            self.assertEqual(metrics["rows_total"], 5)
            self.assertEqual(metrics["processed"], 5)
            self.assertEqual(metrics["created"], 1)
            self.assertEqual(metrics["updated"], 3)
            self.assertEqual(metrics["skipped"], 1)
            self.assertEqual(metrics["updated_price"], 2)
            self.assertEqual(metrics["kept_price"], 2)
            self.assertEqual(metrics["stock_set"], 4)
            self.assertEqual(metrics["cat_assigned"], 4)
            self.assertEqual(metrics["skipped_no_cat"], 1)
            self.assertEqual(metrics["errors"], 0)

            summary_path = run_dir / "summary.json"
            with summary_path.open("r", encoding="utf-8") as fh:
                summary = json.load(fh)
            self.assertIn("stage_10", summary)
            self.assertEqual(summary["stage_10"], metrics)

            final_summary = run_dir / "final" / "summary.json"
            with final_summary.open("r", encoding="utf-8") as fh:
                final_data = json.load(fh)
            self.assertIn("stage_10", final_data)


if __name__ == "__main__":
    unittest.main()
