#!/usr/bin/env python3
import argparse
from pathlib import Path
from typing import Any, Dict, Iterable, List
from urllib.parse import urlparse

from pipeline_utils import (
    ensure_parent_dir,
    read_jsonl,
    update_summary,
    write_jsonl,
)


VALID_SCHEMES = {"http", "https"}


def has_valid_image(url: Any) -> bool:
    if not isinstance(url, str):
        return False
    url = url.strip()
    if not url:
        return False
    parsed = urlparse(url)
    if parsed.scheme.lower() not in VALID_SCHEMES:
        return False
    if not parsed.netloc:
        return False
    return True


def media(records: Iterable[Dict[str, Any]]):
    processed: List[Dict[str, Any]] = []
    total = 0
    valid_images = 0
    no_image = 0
    invalid_lvl1 = 0

    for record in records:
        total += 1
        image_url = record.get("Imagen_Principal")
        valid = has_valid_image(image_url)
        lvl1 = str(record.get("ID_Menu_Nvl_1", ""))
        if valid:
            valid_images += 1
            new_record = dict(record)
        else:
            no_image += 1
            new_record = dict(record)
            new_record["media_status"] = "no_image"
        if lvl1 in {"---", "25"}:
            invalid_lvl1 += 1
        processed.append(new_record)

    metrics = {
        "rows_total": total,
        "media": {
            "valid_image_percent": round((valid_images / total) * 100, 2) if total else 0.0,
            "no_image": no_image,
        },
        "invalid_lvl1": invalid_lvl1,
    }

    return processed, metrics


def stage09(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    output_path = Path(args.output)
    log_path = Path(args.log)

    summary_paths = [Path(p) for p in args.summary] if args.summary else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]

    ensure_parent_dir(log_path)
    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 09: media & taxonomy ==\n")
        if not input_path.exists():
            raise FileNotFoundError(f"No existe input: {input_path}")
        records = list(read_jsonl(input_path))
        processed, metrics = media(records)
        log.write(
            f"Total={metrics['rows_total']} válidas={metrics['media']['valid_image_percent']}% no_image={metrics['media']['no_image']}"
            f" lvl1_inconsistentes={metrics['invalid_lvl1']}.\n"
        )

    write_jsonl(output_path, processed)
    update_summary(summary_paths, "stage_09", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 09 - Media & Taxonomy")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--summary", action="append", default=[])
    return parser.parse_args()


if __name__ == "__main__":
    stage09(parse_args())
