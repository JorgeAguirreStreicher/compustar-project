#!/usr/bin/env python3
import argparse
import json
import os
import random
import shlex
import subprocess
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Optional

from pipeline_utils import ensure_parent_dir, parse_float, parse_int, update_summary


class WPCLIError(RuntimeError):
    """Raised when a WP-CLI command fails."""


class WooInspector:
    def __init__(self, wp_path: str, wp_args: str) -> None:
        self.wp_path = wp_path or "wp"
        self.wp_args = shlex.split(wp_args) if wp_args else []

    def _build_cmd(self, args: Iterable[str]) -> List[str]:
        return [self.wp_path, *self.wp_args, *list(args)]

    def run(self, args: Iterable[str], *, check: bool = True) -> subprocess.CompletedProcess:
        cmd = self._build_cmd(args)
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
        except FileNotFoundError as exc:
            raise WPCLIError(f"wp_cli_not_found:{exc}") from exc
        if check and result.returncode != 0:
            message = result.stderr.strip() or result.stdout.strip() or " ".join(cmd)
            raise WPCLIError(message)
        return result

    def find_product_id(self, sku: str) -> Optional[int]:
        if not sku:
            return None
        result = self.run(
            [
                "post",
                "list",
                "--post_type=product",
                "--meta_key=_sku",
                f"--meta_value={sku}",
                "--fields=ID",
                "--format=ids",
            ],
            check=True,
        )
        output = (result.stdout or "").strip()
        if not output:
            return None
        for part in output.split():
            try:
                product_id = int(part.strip())
            except (TypeError, ValueError):
                continue
            if product_id > 0:
                return product_id
        return None

    def get_post_field(self, product_id: int, field: str) -> str:
        result = self.run(["post", "get", str(product_id), f"--field={field}"], check=False)
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def get_post_meta(self, product_id: int, key: str) -> str:
        result = self.run(["post", "meta", "get", str(product_id), key], check=False)
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def get_terms(self, product_id: int, taxonomy: str) -> List[Dict[str, Any]]:
        result = self.run(
            [
                "term",
                "list",
                taxonomy,
                f"--object_id={product_id}",
                "--fields=term_id,name,slug",
                "--format=json",
            ],
            check=False,
        )
        if result.returncode != 0:
            return []
        try:
            data = json.loads(result.stdout or "[]")
        except json.JSONDecodeError:
            return []
        terms: List[Dict[str, Any]] = []
        if isinstance(data, list):
            for item in data:
                if isinstance(item, Mapping):
                    terms.append(dict(item))
        return terms

    def db_query(self, sql: str) -> subprocess.CompletedProcess:
        return self.run(["db", "query", sql, "--skip-column-names"], check=False)


class MirrorInspector:
    def __init__(self, inspector: WooInspector, log) -> None:
        self.inspector = inspector
        self.log = log
        self._detected = False
        self._tables: Dict[str, bool] = {}

    def detect(self) -> None:
        if self._detected:
            return
        self._detected = True
        try:
            result = self.inspector.db_query("SHOW TABLES LIKE 'wp_compu_%';")
        except WPCLIError as exc:
            if self.log:
                self.log.write(f"mirror detect error: {exc}\n")
            return
        tables = set(line.strip() for line in (result.stdout or "").splitlines() if line.strip())
        for name in ("wp_compu_products", "wp_compu_prices", "wp_compu_inventory"):
            self._tables[name] = name in tables

    def sku_present(self, table: str, sku: str) -> bool:
        self.detect()
        if not self._tables.get(table):
            return True
        sku_text = str(sku).replace("'", "''")
        result = self.inspector.db_query(
            f"SELECT 1 FROM {table} WHERE sku = '{sku_text}' LIMIT 1;"
        )
        if result.returncode != 0:
            if self.log:
                self.log.write(
                    f"mirror query error ({table}): rc={result.returncode} stderr={result.stderr.strip()}\n"
                )
            return False
        return bool((result.stdout or "").strip())

    def all_present(self, sku: str) -> bool:
        self.detect()
        for table, enabled in self._tables.items():
            if not enabled:
                continue
            if not self.sku_present(table, sku):
                return False
        return True

    @property
    def enabled(self) -> bool:
        self.detect()
        return any(self._tables.values())


def format_wp_error(exc: Exception) -> str:
    message = str(exc).strip().replace("\n", " ").replace("\r", " ")
    return f"wp_error:{message}" if message else "wp_error:unknown"


def ensure_summary_paths(run_dir: Path, explicit: Iterable[str]) -> List[Path]:
    summary_paths = [Path(path) for path in explicit] if explicit else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]
    return summary_paths


def is_action(record: Mapping[str, Any], name: str) -> bool:
    actions = record.get("actions") if isinstance(record, Mapping) else None
    if not isinstance(actions, list):
        return False
    for action in actions:
        if isinstance(action, str) and action == name:
            return True
    return False


def load_import_report(path: Path) -> Dict[str, Any]:
    if not path.exists():
        raise FileNotFoundError(f"Falta import-report: {path}")
    with path.open("r", encoding="utf-8") as fh:
        data = json.load(fh)
    if isinstance(data, Mapping) and all(key in data for key in ("created", "updated", "skipped")):
        created = data.get("created") or []
        updated = data.get("updated") or []
        skipped = data.get("skipped") or []
        if not isinstance(created, list) or not isinstance(updated, list) or not isinstance(skipped, list):
            raise ValueError("Estructura inesperada en import-report.json (keys=created,updated,skipped)")
        metrics = data.get("metrics") if isinstance(data.get("metrics"), Mapping) else {}
        return {
            "created": created,
            "updated": updated,
            "skipped": skipped,
            "metrics": dict(metrics),
        }

    if isinstance(data, Mapping) and "results" in data and "metrics" in data:
        results_raw = data.get("results") or []
        records: List[Dict[str, Any]] = []
        if isinstance(results_raw, list):
            for item in results_raw:
                if isinstance(item, Mapping):
                    records.append(dict(item))
        created = [record for record in records if is_action(record, "created")]
        updated = [record for record in records if is_action(record, "updated")]
        skipped = [record for record in records if record.get("skipped")]
        metrics = data.get("metrics") if isinstance(data.get("metrics"), Mapping) else {}
        return {
            "created": created,
            "updated": updated,
            "skipped": skipped,
            "metrics": dict(metrics),
        }

    found = list(data.keys()) if isinstance(data, Mapping) else type(data).__name__
    raise ValueError(f"Estructura inesperada en import-report.json (keys={found})")


def collect_samples(created: List[Mapping[str, Any]], updated: List[Mapping[str, Any]]) -> List[Dict[str, Any]]:
    pool: List[Mapping[str, Any]] = list(created) + list(updated)
    if not pool:
        return []
    rng = random.Random(42)
    sample_size = min(20, len(pool))
    picks = rng.sample(pool, sample_size)
    samples: List[Dict[str, Any]] = []
    for item in picks:
        after = item.get("after") if isinstance(item.get("after"), Mapping) else {}
        samples.append(
            {
                "sku": item.get("sku"),
                "action": item.get("action"),
                "reason": item.get("reason"),
                "expected_price": parse_float(after.get("price"), 0.0),
                "expected_stock": parse_int(after.get("stock"), 0),
                "expected_category": after.get("category"),
                "expected_brand": after.get("brand"),
                "expected_weight": parse_float(after.get("weight"), 0.0),
            }
        )
    return samples


def stage11(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    log_path = Path(args.log)
    report_path = Path(args.import_report)
    postcheck_path = Path(args.postcheck)
    dry_run = bool(args.dry_run)
    summary_paths = ensure_summary_paths(run_dir, args.summary)

    writer = (args.writer or os.environ.get("IMPORT_WRITER") or os.environ.get("WRITER") or "sim").strip().lower()
    if writer not in {"sim", "wp"}:
        writer = "sim"
    real_mode = writer == "wp" and not dry_run

    wp_path = args.wp_path or os.environ.get("WP_PATH", "wp")
    wp_args = args.wp_args or os.environ.get("WP_PATH_ARGS", "")

    ensure_parent_dir(log_path)
    ensure_parent_dir(postcheck_path)

    report = load_import_report(report_path)
    created = report.get("created", [])
    updated = report.get("updated", [])
    skipped = report.get("skipped", [])
    report_metrics = report.get("metrics") if isinstance(report.get("metrics"), Mapping) else {}

    counts = {
        "created": len(created),
        "updated": len(updated),
        "skipped": len(skipped),
    }

    samples = collect_samples(created, updated)

    diffs: List[Dict[str, Any]] = []
    wp_errors = 0
    check_counts = {
        "samples_total": len(samples),
        "product_found": 0,
        "category_present": 0,
        "brand_present": 0,
        "image_present": 0,
        "price_match": 0,
        "stock_match": 0,
        "weight_match": 0,
        "mirrors_present": 0,
    }

    inspector = WooInspector(wp_path, wp_args) if real_mode else None
    mirror = MirrorInspector(inspector, None) if inspector else None

    with log_path.open("w", encoding="utf-8") as log:
        log.write(f"== Stage 11: postcheck (writer={writer})==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")
        log.write(
            f"Conteos → created={counts['created']} updated={counts['updated']} skipped={counts['skipped']}\n"
        )
        if report_metrics:
            log.write(
                "Métricas import → "
                + json.dumps(report_metrics, ensure_ascii=False, sort_keys=True)
                + "\n"
            )
        if not samples:
            log.write("Sin muestras para verificar.\n")
        if real_mode:
            log.write("Modo real: usando WP-CLI para verificaciones.\n")
        else:
            log.write("Modo simulación: verificación sólo contra import-report.\n")

        if real_mode and inspector is not None:
            mirror = MirrorInspector(inspector, log)
            for sample in samples:
                sku = str(sample.get("sku") or "").strip()
                if not sku:
                    continue
                try:
                    product_id = inspector.find_product_id(sku)
                except WPCLIError as exc:
                    wp_errors += 1
                    diff = {"sku": sku, "field": "product_id", "expected": "exists", "actual": format_wp_error(exc)}
                    diffs.append(diff)
                    sample["error"] = format_wp_error(exc)
                    log.write(f"{sku}: error al buscar producto → {exc}\n")
                    continue
                if not product_id:
                    diff = {"sku": sku, "field": "product_id", "expected": "exists", "actual": "missing"}
                    diffs.append(diff)
                    sample["product_id"] = None
                    log.write(f"{sku}: producto no encontrado.\n")
                    continue

                sample["product_id"] = product_id
                check_counts["product_found"] += 1
                log.write(f"{sku}: verificación producto {product_id}.\n")

                actual_name = inspector.get_post_field(product_id, "post_title")
                actual_price = parse_float(inspector.get_post_meta(product_id, "_regular_price"), 0.0)
                actual_stock = parse_int(inspector.get_post_meta(product_id, "_stock"), 0)
                actual_weight = parse_float(inspector.get_post_meta(product_id, "_weight"), 0.0)
                thumbnail_id = inspector.get_post_meta(product_id, "_thumbnail_id")

                brand_terms = inspector.get_terms(product_id, "product_brand")
                category_terms = inspector.get_terms(product_id, "product_cat")

                sample.update(
                    {
                        "woo_name": actual_name,
                        "woo_price": round(actual_price, 2),
                        "woo_stock": actual_stock,
                        "woo_weight": actual_weight,
                        "woo_brand": brand_terms[0].get("name") if brand_terms else "",
                        "woo_category": category_terms[0].get("term_id") if category_terms else None,
                        "woo_thumbnail": thumbnail_id,
                    }
                )

                if brand_terms:
                    expected_brand = str(sample.get("expected_brand") or "").strip()
                    actual_brand = str(brand_terms[0].get("name") or "").strip()
                    if expected_brand and expected_brand.lower() != actual_brand.lower():
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "brand",
                                "expected": expected_brand,
                                "actual": actual_brand or "missing",
                            }
                        )
                    else:
                        check_counts["brand_present"] += 1
                else:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "brand",
                            "expected": sample.get("expected_brand"),
                            "actual": "missing",
                        }
                    )

                if category_terms:
                    check_counts["category_present"] += 1
                    expected_category = sample.get("expected_category")
                    actual_term = category_terms[0]
                    actual_candidates = {
                        str(actual_term.get("term_id")),
                        actual_term.get("term_id"),
                        str(actual_term.get("slug") or ""),
                        str(actual_term.get("name") or ""),
                    }
                    expected_candidates = set()
                    if isinstance(expected_category, list):
                        for item in expected_category:
                            expected_candidates.add(item)
                            expected_candidates.add(str(item))
                    elif expected_category not in {None, ""}:
                        expected_candidates.add(expected_category)
                        expected_candidates.add(str(expected_category))
                    if expected_candidates and actual_candidates.isdisjoint(expected_candidates):
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "category",
                                "expected": expected_category,
                                "actual": actual_term.get("term_id"),
                            }
                        )
                else:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "category",
                            "expected": sample.get("expected_category"),
                            "actual": "missing",
                        }
                    )

                if thumbnail_id:
                    check_counts["image_present"] += 1
                else:
                    diffs.append({"sku": sku, "field": "image", "expected": "featured", "actual": "missing"})

                guard_price_zero = sample.get("reason") == "price_zero"
                expected_price = round(sample.get("expected_price", 0.0), 2)
                if guard_price_zero:
                    if actual_stock != 0:
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "stock",
                                "expected": 0,
                                "actual": actual_stock,
                            }
                        )
                    else:
                        check_counts["stock_match"] += 1
                else:
                    if round(actual_price, 2) > expected_price + 0.01:
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "price",
                                "expected": expected_price,
                                "actual": round(actual_price, 2),
                            }
                        )
                    else:
                        check_counts["price_match"] += 1
                    expected_stock = sample.get("expected_stock", 0)
                    if actual_stock != expected_stock:
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "stock",
                                "expected": expected_stock,
                                "actual": actual_stock,
                            }
                        )
                    else:
                        check_counts["stock_match"] += 1

                expected_weight = sample.get("expected_weight")
                if expected_weight:
                    if abs(expected_weight - actual_weight) < 0.001:
                        check_counts["weight_match"] += 1
                    else:
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "weight",
                                "expected": expected_weight,
                                "actual": actual_weight,
                            }
                        )

                if mirror and mirror.enabled:
                    if mirror.all_present(sku):
                        check_counts["mirrors_present"] += 1
                    else:
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "mirror",
                                "expected": "synced",
                                "actual": "missing",
                            }
                        )
        elif samples:
            log.write("Saltando validaciones reales por modo simulación.\n")

        if diffs:
            log.write(f"Total diferencias detectadas: {len(diffs)}\n")
        else:
            log.write("Sin diferencias detectadas.\n")

    postcheck_payload = {
        "mode": "wp" if real_mode else "simulation",
        "writer": writer,
        "diffs": len(diffs),
        "checks": check_counts,
        "samples": samples,
        "issues": diffs,
        "wp_errors": wp_errors,
    }

    with postcheck_path.open("w", encoding="utf-8") as fh:
        json.dump(postcheck_payload, fh, indent=2, ensure_ascii=False, sort_keys=True)

    metrics = {
        "rows_total": counts["created"] + counts["updated"] + counts["skipped"],
        "created": counts["created"],
        "updated": counts["updated"],
        "skipped": counts["skipped"],
        "diffs": len(diffs),
        "wp_errors": wp_errors,
        "mode": "wp" if real_mode else "simulation",
    }
    if report_metrics:
        metrics["import_metrics"] = report_metrics

    update_summary(summary_paths, "stage_11", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 11 - Post import verification")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--import-report", required=True)
    parser.add_argument("--postcheck", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--dry-run", type=int, choices=[0, 1], default=1)
    parser.add_argument("--summary", action="append", default=[])
    parser.add_argument("--run-id", default="")
    parser.add_argument("--writer", choices=["sim", "wp"], default=None)
    parser.add_argument("--wp-path", default=None)
    parser.add_argument("--wp-args", default=None)
    return parser.parse_args()


if __name__ == "__main__":
    stage11(parse_args())
