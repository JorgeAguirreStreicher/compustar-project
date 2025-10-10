#!/usr/bin/env python3
"""Stage 04 - Resolve category mapping.

Este stage toma los registros validados del proveedor, busca su categoría
equivalente en WooCommerce y genera los artefactos necesarios para las etapas
posteriores.  El flujo general es:

1. Leer `validated.jsonl` de forma streaming.
2. Consultar la tabla puente `compu_cats_map` mediante `wp db query`.
3. Verificar que cada `term_id` exista en `wp_term_taxonomy` con
   `taxonomy = 'product_cat'`.
4. Escribir `resolved.jsonl` con el `woo_term_id` válido (cuando aplique) y
   dejar constancia del estado de resolución.
5. Generar reportes auditables (`unmapped.csv`, `invalid_term_ids.csv` y
   `metrics.json`).

La implementación evita cargar el JSON completo en memoria y está diseñada para
procesar archivos grandes sin problemas de memoria.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import shlex
import subprocess
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Dict, Iterable, Iterator, List, Mapping, MutableMapping, Optional, Sequence, Tuple

from pipeline_utils import ensure_parent_dir, parse_int, update_summary


class WPCLIError(RuntimeError):
    """Excepción cuando `wp` regresa un código diferente a cero."""


class WPCLI:
    """Wrapper liviano para ejecutar comandos de WP-CLI."""

    def __init__(self, binary: str, wp_path: Optional[str], extra_args: str = "") -> None:
        self.binary = binary or "wp"
        self.wp_path = (wp_path or "").strip() or None
        self.extra_args: List[str] = shlex.split(extra_args) if extra_args else []

    def _build_cmd(self, args: Sequence[str]) -> List[str]:
        cmd: List[str] = [self.binary]
        if self.wp_path:
            cmd.append(f"--path={self.wp_path}")
        cmd.extend(self.extra_args)
        cmd.extend(args)
        return cmd

    def run(self, args: Sequence[str], *, check: bool = True) -> subprocess.CompletedProcess:
        cmd = self._build_cmd(list(args))
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
        except FileNotFoundError as exc:  # pragma: no cover - entorno sin wp-cli
            raise WPCLIError(f"wp_cli_not_found:{exc}") from exc
        if check and result.returncode != 0:
            stderr = (result.stderr or "").strip()
            stdout = (result.stdout or "").strip()
            message = stderr or stdout or "wp_cli_error"
            raise WPCLIError(message)
        return result

    def db_query(self, sql: str) -> subprocess.CompletedProcess:
        return self.run(["db", "query", sql, "--skip-column-names"])


MapRow = Dict[str, Any]


def _parse_tabular(stdout: str, expected_columns: int) -> Iterator[List[str]]:
    for raw_line in stdout.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        parts = line.split("\t")
        if expected_columns and len(parts) < expected_columns:
            # Rellenar con strings vacíos para evitar IndexError aguas abajo.
            parts.extend([""] * (expected_columns - len(parts)))
        yield parts


def load_valid_term_ids(client: WPCLI, table_prefix: str) -> Tuple[set[int], Dict[int, str]]:
    sql = (
        "SELECT term_id, taxonomy "
        f"FROM {table_prefix}term_taxonomy WHERE taxonomy = 'product_cat'"
    )
    result = client.db_query(sql)
    term_ids: set[int] = set()
    taxonomy_map: Dict[int, str] = {}
    for term_id_str, taxonomy in _parse_tabular(result.stdout or "", 2):
        term_id = parse_int(term_id_str, 0)
        if term_id > 0:
            term_ids.add(term_id)
            taxonomy_map[term_id] = taxonomy
    return term_ids, taxonomy_map


def load_category_map(
    client: WPCLI,
    table_prefix: str,
) -> Tuple[Dict[int, MapRow], List[Dict[str, Any]], Dict[int, Dict[str, str]]]:
    table = f"{table_prefix}compu_cats_map"
    sql = (
        "SELECT vendor_l3_id, term_id, l1, l2, l3, match_type, matched_name, created_at, updated_at "
        f"FROM {table}"
    )
    result = client.db_query(sql)
    rows_by_vendor: Dict[int, List[MapRow]] = defaultdict(list)
    for parts in _parse_tabular(result.stdout or "", 9):
        vendor_id = parse_int(parts[0], 0)
        term_id = parse_int(parts[1], 0)
        row: MapRow = {
            "vendor_l3_id": vendor_id,
            "term_id": term_id,
            "l1": parts[2].strip(),
            "l2": parts[3].strip(),
            "l3": parts[4].strip(),
            "match_type": parts[5].strip(),
            "matched_name": parts[6].strip(),
            "created_at": parts[7].strip(),
            "updated_at": parts[8].strip(),
        }
        if vendor_id <= 0:
            continue
        rows_by_vendor[vendor_id].append(row)

    mapping: Dict[int, MapRow] = {}
    invalid_rows: List[Dict[str, Any]] = []
    invalid_reason_by_vendor: Dict[int, Dict[str, str]] = {}

    term_ids, taxonomy_map = load_valid_term_ids(client, table_prefix)

    for vendor_id, rows in rows_by_vendor.items():
        valid_rows = [row for row in rows if parse_int(row.get("term_id"), 0) > 0]
        unique_term_ids = {parse_int(row.get("term_id"), 0) for row in valid_rows}
        if len(unique_term_ids) > 1:
            term_list = ",".join(str(tid) for tid in sorted(unique_term_ids))
            reason = "ambiguous_vendor_l3_id"
            invalid_reason_by_vendor[vendor_id] = {"reason": reason, "details": term_list}
            for row in rows:
                invalid_rows.append({**row, "reason": reason, "details": term_list})
            continue

        term_id = next(iter(unique_term_ids)) if unique_term_ids else 0
        if term_id <= 0:
            reason = "invalid_term_id"
            invalid_reason_by_vendor[vendor_id] = {"reason": reason, "details": ""}
            for row in rows:
                invalid_rows.append({**row, "reason": reason, "details": ""})
            continue

        if term_id not in term_ids:
            reason = "term_not_found"
            details = taxonomy_map.get(term_id, "")
            invalid_reason_by_vendor[vendor_id] = {"reason": reason, "details": details}
            for row in rows:
                invalid_rows.append({**row, "reason": reason, "details": details})
            continue

        chosen = rows[0].copy()
        chosen["term_id"] = term_id
        mapping[vendor_id] = chosen

    return mapping, invalid_rows, invalid_reason_by_vendor


LEVEL_FIELD_CANDIDATES = {
    "l1": [
        "l1",
        "Menu_Nvl_1",
        "menu_nvl_1",
        "menu_lvl1",
        "Categoria_Nvl_1",
        "categoria_nvl_1",
        "vendor_l1",
        "vendor_l1_name",
    ],
    "l2": [
        "l2",
        "Menu_Nvl_2",
        "menu_nvl_2",
        "menu_lvl2",
        "Categoria_Nvl_2",
        "categoria_nvl_2",
        "vendor_l2",
        "vendor_l2_name",
    ],
    "l3": [
        "l3",
        "Menu_Nvl_3",
        "menu_nvl_3",
        "menu_lvl3",
        "Categoria_Nvl_3",
        "categoria_nvl_3",
        "vendor_l3",
        "vendor_l3_name",
    ],
}


def extract_level(record: Mapping[str, Any], level_key: str) -> str:
    for candidate in LEVEL_FIELD_CANDIDATES.get(level_key, []):
        value = record.get(candidate)
        if value is None:
            continue
        text = str(value).strip()
        if text:
            return text
    return ""


def resolve_records(
    input_path: Path,
    output_path: Path,
    mapping: Mapping[int, MapRow],
    invalid_vendor_info: Mapping[int, Mapping[str, str]],
    require_term: bool,
    log,
    log_interval: int,
) -> Tuple[int, Counter, Dict[str, int], Dict[Tuple[int, str, str, str], int], Counter, int]:
    ensure_parent_dir(output_path)
    status_counts: Counter[str] = Counter()
    reason_counts: Dict[str, int] = defaultdict(int)
    unmapped_counter: Dict[Tuple[int, str, str, str], int] = defaultdict(int)
    level_counters: Counter[str] = Counter()
    level2_counters: Counter[str] = Counter()
    level3_counters: Counter[str] = Counter()
    total_rows = 0
    mapped_rows = 0
    invalid_vendor_ids = set(invalid_vendor_info.keys())

    with input_path.open("r", encoding="utf-8") as src, output_path.open(
        "w", encoding="utf-8"
    ) as dst:
        for line in src:
            line = line.strip()
            if not line:
                continue
            total_rows += 1
            try:
                record = json.loads(line)
            except json.JSONDecodeError:
                status_counts["json_error"] += 1
                reason_counts["json_error"] += 1
                continue

            if not isinstance(record, MutableMapping):
                status_counts["invalid_record"] += 1
                reason_counts["invalid_record"] += 1
                continue

            record = dict(record)

            vendor_id = parse_int(
                record.get("vendor_l3_id")
                or record.get("ID_Menu_Nvl_3")
                or record.get("id_menu_nvl_3"),
                0,
            )
            level1 = extract_level(record, "l1")
            level2 = extract_level(record, "l2")
            level3 = extract_level(record, "l3")

            if level1:
                level_counters[level1] += 1
            if level2:
                level2_counters[level2] += 1
            if level3:
                level3_counters[level3] += 1

            status = "mapped"
            reason = ""

            if vendor_id <= 0:
                status = "missing_vendor_l3_id"
                reason = "missing_vendor_l3_id"
            elif vendor_id in invalid_vendor_ids:
                status = "invalid_term"
                reason = invalid_vendor_info[vendor_id].get("reason", "invalid_term")
            elif vendor_id not in mapping:
                status = "unmapped"
                reason = "missing_in_map"
            else:
                map_row = mapping[vendor_id]
                woo_term_id = parse_int(map_row.get("term_id"), 0)
                if woo_term_id > 0:
                    record["woo_term_id"] = woo_term_id
                    category_path = " > ".join(
                        [part for part in (map_row.get("l1"), map_row.get("l2"), map_row.get("l3")) if part]
                    )
                    if category_path:
                        record["category_path_src"] = category_path
                    mapped_rows += 1
                    status_counts[status] += 1
                else:
                    status = "invalid_term"
                    reason = "invalid_term_id"

            if status != "mapped":
                status_counts[status] += 1
                reason_key = reason or status
                reason_counts[reason_key] += 1
                if reason_key == "missing_in_map" and vendor_id > 0:
                    unmapped_counter[(vendor_id, level1, level2, level3)] += 1
                if require_term:
                    record["term_required"] = True
            else:
                record.pop("term_required", None)

            record["term_status"] = status
            if reason:
                record["term_reason"] = reason
            elif "term_reason" in record:
                record.pop("term_reason")

            dst.write(json.dumps(record, ensure_ascii=False) + "\n")

            if total_rows % max(1, log_interval) == 0:
                log.write(
                    f"procesadas {total_rows} filas → mapped={mapped_rows} "
                    f"unmapped={status_counts.get('unmapped', 0)}\n"
                )

    level_metrics = {
        "l1": level_counters,
        "l2": level2_counters,
        "l3": level3_counters,
    }

    return total_rows, status_counts, reason_counts, unmapped_counter, level_metrics, mapped_rows


def top_from_counter(counter: Counter[str], limit: int = 10) -> List[Tuple[str, int]]:
    return [(name, count) for name, count in counter.most_common(limit)]


def write_unmapped_csv(path: Path, data: Mapping[Tuple[int, str, str, str], int]) -> None:
    ensure_parent_dir(path)
    with path.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.writer(fh)
        writer.writerow(["vendor_l3_id", "l1", "l2", "l3", "count"])
        for (vendor_id, l1, l2, l3), count in sorted(
            data.items(), key=lambda item: (-item[1], item[0][0])
        ):
            writer.writerow([vendor_id, l1, l2, l3, count])


def write_invalid_csv(path: Path, rows: Iterable[Mapping[str, Any]]) -> None:
    ensure_parent_dir(path)
    columns = [
        "vendor_l3_id",
        "term_id",
        "l1",
        "l2",
        "l3",
        "match_type",
        "matched_name",
        "reason",
        "details",
        "created_at",
        "updated_at",
    ]
    with path.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=columns)
        writer.writeheader()
        sorted_rows = sorted(
            rows,
            key=lambda item: (
                parse_int(item.get("vendor_l3_id"), 0),
                parse_int(item.get("term_id"), 0),
                item.get("reason", ""),
            ),
        )
        for row in sorted_rows:
            writer.writerow({col: row.get(col, "") for col in columns})


def stage04(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input) if args.input else run_dir / "validated.jsonl"
    output_path = Path(args.output) if args.output else run_dir / "resolved.jsonl"
    log_path = Path(args.log) if args.log else run_dir / "logs" / "stage-04.log"
    unmapped_path = Path(args.unmapped) if args.unmapped else run_dir / "unmapped.csv"
    invalid_path = (
        Path(args.invalid) if args.invalid else run_dir / "invalid_term_ids.csv"
    )
    metrics_path = Path(args.metrics) if args.metrics else run_dir / "metrics.json"
    stage_metrics_path = run_dir / "stage-04.metrics.json"

    ensure_parent_dir(log_path)

    summary_paths = [Path(p) for p in args.summary] if args.summary else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]

    require_term_env = os.environ.get("REQUIRE_TERM")
    require_term = (
        bool(args.require_term)
        if args.require_term is not None
        else require_term_env in {"1", "true", "TRUE", "yes", "YES"}
    )

    wp_cli = WPCLI(args.wp_cli, args.wp_path, args.wp_args or "")

    if not input_path.exists():
        raise FileNotFoundError(f"No existe input: {input_path}")

    with log_path.open("w", encoding="utf-8") as log:
        log.write("== Stage 04: resolve map ==\n")
        log.write(f"Input: {input_path}\n")
        log.write(f"Output: {output_path}\n")

        log.write("Cargando mapeo de categorías...\n")
        mapping, invalid_rows, invalid_reason_by_vendor = load_category_map(
            wp_cli, args.table_prefix
        )
        log.write(
            f"Mapeos válidos: {len(mapping)}. inválidos: {len(invalid_reason_by_vendor)}.\n"
        )

        total_rows, status_counts, reason_counts, unmapped_data, level_metrics, mapped_rows = resolve_records(
            input_path,
            output_path,
            mapping,
            invalid_reason_by_vendor,
            require_term,
            log,
            max(1, args.log_interval),
        )

        log.write(
            f"Filas procesadas: {total_rows}. Con woo_term_id: {mapped_rows}. "
            f"Sin mapeo: {status_counts.get('unmapped', 0)}.\n"
        )
        for reason, count in sorted(reason_counts.items(), key=lambda item: item[0]):
            log.write(f"motivo {reason}: {count}\n")

    write_unmapped_csv(unmapped_path, unmapped_data)
    write_invalid_csv(invalid_path, invalid_rows)

    metrics = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "rows_total": total_rows,
        "rows_mapped": mapped_rows,
        "rows_unmapped": int(status_counts.get("unmapped", 0)),
        "rows_invalid_term": int(status_counts.get("invalid_term", 0)),
        "rows_missing_vendor": int(status_counts.get("missing_vendor_l3_id", 0)),
        "json_errors": int(status_counts.get("json_error", 0)),
        "require_term": require_term,
        "mapping_available": len(mapping),
        "mapping_invalid": len(invalid_reason_by_vendor),
        "top_vendor_l1": top_from_counter(level_metrics["l1"]),
        "top_vendor_l2": top_from_counter(level_metrics["l2"]),
        "top_vendor_l3": top_from_counter(level_metrics["l3"]),
        "status_counts": dict(status_counts),
        "reason_counts": dict(reason_counts),
        "artifacts": {
            "resolved": str(output_path),
            "unmapped": str(unmapped_path),
            "invalid_term_ids": str(invalid_path),
            "log": str(log_path),
        },
    }

    ensure_parent_dir(metrics_path)
    with metrics_path.open("w", encoding="utf-8") as fh:
        json.dump(metrics, fh, indent=2, ensure_ascii=False, sort_keys=True)

    ensure_parent_dir(stage_metrics_path)
    with stage_metrics_path.open("w", encoding="utf-8") as fh:
        json.dump(metrics, fh, indent=2, ensure_ascii=False, sort_keys=True)

    update_summary(summary_paths, "stage_04", metrics)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 04 - Resolve category mapping")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", default=None)
    parser.add_argument("--output", default=None)
    parser.add_argument("--log", default=None)
    parser.add_argument("--unmapped", default=None)
    parser.add_argument("--invalid", default=None)
    parser.add_argument("--metrics", default=None)
    parser.add_argument("--summary", action="append", default=[])
    parser.add_argument("--wp-cli", default=os.environ.get("WP_CLI", "wp"))
    parser.add_argument("--wp-path", default=os.environ.get("WP_PATH"))
    parser.add_argument("--wp-args", default=os.environ.get("WP_PATH_ARGS", ""))
    parser.add_argument("--table-prefix", default=os.environ.get("WP_TABLE_PREFIX", "wp_"))
    parser.add_argument("--require-term", type=int, choices=[0, 1], default=None)
    parser.add_argument("--log-interval", type=int, default=10000)
    return parser.parse_args()


if __name__ == "__main__":
    stage04(parse_args())
