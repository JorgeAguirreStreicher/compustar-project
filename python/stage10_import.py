#!/usr/bin/env python3
import argparse
import json
import random
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, MutableMapping, Optional, Tuple

from pipeline_utils import (
    ensure_parent_dir,
    parse_float,
    parse_int,
    read_jsonl,
    update_summary,
)


DEFAULT_SUMMARY_KEYS = ("stage_10",)


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


def build_name(record: Mapping[str, Any]) -> str:
    name = str(record.get("Nombre", "")).strip()
    if name:
        return name
    parts: List[str] = []
    for key in ("Marca", "Modelo", "Titulo"):
        value = str(record.get(key, "")).strip()
        if value:
            parts.append(value)
    return " ".join(parts)


def detect_existing(record: Mapping[str, Any]) -> Tuple[bool, Optional[int]]:
    possible_ids = (
        "woo_product_id",
        "product_id",
        "id_producto_woo",
        "woo_id",
        "post_id",
    )
    for key in possible_ids:
        product_id = parse_int(record.get(key))
        if product_id > 0:
            return True, product_id
    possible_flags = (
        "woo_exists",
        "exists_in_woo",
        "exists",
    )
    for key in possible_flags:
        if to_bool(record.get(key)):
            return True, None
    return False, None


def has_description(record: Mapping[str, Any]) -> bool:
    possible_flags = (
        "woo_has_description",
        "has_description",
    )
    for key in possible_flags:
        if key in record and to_bool(record.get(key)):
            return True
    possible_values = (
        "woo_description",
        "description",
        "post_content",
    )
    for key in possible_values:
        value = record.get(key)
        if isinstance(value, str) and value.strip():
            return True
    return False


def has_image(record: Mapping[str, Any]) -> bool:
    possible_flags = (
        "woo_has_image",
        "has_image",
        "image_set",
    )
    for key in possible_flags:
        if key in record and to_bool(record.get(key)):
            return True
    possible_values = (
        "woo_image",
        "image",
        "Imagen_Principal",
    )
    for key in possible_values:
        value = record.get(key)
        if isinstance(value, str) and value.strip():
            return True
    return False


def _sum_non_negative(values: Iterable[Any]) -> int:
    total = 0
    for value in values:
        qty = parse_int(value)
        if qty > 0:
            total += qty
    return total


def compute_stock_total(record: Mapping[str, Any]) -> Dict[str, Any]:
    stock_syscom = max(0, parse_int(record.get("stock_for_import")))
    other_total = 0
    mayorista_details: Dict[str, int] = {}

    stocks_mayorista = record.get("stocks_por_mayorista")
    if isinstance(stocks_mayorista, Mapping):
        for key, value in stocks_mayorista.items():
            qty = max(0, parse_int(value))
            if qty > 0:
                mayorista_details[str(key)] = qty
                other_total += qty
    elif isinstance(stocks_mayorista, list):
        subtotal = _sum_non_negative(stocks_mayorista)
        other_total += subtotal
        if subtotal > 0:
            mayorista_details["list_total"] = subtotal
    else:
        for key, value in record.items():
            if not isinstance(key, str):
                continue
            if not key.startswith("stock_"):
                continue
            if key in {"stock_for_import", "stock_total"}:
                continue
            qty = max(0, parse_int(value))
            if qty <= 0:
                continue
            mayorista_details[key] = qty
            other_total += qty

    stock_total = max(0, stock_syscom + other_total)
    return {
        "stock_syscom": stock_syscom,
        "stock_mayoristas": mayorista_details,
        "stock_total_mayoristas": stock_total,
    }


def target_weight(record: Mapping[str, Any]) -> Optional[float]:
    weight = parse_float(record.get("Peso_Kg"), -1.0)
    if weight < 0:
        return None
    return round(weight, 3)


def ensure_summary_paths(run_dir: Path, explicit: Iterable[str]) -> List[Path]:
    summary_paths = [Path(p) for p in explicit] if explicit else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]
    return summary_paths


def stage10(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    log_path = Path(args.log)
    report_path = Path(args.report)
    dry_run = bool(args.dry_run)
    summary_paths = ensure_summary_paths(run_dir, args.summary)

    ensure_parent_dir(log_path)
    ensure_parent_dir(report_path)

    created: List[Dict[str, Any]] = []
    updated: List[Dict[str, Any]] = []
    skipped: List[Dict[str, Any]] = []

    metrics: Dict[str, Any] = {
        "rows_total": 0,
        "created": 0,
        "updated": 0,
        "skipped": 0,
        "price_zero": 0,
    }

    if not input_path.exists():
        raise FileNotFoundError(f"No existe input: {input_path}")

    records = list(read_jsonl(input_path))
    metrics["rows_total"] = len(records)

    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 10: import (simulación)==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")

        for record in records:
            sku = str(record.get("sku"))
            if not sku:
                log.write("Registro sin SKU, se omite.\n")
                skipped.append(
                    {
                        "sku": sku or "",
                        "action": "skip",
                        "reason": "missing_sku",
                        "stock_total_mayoristas": 0,
                        "precio_objetivo": 0,
                    }
                )
                metrics["skipped"] += 1
                continue

            name = build_name(record)
            brand = str(record.get("Marca", "")).strip()
            category = str(record.get("ID_Menu_Nvl_3", "")).strip()

            stock_info = compute_stock_total(record)
            stock_total = stock_info["stock_total_mayoristas"]

            precio_objetivo = parse_float(record.get("price_16_final"), 0.0)
            if precio_objetivo is None or not isinstance(precio_objetivo, (int, float)):
                precio_objetivo = 0.0
            if not isinstance(precio_objetivo, (int, float)):
                precio_objetivo = 0.0

            existing, product_id = detect_existing(record)
            current_price = parse_float(record.get("woo_regular_price"), 0.0)
            current_stock = parse_int(record.get("woo_stock"), 0)

            before: Dict[str, Any] = {}
            if existing:
                if product_id:
                    before["product_id"] = product_id
                if current_price:
                    before["price"] = current_price
                before["stock"] = current_stock
                if record.get("woo_name"):
                    before["name"] = record.get("woo_name")
                if record.get("woo_category"):
                    before["category"] = record.get("woo_category")
                if record.get("woo_brand"):
                    before["brand"] = record.get("woo_brand")

            weight = target_weight(record)

            should_update_description = not has_description(record)
            should_update_image = not has_image(record)

            entry: Dict[str, Any] = {
                "sku": sku,
                "stock_total_mayoristas": stock_total,
                "precio_objetivo": round(precio_objetivo, 2) if isinstance(precio_objetivo, (int, float)) else 0.0,
                "before": before if before else None,
                "after": {
                    "name": name,
                    "brand": brand,
                    "category": category,
                    "stock": stock_total,
                    "price": round(precio_objetivo, 2) if isinstance(precio_objetivo, (int, float)) else 0.0,
                    "update_price": False,
                    "set_description": should_update_description,
                    "set_image": should_update_image,
                    "weight": weight,
                    "stock_breakdown": {
                        "syscom": stock_info["stock_syscom"],
                        "mayoristas": stock_info["stock_mayoristas"],
                    },
                },
            }

            if weight is None:
                entry["after"].pop("weight")

            if precio_objetivo <= 0:
                metrics["price_zero"] += 1
                entry["reason"] = "price_zero"
                if existing:
                    entry["action"] = "update_stock_zero_due_to_price_zero"
                    entry["after"]["stock"] = 0
                    updated.append(entry)
                    metrics["updated"] += 1
                    log.write(
                        f"{sku}: price_zero guard → stock=0, sin actualización de precio.\n"
                    )
                else:
                    entry["action"] = "skip_create"
                    skipped.append(entry)
                    metrics["skipped"] += 1
                    log.write(f"{sku}: price_zero guard → no se crea.\n")
                continue

            if stock_total <= 0:
                entry["action"] = "skip_create"
                entry["reason"] = "stock_zero"
                skipped.append(entry)
                metrics["skipped"] += 1
                log.write(f"{sku}: stock total 0 → no se crea/actualiza.\n")
                continue

            if existing:
                entry["action"] = "update"
                if precio_objetivo > 0 and (
                    current_price <= 0 or precio_objetivo < current_price
                ):
                    entry["after"]["update_price"] = True
                else:
                    entry["after"]["update_price"] = False
                updated.append(entry)
                metrics["updated"] += 1
                log.write(
                    f"{sku}: update → stock={stock_total} precio={precio_objetivo}"
                    f" update_price={entry['after']['update_price']}.\n"
                )
            else:
                entry["action"] = "create"
                created.append(entry)
                metrics["created"] += 1
                log.write(
                    f"{sku}: create → stock={stock_total} precio={precio_objetivo}.\n"
                )

        report_payload = {
            "created": created,
            "updated": updated,
            "skipped": skipped,
        }

    with report_path.open("w", encoding="utf-8") as report:
        json.dump(report_payload, report, indent=2, ensure_ascii=False, sort_keys=True)

    update_summary(summary_paths, "stage_10", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 10 - WooCommerce import plan")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--report", required=True)
    parser.add_argument("--dry-run", type=int, choices=[0, 1], default=1)
    parser.add_argument("--summary", action="append", default=[])
    return parser.parse_args()


if __name__ == "__main__":
    stage10(parse_args())
