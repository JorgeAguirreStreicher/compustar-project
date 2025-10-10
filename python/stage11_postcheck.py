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


class WooReader:
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
        if isinstance(data, list):
            return [item for item in data if isinstance(item, Mapping)]
        return []


def format_wp_error(exc: Exception) -> str:
    message = str(exc).strip().replace("\r", " ").replace("\n", " ")
    message = " ".join(message.split())
    return f"wp_error:{message}" if message else "wp_error:unknown"


def to_bool(value: Any) -> bool:
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return bool(value)
    if isinstance(value, str):
        text = value.strip().lower()
        if text in {"1", "true", "yes", "y", "si", "sí"}:
            return True
    return False


def ensure_summary_paths(run_dir: Path, explicit: Iterable[str]) -> List[Path]:
    summary_paths = [Path(p) for p in explicit] if explicit else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]
    return summary_paths


def load_import_report(path: Path) -> Mapping[str, List[Mapping[str, Any]]]:
    if not path.exists():
        raise FileNotFoundError(f"Falta import-report: {path}")
    with path.open("r", encoding="utf-8") as fh:
        data = json.load(fh)
    if not isinstance(data, Mapping):
        raise ValueError("import-report.json inválido")
    created = data.get("created")
    updated = data.get("updated")
    skipped = data.get("skipped")
    if not isinstance(created, list) or not isinstance(updated, list) or not isinstance(skipped, list):
        raise ValueError("Estructura inesperada en import-report.json")
    return {
        "created": created,
        "updated": updated,
        "skipped": skipped,
    }


def collect_samples(created: List[Mapping[str, Any]], updated: List[Mapping[str, Any]]) -> List[Dict[str, Any]]:
    pool: List[Mapping[str, Any]] = list(created) + list(updated)
    if not pool:
        return []
    rng = random.Random(42)
    sample_size = min(20, len(pool))
    selection = rng.sample(pool, sample_size)
    samples: List[Dict[str, Any]] = []
    for item in selection:
        after = item.get("after") if isinstance(item.get("after"), Mapping) else {}
        samples.append(
            {
                "sku": item.get("sku"),
                "name": after.get("name"),
                "price": after.get("price"),
                "stock": after.get("stock"),
                "category": after.get("category"),
                "image_set": after.get("set_image"),
                "brand": after.get("brand"),
                "action": item.get("action"),
                "reason": item.get("reason"),
            }
        )
    return samples


def price_zero_guard_items(report: Mapping[str, List[Mapping[str, Any]]]) -> List[Dict[str, Any]]:
    items: List[Dict[str, Any]] = []
    for bucket in ("created", "updated", "skipped"):
        for entry in report.get(bucket, []):
            if not isinstance(entry, Mapping):
                continue
            if entry.get("reason") == "price_zero":
                items.append(
                    {
                        "sku": entry.get("sku"),
                        "action": entry.get("action"),
                        "precio_objetivo": entry.get("precio_objetivo"),
                        "stock_total_mayoristas": entry.get("stock_total_mayoristas"),
                    }
                )
    return items


def stage11(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    log_path = Path(args.log)
    report_path = Path(args.import_report)
    postcheck_path = Path(args.postcheck)
    dry_run = bool(args.dry_run)
    run_id = args.run_id or run_dir.name
    summary_paths = ensure_summary_paths(run_dir, args.summary)

    env_writer = (args.writer or os.environ.get("IMPORT_WRITER") or os.environ.get("WRITER") or "sim").strip().lower()
    writer = env_writer if env_writer in {"sim", "wp"} else "sim"
    if writer == "wp" and dry_run and to_bool(os.environ.get("FORCE_WRITE")):
        dry_run = False
    wp_path = args.wp_path or os.environ.get("WP_PATH", "wp")
    wp_args = args.wp_args or os.environ.get("WP_PATH_ARGS", "")
    real_mode = writer == "wp" and not dry_run

    ensure_parent_dir(log_path)
    ensure_parent_dir(postcheck_path)

    report = load_import_report(report_path)

    created = report.get("created", [])
    updated = report.get("updated", [])
    skipped = report.get("skipped", [])

    counts = {
        "created": len(created),
        "updated": len(updated),
        "skipped": len(skipped),
    }

    price_zero_items = price_zero_guard_items(report)

    samples = collect_samples(created, updated)

    diffs: List[Dict[str, Any]] = []
    wp_error_count = 0

    woo_reader = WooReader(wp_path, wp_args) if real_mode else None

    with log_path.open("w", encoding="utf-8") as log:
        log.write(f"== Stage 11: post-import check (writer={writer})==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")
        log.write(
            f"Conteos → created={counts['created']} updated={counts['updated']} skipped={counts['skipped']}\n"
        )
        if price_zero_items:
            log.write(f"Guardas price_zero: {len(price_zero_items)} casos.\n")
        if real_mode and woo_reader is not None:
            log.write("Modo real: consultando WooCommerce vía WP-CLI.\n")
            for sample in samples:
                sku = str(sample.get("sku") or "").strip()
                if not sku:
                    continue
                try:
                    product_id = woo_reader.find_product_id(sku)
                except WPCLIError as exc:
                    sample["woo_error"] = format_wp_error(exc)
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "product_id",
                            "expected": "exists",
                            "actual": "wp_error",
                        }
                    )
                    wp_error_count += 1
                    log.write(f"{sku}: error consultando Woo → {exc}\n")
                    continue
                if not product_id:
                    sample["woo_found"] = False
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "product_id",
                            "expected": "exists",
                            "actual": "missing",
                        }
                    )
                    log.write(f"{sku}: producto no encontrado en Woo.\n")
                    continue

                sample["woo_found"] = True
                log.write(f"{sku}: verificación en Woo ID {product_id}.\n")

                actual_name = woo_reader.get_post_field(product_id, "post_title")
                actual_name = (actual_name or "").strip()
                actual_price_value = parse_float(
                    woo_reader.get_post_meta(product_id, "_price"), 0.0
                )
                actual_stock_value = parse_int(
                    woo_reader.get_post_meta(product_id, "_stock"), 0
                )
                brand_terms = woo_reader.get_terms(product_id, "product_brand")
                actual_brand = ""
                if brand_terms:
                    actual_brand = str(brand_terms[0].get("name") or "").strip()
                cat_terms = woo_reader.get_terms(product_id, "product_cat")
                actual_category = ""
                if cat_terms:
                    term = cat_terms[0]
                    actual_category = str(
                        term.get("term_id") or term.get("name") or ""
                    ).strip()

                sample["woo_name"] = actual_name
                sample["woo_price"] = round(actual_price_value, 2)
                sample["woo_stock"] = actual_stock_value
                sample["woo_brand"] = actual_brand
                sample["woo_category"] = actual_category

                expected_name = str(sample.get("name") or "").strip()
                expected_price_value = round(parse_float(sample.get("price"), 0.0), 2)
                expected_stock_value = parse_int(sample.get("stock"), 0)
                expected_brand = str(sample.get("brand") or "").strip()
                expected_category = str(sample.get("category") or "").strip()

                if expected_name and actual_name != expected_name:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "name",
                            "expected": expected_name,
                            "actual": actual_name,
                        }
                    )

                guard_price_zero = sample.get("reason") == "price_zero"
                if guard_price_zero:
                    sample["price_guard"] = True
                else:
                    if expected_price_value != round(actual_price_value, 2):
                        diffs.append(
                            {
                                "sku": sku,
                                "field": "price",
                                "expected": expected_price_value,
                                "actual": round(actual_price_value, 2),
                            }
                        )

                if expected_stock_value != actual_stock_value:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "stock",
                            "expected": expected_stock_value,
                            "actual": actual_stock_value,
                        }
                    )

                if expected_brand and actual_brand != expected_brand:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "brand",
                            "expected": expected_brand,
                            "actual": actual_brand,
                        }
                    )

                if expected_category and actual_category != expected_category:
                    diffs.append(
                        {
                            "sku": sku,
                            "field": "category",
                            "expected": expected_category,
                            "actual": actual_category,
                        }
                    )
        else:
            log.write("Modo simulación (sin consulta a Woo).\n")

        if diffs:
            log.write(f"Se detectaron {len(diffs)} diferencias.\n")
        else:
            if real_mode:
                log.write("Sin diferencias detectadas contra Woo.\n")
            else:
                log.write("Sin diferencias detectadas (modo simulación).\n")

    postcheck_payload = {
        "counts": counts,
        "samples": samples,
        "diffs": diffs,
        "price_zero_guard": price_zero_items,
        "mode": "wp" if real_mode else "simulation",
        "writer": writer,
        "wp_errors": wp_error_count,
    }

    with postcheck_path.open("w", encoding="utf-8") as fh:
        json.dump(postcheck_payload, fh, indent=2, ensure_ascii=False, sort_keys=True)

    # README para docs
    readme_dir = run_dir / "docs" / "runs" / run_id / "step-11"
    ensure_parent_dir(readme_dir / "dummy")
    readme_path = readme_dir / "README.md"
    with readme_path.open("w", encoding="utf-8") as readme:
        readme.write(f"# Stage 11 - Postcheck (writer={writer})\n\n")
        readme.write(f"- Run ID: {run_id}\n")
        readme.write(f"- Dry-run: {'sí' if dry_run else 'no'}\n")
        readme.write(f"- Modo: {'Woo real' if real_mode else 'Simulación'}\n")
        readme.write(
            f"- Conteos: created={counts['created']} updated={counts['updated']} skipped={counts['skipped']}\n"
        )
        readme.write(f"- Casos price_zero: {len(price_zero_items)}\n")
        readme.write(f"- Diferencias detectadas: {len(diffs)}\n")
        if real_mode:
            readme.write(f"- WP-CLI errores: {wp_error_count}\n")

    metrics = {
        "rows_total": counts["created"] + counts["updated"] + counts["skipped"],
        "created": counts["created"],
        "updated": counts["updated"],
        "skipped": counts["skipped"],
        "price_zero": len(price_zero_items),
        "diffs": len(diffs),
        "mode": "wp" if real_mode else "simulation",
        "writer": writer,
        "wp_errors": wp_error_count,
    }

    update_summary(summary_paths, "stage_11", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 11 - Post import verification")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--import-report", required=True)
    parser.add_argument("--postcheck", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--dry-run", type=int, choices=[0, 1], default=1)
    parser.add_argument("--run-id", default="")
    parser.add_argument("--summary", action="append", default=[])
    parser.add_argument("--writer", choices=["sim", "wp"], default=None)
    parser.add_argument("--wp-path", default=None)
    parser.add_argument("--wp-args", default=None)
    return parser.parse_args()


if __name__ == "__main__":
    stage11(parse_args())
