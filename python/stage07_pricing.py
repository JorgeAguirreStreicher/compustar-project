#!/usr/bin/env python3
import argparse
import math
from collections import Counter
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping

from pipeline_utils import (
    ensure_parent_dir,
    load_margin_rules,
    parse_float,
    read_jsonl,
    round_059,
    update_summary,
    write_jsonl,
)


def apply_margin(cost_mxn: float, margin_type: str, margin_value: float) -> float:
    if margin_type == "FIXED":
        return max(0.0, cost_mxn + margin_value)
    # Default percent
    return max(0.0, cost_mxn * (1.0 + margin_value))


def round_currency(value: float) -> int:
    if not math.isfinite(value):
        return 0
    try:
        return int(Decimal(value).quantize(Decimal('1'), rounding=ROUND_HALF_UP))
    except InvalidOperation:
        return 0


def pricing(records: Iterable[Dict[str, Any]], rules: Mapping[str, Mapping[str, Any]]):
    total = 0
    matched = 0
    defaulted = 0
    net_values: List[float] = []
    distribution: Counter[int] = Counter()

    processed: List[Dict[str, Any]] = []

    default_rule = rules.get("default", {"type": "PERCENT", "value": 0.0})

    for record in records:
        total += 1
        lvl3 = str(record.get("ID_Menu_Nvl_3", ""))
        rule = rules.get(lvl3)
        if rule is None:
            defaulted += 1
            rule = default_rule
        else:
            matched += 1

        margin_type = str(rule.get("type", "PERCENT")).upper()
        margin_value = float(rule.get("value", 0.0))

        costo_base_usd = parse_float(record.get("Su_Precio"), 0.0)
        tipo_cambio = parse_float(record.get("Tipo_de_Cambio"), 0.0)
        costo_base_mxn = costo_base_usd * tipo_cambio

        net = apply_margin(costo_base_mxn, margin_type, margin_value)
        net = round(net, 2)

        gross_16 = round_currency(net * 1.16)
        gross_8 = round_currency(net * 1.08)

        price_16 = round_059(gross_16)
        price_8 = round_059(gross_8)

        net_values.append(net)
        distribution[price_16] += 1

        record = dict(record)
        record.update(
            {
                "costo_base_usd": round(costo_base_usd, 4),
                "costo_base_mxn": round(costo_base_mxn, 4),
                "margin_type": margin_type,
                "margin_value": margin_value,
                "net": net,
                "gross_16": gross_16,
                "gross_8": gross_8,
                "price_16_final": price_16,
                "price_8_final": price_8,
            }
        )
        processed.append(record)

    net_min = min(net_values) if net_values else 0.0
    net_max = max(net_values) if net_values else 0.0
    net_avg = sum(net_values) / len(net_values) if net_values else 0.0

    metrics = {
        "rows_total": total,
        "margin_rules": {
            "by_lvl3": matched,
            "default": defaulted,
        },
        "net": {
            "min": round(net_min, 2),
            "max": round(net_max, 2),
            "avg": round(net_avg, 2),
        },
        "price_16_distribution": {str(price): count for price, count in sorted(distribution.items())},
    }

    return processed, metrics


def stage07(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    output_path = Path(args.output)
    log_path = Path(args.log)
    margin_config = Path(args.margin_config)

    summary_paths = [Path(p) for p in args.summary] if args.summary else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json"]
        final_summary = run_dir / "final" / "summary.json"
        summary_paths.append(final_summary)

    ensure_parent_dir(log_path)
    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 07: pricing ==\n")
        if not input_path.exists():
            raise FileNotFoundError(f"No existe input: {input_path}")
        records = list(read_jsonl(input_path))
        if not records:
            log.write("Input vacío, nada que procesar\n")
            processed = []
            metrics = {
                "rows_total": 0,
                "margin_rules": {"by_lvl3": 0, "default": 0},
                "net": {"min": 0, "max": 0, "avg": 0},
                "price_16_distribution": {},
            }
        else:
            rules = load_margin_rules(margin_config)
            processed, metrics = pricing(records, rules)
            log.write(
                f"Procesados {metrics['rows_total']} registros. Reglas específicas: {metrics['margin_rules']['by_lvl3']}, "
                f"default: {metrics['margin_rules']['default']}.\n"
            )
            log.write(
                f"Net min={metrics['net']['min']} max={metrics['net']['max']} avg={metrics['net']['avg']}.\n"
            )

    write_jsonl(output_path, processed)
    update_summary(summary_paths, "stage_07", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 07 - Pricing")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--margin-config", required=True)
    parser.add_argument("--summary", action="append", default=[])
    return parser.parse_args()


if __name__ == "__main__":
    stage07(parse_args())
