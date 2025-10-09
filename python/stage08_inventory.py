#!/usr/bin/env python3
import argparse
from pathlib import Path
from typing import Any, Dict, Iterable, List

from pipeline_utils import (
    ensure_parent_dir,
    parse_int,
    read_jsonl,
    update_summary,
    write_jsonl,
)


def compute_stock(record: Dict[str, Any]) -> int:
    stock_main = parse_int(record.get("Almacen_15"), 0)
    stock_tj = parse_int(record.get("Almacen_15_Tijuana"), 0)
    total = stock_main + stock_tj
    return max(0, total)


def inventory(records: Iterable[Dict[str, Any]]):
    processed: List[Dict[str, Any]] = []
    total = 0
    in_stock = 0

    for record in records:
        total += 1
        stock_for_import = compute_stock(record)
        if stock_for_import > 0:
            in_stock += 1
        new_record = dict(record)
        new_record["stock_for_import"] = stock_for_import
        processed.append(new_record)

    metrics = {
        "rows_total": total,
        "stock": {
            ">0": in_stock,
            "=0": total - in_stock,
        },
    }

    return processed, metrics


def stage08(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    output_path = Path(args.output)
    log_path = Path(args.log)

    summary_paths = [Path(p) for p in args.summary] if args.summary else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]

    ensure_parent_dir(log_path)
    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 08: inventory ==\n")
        if not input_path.exists():
            raise FileNotFoundError(f"No existe input: {input_path}")
        records = list(read_jsonl(input_path))
        processed, metrics = inventory(records)
        log.write(
            f"Registros: {metrics['rows_total']}. Con stock>0: {metrics['stock']['>0']}. Sin stock: {metrics['stock']['=0']}.\n"
        )

    write_jsonl(output_path, processed)
    update_summary(summary_paths, "stage_08", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 08 - Inventory")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--summary", action="append", default=[])
    return parser.parse_args()


if __name__ == "__main__":
    stage08(parse_args())
