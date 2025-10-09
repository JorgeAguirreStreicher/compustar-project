#!/usr/bin/env python3
import argparse
import json
import random
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping

from pipeline_utils import ensure_parent_dir, update_summary


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

    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 11: post-import check (simulación)==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")
        log.write(
            f"Conteos → created={counts['created']} updated={counts['updated']} skipped={counts['skipped']}\n"
        )
        if price_zero_items:
            log.write(f"Guardas price_zero: {len(price_zero_items)} casos.\n")
        if diffs:
            log.write(f"Se detectaron {len(diffs)} diferencias.\n")
        else:
            log.write("Sin diferencias detectadas (modo simulación).\n")

    postcheck_payload = {
        "counts": counts,
        "samples": samples,
        "diffs": diffs,
        "price_zero_guard": price_zero_items,
        "mode": "simulation" if dry_run else "simulation_no_wp",
    }

    with postcheck_path.open("w", encoding="utf-8") as fh:
        json.dump(postcheck_payload, fh, indent=2, ensure_ascii=False, sort_keys=True)

    # README para docs
    readme_dir = run_dir / "docs" / "runs" / run_id / "step-11"
    ensure_parent_dir(readme_dir / "dummy")
    readme_path = readme_dir / "README.md"
    with readme_path.open("w", encoding="utf-8") as readme:
        readme.write("# Stage 11 - Postcheck (simulación)\n\n")
        readme.write(f"- Run ID: {run_id}\n")
        readme.write(f"- Dry-run: {'sí' if dry_run else 'no'}\n")
        readme.write(
            f"- Conteos: created={counts['created']} updated={counts['updated']} skipped={counts['skipped']}\n"
        )
        readme.write(f"- Casos price_zero: {len(price_zero_items)}\n")

    metrics = {
        "rows_total": counts["created"] + counts["updated"] + counts["skipped"],
        "created": counts["created"],
        "updated": counts["updated"],
        "skipped": counts["skipped"],
        "price_zero": len(price_zero_items),
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
    return parser.parse_args()


if __name__ == "__main__":
    stage11(parse_args())
