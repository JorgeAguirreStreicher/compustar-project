#!/usr/bin/env python3
import argparse
import json
import os
import random
import shlex
import subprocess
import time
from pathlib import Path
from typing import Any, Dict, Iterable, Iterator, List, Mapping, MutableMapping, Optional, Sequence, Tuple

from pipeline_utils import (
    ensure_parent_dir,
    parse_float,
    parse_int,
    read_jsonl,
    update_summary,
)


class WPCLIError(RuntimeError):
    """Raised when a WP-CLI command fails."""


def slugify(text: str) -> str:
    normalized = str(text or "").strip().lower()
    if not normalized:
        return "general"
    cleaned: List[str] = []
    for char in normalized:
        if char.isalnum():
            cleaned.append(char)
        elif char in {" ", "-", "_", ".", "/"}:
            cleaned.append("-" if char in {" ", "/"} else char)
    slug = "".join(cleaned)
    while "--" in slug:
        slug = slug.replace("--", "-")
    slug = slug.strip("-_.")
    return slug or "general"


def normalize_category_path(raw: Any) -> List[str]:
    if raw is None:
        return []
    if isinstance(raw, (list, tuple)):
        parts: List[str] = []
        for item in raw:
            for segment in normalize_category_path(item):
                if segment:
                    parts.append(segment)
        return parts
    text = str(raw).strip()
    if not text:
        return []
    if ">" in text:
        parts = [segment.strip() for segment in text.split(">")]
    elif "|" in text:
        parts = [segment.strip() for segment in text.split("|")]
    elif "/" in text:
        parts = [segment.strip() for segment in text.split("/")]
    else:
        return [text]
    return [part for part in parts if part]


def build_name(record: Mapping[str, Any]) -> str:
    explicit = str(record.get("Nombre", "")).strip()
    if explicit:
        return explicit
    parts: List[str] = []
    for key in ("Marca", "Modelo", "Titulo", "Title"):
        value = str(record.get(key, "")).strip()
        if value:
            parts.append(value)
    fallback = str(record.get("sku", "")).strip()
    if parts:
        return " ".join(parts)
    return fallback or "Producto"


def extract_description(record: Mapping[str, Any]) -> str:
    for key in ("Descripcion", "descripcion", "description", "Descripcion_HTML", "descripcion_html"):
        value = record.get(key)
        if isinstance(value, str) and value.strip():
            return value.strip()
    return ""


def extract_image_source(record: Mapping[str, Any]) -> str:
    for key in (
        "Imagen_Principal",
        "image",
        "image_url",
        "image_path",
        "image_local_path",
        "imagen",
    ):
        value = record.get(key)
        if isinstance(value, str) and value.strip():
            return value.strip()
    return ""


def compute_stock_info(record: Mapping[str, Any]) -> Dict[str, Any]:
    total = parse_int(record.get("stock_total_mayoristas"), -1)
    syscom = parse_int(record.get("stock_for_import"), 0)
    mayoristas: Dict[str, int] = {}
    if isinstance(record.get("stocks_por_mayorista"), Mapping):
        for key, value in record["stocks_por_mayorista"].items():
            qty = max(0, parse_int(value))
            if qty:
                mayoristas[str(key)] = qty
    for key, value in record.items():
        if not isinstance(key, str):
            continue
        if not key.startswith("stock_"):
            continue
        if key in {"stock_for_import", "stock_total_mayoristas"}:
            continue
        qty = max(0, parse_int(value))
        if qty:
            mayoristas[key] = qty
    if total < 0:
        total = syscom + sum(mayoristas.values())
    total = max(0, total)
    return {
        "stock_total_mayoristas": total,
        "stock_syscom": max(0, syscom),
        "stock_mayoristas": mayoristas,
    }


class WooClient:
    def __init__(
        self,
        wp_path: str,
        wp_args: str,
        log,
        write_enabled: bool,
        retries: int = 2,
    ) -> None:
        self.wp_path = wp_path or "wp"
        self.wp_args = shlex.split(wp_args) if wp_args else []
        self.log = log
        self.write_enabled = write_enabled
        self.retries = max(0, retries)
        self._term_cache: Dict[Tuple[str, str, int], Dict[str, Any]] = {}
        self._term_by_name_cache: Dict[Tuple[str, str], Dict[str, Any]] = {}

    def _build_cmd(self, args: Iterable[str]) -> List[str]:
        return [self.wp_path, *self.wp_args, *list(args)]

    def _format_cmd(self, cmd: Sequence[str]) -> str:
        return " ".join(shlex.quote(part) for part in cmd)

    def run(
        self,
        args: Iterable[str],
        *,
        write: bool = False,
        check: bool = True,
    ) -> subprocess.CompletedProcess:
        cmd = self._build_cmd(args)
        cmd_fmt = self._format_cmd(cmd)
        if write and not self.write_enabled:
            if self.log:
                self.log.write(f"[dry-run] {cmd_fmt}\n")
            return subprocess.CompletedProcess(cmd, 0, "", "")
        attempts = 0
        while True:
            attempts += 1
            try:
                result = subprocess.run(cmd, capture_output=True, text=True)
            except FileNotFoundError as exc:
                raise WPCLIError(f"wp_cli_not_found:{exc}") from exc
            if result.returncode == 0 or not check:
                if self.log and result.returncode != 0:
                    self.log.write(
                        f"cmd rc={result.returncode} (ignored) → {cmd_fmt}\nstderr: {result.stderr.strip()}\n"
                    )
                return result
            message = result.stderr.strip() or result.stdout.strip() or cmd_fmt
            if attempts <= self.retries:
                if self.log:
                    self.log.write(
                        f"retry ({attempts}/{self.retries}) rc={result.returncode} → {cmd_fmt}\n"
                    )
                    if result.stderr:
                        self.log.write(f"stderr: {result.stderr}\n")
                time.sleep(0.5)
                continue
            raise WPCLIError(message)

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
            write=False,
        )
        output = (result.stdout or "").strip()
        if not output:
            return None
        for part in output.split():
            try:
                value = int(part.strip())
            except (TypeError, ValueError):
                continue
            if value > 0:
                return value
        return None

    def get_post_field(self, product_id: int, field: str) -> str:
        result = self.run(["post", "get", str(product_id), f"--field={field}"], write=False, check=False)
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def get_post_meta(self, product_id: int, key: str) -> str:
        result = self.run(["post", "meta", "get", str(product_id), key], write=False, check=False)
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def update_post_meta(self, product_id: int, key: str, value: str) -> None:
        self.run(["post", "meta", "update", str(product_id), key, value], write=True)

    def post_update(self, product_id: int, **fields: str) -> None:
        if not fields:
            return
        args = ["post", "update", str(product_id)]
        for key, value in fields.items():
            args.append(f"--{key}={value}")
        self.run(args, write=True)

    def post_create(self, **fields: str) -> int:
        args = ["post", "create", "--post_type=product", "--post_status=publish", "--porcelain"]
        for key, value in fields.items():
            args.append(f"--{key}={value}")
        result = self.run(args, write=True)
        output = (result.stdout or "").strip()
        try:
            product_id = int(output)
        except (TypeError, ValueError):
            raise WPCLIError(f"post_create_invalid_output:{output}")
        if product_id <= 0:
            raise WPCLIError(f"post_create_invalid_id:{output}")
        return product_id

    def _list_terms(self, taxonomy: str, slug: str) -> Optional[Dict[str, Any]]:
        cache_key = (taxonomy, slug)
        if cache_key in self._term_by_name_cache:
            return self._term_by_name_cache[cache_key]
        result = self.run(
            [
                "term",
                "list",
                taxonomy,
                f"--slug={slug}",
                "--fields=term_id,slug,name,parent",
                "--format=json",
            ],
            write=False,
            check=False,
        )
        if result.returncode != 0:
            return None
        try:
            data = json.loads(result.stdout or "[]")
        except json.JSONDecodeError:
            data = []
        if isinstance(data, list) and data:
            term = data[0]
            if isinstance(term, Mapping):
                self._term_by_name_cache[cache_key] = dict(term)
                return dict(term)
        return None

    def ensure_term(self, taxonomy: str, name: str, parent: int = 0) -> int:
        slug = slugify(name)
        cache_key = (taxonomy, slug, parent or 0)
        cached = self._term_cache.get(cache_key)
        if cached:
            term_id = parse_int(cached.get("term_id"), 0)
            if term_id > 0:
                return term_id
        existing = self._list_terms(taxonomy, slug)
        if existing:
            term_id = parse_int(existing.get("term_id"), 0)
            if term_id > 0:
                self._term_cache[cache_key] = dict(existing)
                return term_id
        args = ["term", "create", taxonomy, name, f"--slug={slug}", "--porcelain"]
        if parent:
            args.append(f"--parent={parent}")
        result = self.run(args, write=True, check=False)
        if result.returncode == 0:
            output = (result.stdout or "").strip()
            try:
                term_id = int(output)
            except (TypeError, ValueError):
                term_id = 0
            if term_id <= 0:
                raise WPCLIError(f"term_create_invalid_output:{taxonomy}:{name}:{output}")
            payload = {"term_id": term_id, "slug": slug, "name": name, "parent": parent}
            self._term_cache[cache_key] = payload
            self._term_by_name_cache[(taxonomy, slug)] = payload
            return term_id
        message = (result.stderr or "").lower()
        if "already exists" in message or "ya existe" in message or "existe" in message:
            existing = self._list_terms(taxonomy, slug)
            if existing:
                term_id = parse_int(existing.get("term_id"), 0)
                if term_id > 0:
                    self._term_cache[cache_key] = dict(existing)
                    return term_id
        raise WPCLIError(result.stderr.strip() or result.stdout.strip() or "term_create_error")

    def ensure_category(self, raw_category: Any) -> Optional[int]:
        parts = normalize_category_path(raw_category)
        if not parts:
            if isinstance(raw_category, (int, float)):
                return parse_int(raw_category, 0)
            text = str(raw_category or "").strip()
            if text.isdigit():
                return parse_int(text, 0)
            if text:
                return self.ensure_term("product_cat", text, 0)
            return None
        parent = 0
        term_id = None
        for part in parts:
            term_id = self.ensure_term("product_cat", part, parent)
            parent = term_id
        return term_id

    def ensure_brand(self, brand: str) -> Optional[int]:
        if not brand:
            return None
        return self.ensure_term("product_brand", brand, 0)

    def set_terms(self, taxonomy: str, product_id: int, term_id: int) -> None:
        if term_id <= 0:
            return
        self.run(
            [
                "term",
                "set",
                taxonomy,
                str(product_id),
                str(term_id),
                "--by=id",
            ],
            write=True,
        )

    def import_image(self, product_id: int, source: str) -> None:
        if not source:
            return
        self.run(
            [
                "media",
                "import",
                source,
                f"--post_id={product_id}",
                "--featured_image",
                "--porcelain",
            ],
            write=True,
        )

    def has_thumbnail(self, product_id: int) -> bool:
        return bool(self.get_post_meta(product_id, "_thumbnail_id"))

    def has_description(self, product_id: int) -> bool:
        return bool(self.get_post_field(product_id, "post_content"))

    def db_query(self, sql: str, write: bool = False) -> subprocess.CompletedProcess:
        return self.run(["db", "query", sql, "--skip-column-names"], write=write)

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
            write=False,
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


def safe_sql_value(value: Any) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        if isinstance(value, float):
            return str(round(value, 6))
        return str(value)
    text = str(value)
    escaped = text.replace("'", "''")
    return f"'{escaped}'"


class MirrorManager:
    TABLES = {
        "wp_compu_products": {
            "required": {"sku"},
            "columns": {
                "sku",
                "nombre",
                "marca",
                "cat_term_id",
                "cat_slug",
                "peso_kg",
                "image_set",
                "run_id",
                "updated_at",
            },
        },
        "wp_compu_prices": {
            "required": {"sku"},
            "columns": {
                "sku",
                "net",
                "gross_16",
                "gross_8",
                "price_16_final",
                "tipo_de_cambio",
                "margen_tipo",
                "margen_valor",
                "run_id",
            },
        },
        "wp_compu_inventory": {
            "required": {"sku"},
            "columns": {
                "sku",
                "stock_total_mayoristas",
                "Almacen_15",
                "Almacen_15_Tijuana",
                "run_id",
            },
        },
    }

    def __init__(self, client: WooClient, log, run_id: str) -> None:
        self.client = client
        self.log = log
        self.run_id = run_id
        self._detected = False
        self._available_columns: Dict[str, set] = {}

    def detect(self) -> None:
        if self._detected:
            return
        self._detected = True
        try:
            result = self.client.db_query("SHOW TABLES LIKE 'wp_compu_%';", write=False)
        except WPCLIError as exc:
            if self.log:
                self.log.write(f"mirror detect error: {exc}\n")
            return
        tables = set()
        output = (result.stdout or "").strip().splitlines()
        for line in output:
            table = line.strip()
            if table:
                tables.add(table)
        for table, spec in self.TABLES.items():
            if table not in tables:
                continue
            try:
                cols_result = self.client.db_query(f"SHOW COLUMNS FROM {table};", write=False)
            except WPCLIError as exc:
                if self.log:
                    self.log.write(f"mirror columns error {table}: {exc}\n")
                continue
            available = set()
            for line in (cols_result.stdout or "").splitlines():
                parts = line.strip().split("\t")
                if parts:
                    available.add(parts[0])
            if spec["required"].issubset(available):
                self._available_columns[table] = available
            else:
                if self.log:
                    self.log.write(
                        f"mirror table {table} missing required columns {spec['required'] - available}\n"
                    )

    def upsert(self, table: str, payload: Mapping[str, Any]) -> bool:
        self.detect()
        available = self._available_columns.get(table)
        if not available:
            return False
        columns = []
        values = []
        updates = []
        for key, value in payload.items():
            if key not in available:
                continue
            columns.append(key)
            values.append(safe_sql_value(value))
            updates.append(f"{key}=VALUES({key})")
        if not columns:
            return False
        if "updated_at" in available and "updated_at" not in payload:
            columns.append("updated_at")
            values.append("NOW()")
            updates.append("updated_at=NOW()")
        sql = (
            f"INSERT INTO {table} ({', '.join(columns)}) VALUES ({', '.join(values)}) "
            f"ON DUPLICATE KEY UPDATE {', '.join(updates)};"
        )
        self.client.db_query(sql, write=True)
        return True

    def apply(self, sku: str, record: Mapping[str, Any], product_payload: Mapping[str, Any]) -> str:
        updated_any = False
        partial = False
        products_payload = {
            "sku": sku,
            "nombre": product_payload.get("name"),
            "marca": product_payload.get("brand"),
            "cat_term_id": product_payload.get("category_id"),
            "cat_slug": product_payload.get("category_slug"),
            "peso_kg": product_payload.get("weight"),
            "image_set": 1 if product_payload.get("image_set") else 0,
            "run_id": self.run_id,
        }
        prices_payload = {
            "sku": sku,
            "net": record.get("net"),
            "gross_16": record.get("gross_16"),
            "gross_8": record.get("gross_8"),
            "price_16_final": record.get("price_16_final"),
            "tipo_de_cambio": record.get("Tipo_de_Cambio"),
            "margen_tipo": record.get("margin_type"),
            "margen_valor": record.get("margin_value"),
            "run_id": self.run_id,
        }
        inventory_payload = {
            "sku": sku,
            "stock_total_mayoristas": product_payload.get("stock"),
            "Almacen_15": record.get("Almacen_15"),
            "Almacen_15_Tijuana": record.get("Almacen_15_Tijuana"),
            "run_id": self.run_id,
        }
        try:
            if self.upsert("wp_compu_products", products_payload):
                updated_any = True
            else:
                partial = True
        except WPCLIError as exc:
            partial = True
            if self.log:
                self.log.write(f"mirror products error: {exc}\n")
        try:
            if self.upsert("wp_compu_prices", prices_payload):
                updated_any = True
            else:
                partial = True
        except WPCLIError as exc:
            partial = True
            if self.log:
                self.log.write(f"mirror prices error: {exc}\n")
        try:
            if self.upsert("wp_compu_inventory", inventory_payload):
                updated_any = True
            else:
                partial = True
        except WPCLIError as exc:
            partial = True
            if self.log:
                self.log.write(f"mirror inventory error: {exc}\n")
        if updated_any and not partial:
            return "written"
        if updated_any and partial:
            return "partial"
        if partial:
            return "partial"
        return "skipped"


def format_price(value: float) -> str:
    return f"{float(value):.2f}"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Stage 10 - WooCommerce import")
    parser.add_argument("--run-dir", required=True)
    parser.add_argument("--input", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--report", required=True)
    parser.add_argument("--dry-run", type=int, choices=[0, 1], default=1)
    parser.add_argument("--summary", action="append", default=[])
    parser.add_argument("--writer", choices=["sim", "wp"], default=None)
    parser.add_argument("--wp-path", default=None)
    parser.add_argument("--wp-args", default=None)
    parser.add_argument("--run-id", default="")
    return parser.parse_args()


def collect_summary_paths(run_dir: Path, explicit: Iterable[str]) -> List[Path]:
    summary_paths = [Path(item) for item in explicit] if explicit else []
    if not summary_paths:
        summary_paths = [run_dir / "summary.json", run_dir / "final" / "summary.json"]
    return summary_paths


def detect_existing_sim(record: Mapping[str, Any]) -> Tuple[bool, Optional[int]]:
    for key in ("woo_product_id", "product_id", "woo_id", "post_id"):
        pid = parse_int(record.get(key), 0)
        if pid > 0:
            return True, pid
    for key in ("woo_exists", "exists_in_woo", "exists"):
        value = record.get(key)
        if isinstance(value, bool) and value:
            return True, None
        if isinstance(value, str) and value.strip().lower() in {"1", "true", "yes", "y", "si", "sí"}:
            return True, None
    return False, None


def ensure_flags(entry: MutableMapping[str, Any]) -> MutableMapping[str, Any]:
    flags = entry.setdefault("flags", {})
    for key in (
        "category_assigned",
        "brand_assigned",
        "image_set",
        "desc_set",
        "price_set",
        "stock_set",
        "mirror_written",
    ):
        flags.setdefault(key, False)
    return flags


def stage10(args: argparse.Namespace) -> None:
    run_dir = Path(args.run_dir)
    input_path = Path(args.input)
    log_path = Path(args.log)
    report_path = Path(args.report)
    dry_run = bool(args.dry_run)
    summary_paths = collect_summary_paths(run_dir, args.summary)

    writer = (args.writer or os.environ.get("IMPORT_WRITER") or os.environ.get("WRITER") or "sim").strip().lower()
    if writer not in {"sim", "wp"}:
        writer = "sim"
    if writer == "wp" and dry_run and os.environ.get("FORCE_WRITE", "0") in {"1", "true", "yes"}:
        dry_run = False

    wp_path = args.wp_path or os.environ.get("WP_PATH", "wp")
    wp_args = args.wp_args or os.environ.get("WP_PATH_ARGS", "")
    write_enabled = writer == "wp" and not dry_run

    ensure_parent_dir(log_path)
    ensure_parent_dir(report_path)

    with log_path.open("w", encoding="utf-8") as log:
        log.write(f"== Stage 10: import (writer={writer})==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")

        if not input_path.exists():
            raise FileNotFoundError(f"No existe input: {input_path}")

        records = list(read_jsonl(input_path))
        total_records = len(records)
        log.write(f"Registros a procesar: {total_records}\n")

        woo_client = WooClient(wp_path, wp_args, log, write_enabled) if writer == "wp" else None
        mirror_manager = MirrorManager(woo_client, log, args.run_id or run_dir.name) if woo_client else None

        metrics: Dict[str, Any] = {
            "rows_total": total_records,
            "created": 0,
            "updated": 0,
            "skipped": 0,
            "price_zero": 0,
            "stock_zero": 0,
            "wp_errors": 0,
            "mirror_written": 0,
            "mirror_partial": 0,
            "mirror_skipped": 0,
        }

        created_entries: List[Dict[str, Any]] = []
        updated_entries: List[Dict[str, Any]] = []
        skipped_entries: List[Dict[str, Any]] = []

        for record in records:
            sku = str(record.get("sku") or record.get("SKU") or "").strip()
            if not sku:
                log.write("registro sin SKU, skip\n")
                metrics["skipped"] += 1
                skipped_entries.append({"sku": None, "action": "skipped", "reason": "missing_sku", "flags": {}})
                continue

            name = build_name(record)
            brand = str(record.get("Marca", "")).strip()
            category_raw = record.get("ID_Menu_Nvl_3")
            description = extract_description(record)
            image_source = extract_image_source(record)
            weight = parse_float(record.get("Peso_Kg"), -1.0)
            weight = round(weight, 3) if weight >= 0 else None

            stock_info = compute_stock_info(record)
            stock_total = stock_info["stock_total_mayoristas"]
            precio_objetivo = parse_float(record.get("price_16_final"), 0.0)

            entry: Dict[str, Any] = {
                "sku": sku,
                "precio_objetivo": round(precio_objetivo, 2),
                "stock_total_mayoristas": stock_total,
                "before": {},
                "after": {
                    "name": name,
                    "brand": brand,
                    "category": category_raw,
                    "stock": stock_total,
                    "price": round(precio_objetivo, 2),
                    "weight": weight,
                },
                "flags": {},
            }
            flags = ensure_flags(entry)

            existing = False
            product_id: Optional[int] = None
            current_price = 0.0
            current_stock = 0
            current_brand = ""
            current_category = ""
            has_image = False
            has_desc = False

            if writer == "wp" and woo_client:
                try:
                    product_id = woo_client.find_product_id(sku)
                except WPCLIError as exc:
                    metrics["skipped"] += 1
                    metrics["wp_errors"] += 1
                    entry["action"] = "skipped"
                    entry["reason"] = f"wp_error:{exc}"
                    skipped_entries.append(entry)
                    log.write(f"{sku}: error find_product_id → {exc}\n")
                    continue
                existing = product_id is not None
                if existing and product_id:
                    entry["after"]["product_id"] = product_id
                    entry["before"]["product_id"] = product_id
                    current_price = parse_float(woo_client.get_post_meta(product_id, "_regular_price"), 0.0)
                    current_stock = parse_int(woo_client.get_post_meta(product_id, "_stock"), 0)
                    entry["before"]["price"] = round(current_price, 2)
                    entry["before"]["stock"] = current_stock
                    terms_brand = woo_client.get_terms(product_id, "product_brand")
                    if terms_brand:
                        current_brand = str(terms_brand[0].get("name") or "")
                        if current_brand:
                            entry["before"]["brand"] = current_brand
                    terms_cat = woo_client.get_terms(product_id, "product_cat")
                    if terms_cat:
                        term = terms_cat[0]
                        term_id = term.get("term_id")
                        entry["before"]["category"] = term_id or term.get("name")
                        current_category = str(term_id or term.get("name") or "")
                    has_image = woo_client.has_thumbnail(product_id)
                    has_desc = woo_client.has_description(product_id)
                else:
                    has_image = False
                    has_desc = False
            else:
                existing, product_id = detect_existing_sim(record)
                current_price = parse_float(record.get("woo_regular_price"), 0.0)
                current_stock = parse_int(record.get("woo_stock"), 0)
                if existing:
                    entry["before"] = {
                        "product_id": product_id,
                        "price": round(current_price, 2),
                        "stock": current_stock,
                        "brand": record.get("woo_brand"),
                        "category": record.get("woo_category"),
                    }
                has_image = bool(record.get("woo_has_image"))
                has_desc = bool(record.get("woo_has_description"))

            should_update_description = bool(description) and not has_desc
            should_set_image = bool(image_source) and not has_image

            if precio_objetivo <= 0:
                metrics["price_zero"] += 1
                entry["reason"] = "price_zero"
                if existing and writer == "wp" and woo_client and product_id:
                    try:
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", "0")
                        woo_client.update_post_meta(product_id, "_stock_status", "outofstock")
                        flags["stock_set"] = True
                    except WPCLIError as exc:
                        metrics["wp_errors"] += 1
                        entry["reason"] = f"wp_error:{exc}"
                        entry["action"] = "skipped"
                        skipped_entries.append(entry)
                        log.write(f"{sku}: error al forzar stock=0 → {exc}\n")
                        continue
                    entry["action"] = "update"
                    updated_entries.append(entry)
                    metrics["updated"] += 1
                    log.write(f"{sku}: precio objetivo 0 → stock forzado a 0.\n")
                elif existing:
                    entry["action"] = "update"
                    flags["stock_set"] = True
                    updated_entries.append(entry)
                    metrics["updated"] += 1
                    log.write(f"{sku}: precio 0 (simulación) → stock=0.\n")
                else:
                    entry["action"] = "skipped"
                    skipped_entries.append(entry)
                    metrics["skipped"] += 1
                    log.write(f"{sku}: precio 0, no se crea.\n")
                continue

            if stock_total <= 0:
                metrics["stock_zero"] += 1
                entry["reason"] = "stock_zero"
                if existing and writer == "wp" and woo_client and product_id:
                    try:
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", "0")
                        woo_client.update_post_meta(product_id, "_stock_status", "outofstock")
                        flags["stock_set"] = True
                    except WPCLIError as exc:
                        metrics["wp_errors"] += 1
                        entry["reason"] = f"wp_error:{exc}"
                        entry["action"] = "skipped"
                        skipped_entries.append(entry)
                        log.write(f"{sku}: error al poner stock=0 → {exc}\n")
                        continue
                    entry["action"] = "update"
                    updated_entries.append(entry)
                    metrics["updated"] += 1
                    log.write(f"{sku}: sin stock → stock=0 actualizado.\n")
                elif existing:
                    entry["action"] = "update"
                    flags["stock_set"] = True
                    updated_entries.append(entry)
                    metrics["updated"] += 1
                    log.write(f"{sku}: sin stock (sim) → stock=0.\n")
                else:
                    entry["action"] = "skipped"
                    skipped_entries.append(entry)
                    metrics["skipped"] += 1
                    log.write(f"{sku}: sin stock, no se crea.\n")
                continue

            price_set = False
            stock_set = False
            desc_set = False
            image_set = False
            category_assigned = False
            brand_assigned = False
            mirror_status = "skipped"

            category_term_id: Optional[int] = None
            category_slug = ""

            if existing:
                if product_id:
                    entry["after"]["product_id"] = product_id
                entry["action"] = "update"
                if writer == "wp" and woo_client and product_id:
                    try:
                        woo_client.update_post_meta(product_id, "_sku", sku)
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", str(stock_total))
                        woo_client.update_post_meta(
                            product_id,
                            "_stock_status",
                            "instock" if stock_total > 0 else "outofstock",
                        )
                        stock_set = True
                        if weight is not None:
                            woo_client.update_post_meta(product_id, "_weight", str(weight))
                        if should_update_description:
                            woo_client.post_update(product_id, post_content=description)
                            desc_set = True
                        if name and woo_client.get_post_field(product_id, "post_title") != name:
                            woo_client.post_update(product_id, post_title=name)
                        if should_set_image:
                            woo_client.import_image(product_id, image_source)
                            image_set = True
                        # Price guard: only lower or equal
                        if current_price <= 0 or precio_objetivo <= current_price:
                            price_text = format_price(precio_objetivo)
                            woo_client.update_post_meta(product_id, "_price", price_text)
                            woo_client.update_post_meta(product_id, "_regular_price", price_text)
                            price_set = True
                        else:
                            log.write(
                                f"{sku}: precio nuevo ({precio_objetivo}) > actual ({current_price}), no se actualiza precio.\n"
                            )
                        if brand:
                            term_id = woo_client.ensure_brand(brand)
                            if term_id:
                                woo_client.set_terms("product_brand", product_id, term_id)
                                brand_assigned = True
                        if category_raw is not None:
                            category_term_id = woo_client.ensure_category(category_raw)
                            if category_term_id:
                                woo_client.set_terms("product_cat", product_id, category_term_id)
                                category_assigned = True
                                category_slug = slugify(str(category_raw if not isinstance(category_raw, list) else category_raw[-1]))
                    except WPCLIError as exc:
                        metrics["wp_errors"] += 1
                        entry["reason"] = f"wp_error:{exc}"
                        entry["action"] = "skipped"
                        skipped_entries.append(entry)
                        log.write(f"{sku}: error update → {exc}\n")
                        continue
                else:
                    stock_set = True
                    desc_set = should_update_description
                    image_set = should_set_image
                    if precio_objetivo <= current_price or current_price <= 0:
                        price_set = True
                    else:
                        log.write(
                            f"{sku}: (sim) precio nuevo {precio_objetivo} > actual {current_price}, no se actualiza.\n"
                        )
                    category_assigned = category_raw is not None
                    brand_assigned = bool(brand)
                metrics["updated"] += 1
                updated_entries.append(entry)
            else:
                entry["action"] = "create"
                if writer == "wp" and woo_client:
                    try:
                        create_fields = {"post_title": name or sku}
                        if should_update_description:
                            create_fields["post_content"] = description
                        product_id = woo_client.post_create(**create_fields)
                        entry["after"]["product_id"] = product_id
                        woo_client.update_post_meta(product_id, "_sku", sku)
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", str(stock_total))
                        woo_client.update_post_meta(
                            product_id,
                            "_stock_status",
                            "instock" if stock_total > 0 else "outofstock",
                        )
                        stock_set = True
                        if precio_objetivo > 0:
                            price_text = format_price(precio_objetivo)
                            woo_client.update_post_meta(product_id, "_price", price_text)
                            woo_client.update_post_meta(product_id, "_regular_price", price_text)
                            price_set = True
                        if weight is not None:
                            woo_client.update_post_meta(product_id, "_weight", str(weight))
                        if brand:
                            term_id = woo_client.ensure_brand(brand)
                            if term_id:
                                woo_client.set_terms("product_brand", product_id, term_id)
                                brand_assigned = True
                        if category_raw is not None:
                            category_term_id = woo_client.ensure_category(category_raw)
                            if category_term_id:
                                woo_client.set_terms("product_cat", product_id, category_term_id)
                                category_assigned = True
                                category_slug = slugify(
                                    str(category_raw if not isinstance(category_raw, list) else category_raw[-1])
                                )
                        if should_set_image:
                            woo_client.import_image(product_id, image_source)
                            image_set = True
                        if not should_update_description and description and not desc_set:
                            # ensure description if provided but product had none
                            woo_client.post_update(product_id, post_content=description)
                            desc_set = True
                        else:
                            desc_set = should_update_description
                    except WPCLIError as exc:
                        metrics["wp_errors"] += 1
                        entry["reason"] = f"wp_error:{exc}"
                        entry["action"] = "skipped"
                        skipped_entries.append(entry)
                        log.write(f"{sku}: error create → {exc}\n")
                        continue
                else:
                    stock_set = True
                    price_set = True
                    desc_set = should_update_description
                    image_set = should_set_image
                    category_assigned = category_raw is not None
                    brand_assigned = bool(brand)
                metrics["created"] += 1
                created_entries.append(entry)

            flags["price_set"] = price_set
            flags["stock_set"] = stock_set
            flags["desc_set"] = desc_set
            flags["image_set"] = image_set
            flags["category_assigned"] = category_assigned
            flags["brand_assigned"] = brand_assigned

            if category_term_id:
                entry["after"]["category"] = category_term_id

            product_payload = {
                "name": name,
                "brand": brand,
                "category_id": category_term_id,
                "category_slug": category_slug,
                "weight": weight,
                "stock": stock_total,
                "image_set": image_set,
            }

            if writer == "wp" and woo_client and mirror_manager and entry["action"] != "skipped":
                try:
                    status = mirror_manager.apply(sku, record, product_payload)
                except WPCLIError as exc:
                    status = "partial"
                    metrics["wp_errors"] += 1
                    log.write(f"{sku}: mirror error → {exc}\n")
                if status == "written":
                    metrics["mirror_written"] += 1
                    flags["mirror_written"] = True
                elif status == "partial":
                    metrics["mirror_partial"] += 1
                else:
                    metrics["mirror_skipped"] += 1
            else:
                flags["mirror_written"] = False

            log_parts = [
                f"action={entry['action']}",
                f"price_set={price_set}",
                f"stock_set={stock_set}",
                f"desc={desc_set}",
                f"image={image_set}",
                f"brand={brand_assigned}",
                f"cat={category_assigned}",
            ]
            if flags.get("mirror_written"):
                log_parts.append("mirror=written")
            log.write(f"{sku}: " + " ".join(log_parts) + "\n")

        report_payload = {
            "created": created_entries,
            "updated": updated_entries,
            "skipped": skipped_entries,
        }

    with report_path.open("w", encoding="utf-8") as fh:
        json.dump(report_payload, fh, indent=2, ensure_ascii=False, sort_keys=True)

    update_summary(summary_paths, "stage_10", metrics)


if __name__ == "__main__":
    stage10(parse_args())
