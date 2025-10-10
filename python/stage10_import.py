#!/usr/bin/env python3
import argparse
import json
import os
import shlex
import subprocess
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


class WPCLIError(RuntimeError):
    """Raised when a WP-CLI command fails."""


class WooClient:
    def __init__(
        self,
        wp_path: str,
        wp_args: str,
        log,
        write_enabled: bool,
    ) -> None:
        self.wp_path = wp_path or "wp"
        self.wp_args = shlex.split(wp_args) if wp_args else []
        self.log = log
        self.write_enabled = write_enabled

    def _build_cmd(self, args: Iterable[str]) -> List[str]:
        return [self.wp_path, *self.wp_args, *list(args)]

    def _format_cmd(self, cmd: Iterable[str]) -> str:
        return " ".join(shlex.quote(part) for part in cmd)

    def run(self, args: Iterable[str], *, write: bool = False, check: bool = True) -> subprocess.CompletedProcess:
        cmd = self._build_cmd(args)
        if write and not self.write_enabled:
            if self.log is not None:
                self.log.write(f"[dry-run] {self._format_cmd(cmd)}\n")
            return subprocess.CompletedProcess(cmd, 0, "", "")
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
        except FileNotFoundError as exc:
            raise WPCLIError(f"wp_cli_not_found:{exc}") from exc
        if check and result.returncode != 0:
            message = result.stderr.strip() or result.stdout.strip() or self._format_cmd(cmd)
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
            write=False,
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
        result = self.run(
            ["post", "get", str(product_id), f"--field={field}"],
            write=False,
            check=False,
        )
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def get_post_meta(self, product_id: int, key: str) -> str:
        result = self.run(
            ["post", "meta", "get", str(product_id), key],
            write=False,
            check=False,
        )
        if result.returncode != 0:
            return ""
        return (result.stdout or "").strip()

    def update_post_meta(self, product_id: int, key: str, value: str) -> None:
        self.run(
            ["post", "meta", "update", str(product_id), key, value],
            write=True,
        )

    def post_update(self, product_id: int, **fields: str) -> None:
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
            raise WPCLIError(f"post create → respuesta inesperada: {output}")
        if product_id <= 0:
            raise WPCLIError(f"post create → ID inválido: {output}")
        return product_id

    def product_has_image(self, product_id: int) -> bool:
        thumbnail_id = self.get_post_meta(product_id, "_thumbnail_id")
        return bool(thumbnail_id)

    def product_has_description(self, product_id: int) -> bool:
        content = self.get_post_field(product_id, "post_content")
        return bool(content)

    def ensure_brand(self, brand: str) -> None:
        if not brand:
            return
        slug = slugify(brand)
        result = self.run(
            [
                "term",
                "create",
                "product_brand",
                brand,
                f"--slug={slug}",
            ],
            write=True,
            check=False,
        )
        if result.returncode not in (0,):
            message = (result.stderr or "").lower()
            if "term already exists" not in message:
                raise WPCLIError(result.stderr.strip() or result.stdout.strip() or "term create error")

    def set_brand(self, product_id: int, brand: str) -> None:
        if not brand:
            return
        self.ensure_brand(brand)
        self.run(
            [
                "term",
                "set",
                "product_brand",
                str(product_id),
                brand,
                "--by=name",
            ],
            write=True,
        )

    def set_category(self, product_id: int, category: str) -> None:
        if not category:
            return
        args = ["term", "set", "product_cat", str(product_id), str(category)]
        if str(category).isdigit():
            args.append("--by=id")
        self.run(args, write=True)

    def import_image(self, product_id: int, source: str) -> None:
        if not source:
            return
        self.run(
            [
                "media",
                "import",
                source,
                "--featured_image",
                f"--post_id={product_id}",
                "--porcelain",
            ],
            write=True,
        )

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
        if isinstance(data, list):
            return [item for item in data if isinstance(item, Mapping)]
        return []


def slugify(text: str) -> str:
    normalized = str(text).strip().lower()
    cleaned = []
    for char in normalized:
        if char.isalnum():
            cleaned.append(char)
        elif char in {" ", "-", "_", "."}:
            cleaned.append("-" if char == " " else char)
    slug = "".join(cleaned)
    while "--" in slug:
        slug = slug.replace("--", "-")
    slug = slug.strip("-_.")
    return slug or "marca"


def extract_description(record: Mapping[str, Any]) -> str:
    keys = (
        "descripcion_html",
        "description_html",
        "descripcion",
        "Descripcion",
        "description",
    )
    for key in keys:
        value = record.get(key)
        if isinstance(value, str):
            text = value.strip()
            if text:
                return text
    return ""


def extract_image_source(record: Mapping[str, Any]) -> str:
    keys = (
        "image_url",
        "Imagen_Principal",
        "image",
        "imagen",
        "image_path",
        "image_local_path",
    )
    for key in keys:
        value = record.get(key)
        if isinstance(value, str):
            text = value.strip()
            if text:
                return text
    return ""


def format_wp_error(exc: Exception) -> str:
    message = str(exc).strip().replace("\r", " ").replace("\n", " ")
    message = " ".join(message.split())
    return f"wp_error:{message}" if message else "wp_error:unknown"


def format_price(value: float) -> str:
    return f"{float(value):.2f}"


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

    env_writer = (args.writer or os.environ.get("IMPORT_WRITER") or os.environ.get("WRITER") or "sim").strip().lower()
    writer = env_writer if env_writer in {"sim", "wp"} else "sim"
    if writer == "wp" and dry_run and to_bool(os.environ.get("FORCE_WRITE")):
        dry_run = False
    wp_path = args.wp_path or os.environ.get("WP_PATH", "wp")
    wp_args = args.wp_args or os.environ.get("WP_PATH_ARGS", "")
    write_enabled = writer == "wp" and not dry_run
    mode = "wp" if write_enabled else "simulation"

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
        "wp_errors": 0,
        "mode": mode,
        "writer": writer,
    }

    if not input_path.exists():
        raise FileNotFoundError(f"No existe input: {input_path}")

    records = list(read_jsonl(input_path))
    metrics["rows_total"] = len(records)

    with log_path.open("w", encoding="utf-8") as log:
        log.write(f"== Stage 10: import (writer={writer})==\n")
        log.write(f"Dry-run={'yes' if dry_run else 'no'}\n")

        woo_client = (
            WooClient(wp_path, wp_args, log, write_enabled)
            if writer == "wp"
            else None
        )

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
            if not isinstance(precio_objetivo, (int, float)):
                precio_objetivo = 0.0

            description_text = extract_description(record)
            image_source = extract_image_source(record)
            weight = target_weight(record)

            after_payload: Dict[str, Any] = {
                "name": name,
                "brand": brand,
                "category": category,
                "stock": stock_total,
                "price": round(precio_objetivo, 2) if isinstance(precio_objetivo, (int, float)) else 0.0,
                "update_price": False,
                "set_description": False,
                "set_image": False,
                "weight": weight,
                "stock_breakdown": {
                    "syscom": stock_info["stock_syscom"],
                    "mayoristas": stock_info["stock_mayoristas"],
                },
            }

            if weight is None:
                after_payload.pop("weight")

            entry: Dict[str, Any] = {
                "sku": sku,
                "stock_total_mayoristas": stock_total,
                "precio_objetivo": round(precio_objetivo, 2) if isinstance(precio_objetivo, (int, float)) else 0.0,
                "before": None,
                "after": after_payload,
            }

            before: Dict[str, Any] = {}
            existing = False
            product_id: Optional[int] = None
            current_price = 0.0
            current_stock = 0

            if writer == "wp" and woo_client is not None:
                try:
                    product_id = woo_client.find_product_id(sku)
                except WPCLIError as exc:
                    entry["action"] = "skip"
                    entry["reason"] = format_wp_error(exc)
                    skipped.append(entry)
                    metrics["skipped"] += 1
                    metrics["wp_errors"] += 1
                    log.write(f"{sku}: error consultando Woo → {exc}\n")
                    continue
                existing = product_id is not None
                if existing and product_id:
                    before["product_id"] = product_id
                    existing_name = woo_client.get_post_field(product_id, "post_title")
                    if existing_name:
                        before["name"] = existing_name
                    current_price = parse_float(woo_client.get_post_meta(product_id, "_regular_price"), 0.0)
                    if current_price > 0:
                        before["price"] = current_price
                    current_stock = parse_int(woo_client.get_post_meta(product_id, "_stock"), 0)
                    before["stock"] = current_stock
                    brand_terms = woo_client.get_terms(product_id, "product_brand")
                    if brand_terms:
                        before_brand = brand_terms[0].get("name")
                        if before_brand:
                            before["brand"] = before_brand
                    cat_terms = woo_client.get_terms(product_id, "product_cat")
                    if cat_terms:
                        before_cat = cat_terms[0].get("term_id") or cat_terms[0].get("name")
                        if before_cat:
                            before["category"] = before_cat
                    has_desc = woo_client.product_has_description(product_id)
                    has_img = woo_client.product_has_image(product_id)
                else:
                    has_desc = False
                    has_img = False
            else:
                existing, product_id = detect_existing(record)
                current_price = parse_float(record.get("woo_regular_price"), 0.0)
                current_stock = parse_int(record.get("woo_stock"), 0)
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
                has_desc = has_description(record)
                has_img = has_image(record)

            if before:
                entry["before"] = before

            if writer == "wp":
                should_update_description = bool(description_text) and not has_desc
                should_update_image = bool(image_source) and not has_img
            else:
                should_update_description = not has_desc
                should_update_image = not has_img

            after_payload["set_description"] = should_update_description
            after_payload["set_image"] = should_update_image

            if precio_objetivo <= 0:
                metrics["price_zero"] += 1
                entry["reason"] = "price_zero"
                if existing and product_id:
                    entry["action"] = "update_stock_zero_due_to_price_zero"
                    after_payload["stock"] = 0
                    if writer == "wp" and woo_client is not None:
                        try:
                            woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                            woo_client.update_post_meta(product_id, "_stock", "0")
                            woo_client.update_post_meta(product_id, "_stock_status", "outofstock")
                        except WPCLIError as exc:
                            entry["action"] = "error"
                            entry["reason"] = format_wp_error(exc)
                            skipped.append(entry)
                            metrics["skipped"] += 1
                            metrics["wp_errors"] += 1
                            log.write(f"{sku}: error WP price_zero → {exc}\n")
                            continue
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

            if existing and product_id:
                entry["action"] = "update"
                after_payload["update_price"] = bool(
                    precio_objetivo > 0 and (
                        current_price <= 0 or round(precio_objetivo, 2) != round(current_price, 2)
                    )
                )
                if writer == "wp" and woo_client is not None:
                    try:
                        woo_client.update_post_meta(product_id, "_sku", sku)
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", str(stock_total))
                        woo_client.update_post_meta(
                            product_id,
                            "_stock_status",
                            "instock" if stock_total > 0 else "outofstock",
                        )
                        if precio_objetivo > 0:
                            price_text = format_price(precio_objetivo)
                            woo_client.update_post_meta(product_id, "_price", price_text)
                            woo_client.update_post_meta(product_id, "_regular_price", price_text)
                        if weight is not None:
                            woo_client.update_post_meta(product_id, "_weight", str(weight))
                        update_fields: Dict[str, str] = {}
                        if name and before.get("name") != name:
                            update_fields["post_title"] = name
                        if should_update_description and description_text:
                            update_fields["post_content"] = description_text
                        if update_fields:
                            woo_client.post_update(product_id, **update_fields)
                        if brand and (not before.get("brand") or before.get("brand") != brand):
                            woo_client.set_brand(product_id, brand)
                        if category:
                            woo_client.set_category(product_id, category)
                        if should_update_image and image_source:
                            woo_client.import_image(product_id, image_source)
                    except WPCLIError as exc:
                        entry["action"] = "error"
                        entry["reason"] = format_wp_error(exc)
                        skipped.append(entry)
                        metrics["skipped"] += 1
                        metrics["wp_errors"] += 1
                        log.write(f"{sku}: error WP update → {exc}\n")
                        continue
                updated.append(entry)
                metrics["updated"] += 1
                log.write(
                    f"{sku}: update → stock={stock_total} precio={precio_objetivo}"
                    f" update_price={after_payload['update_price']}.\n"
                )
            else:
                entry["action"] = "create"
                if writer == "wp" and woo_client is not None:
                    try:
                        create_fields: Dict[str, str] = {"post_title": name or sku}
                        if should_update_description and description_text:
                            create_fields["post_content"] = description_text
                        product_id = woo_client.post_create(**create_fields)
                        woo_client.update_post_meta(product_id, "_sku", sku)
                        woo_client.update_post_meta(product_id, "_manage_stock", "yes")
                        woo_client.update_post_meta(product_id, "_stock", str(stock_total))
                        woo_client.update_post_meta(
                            product_id,
                            "_stock_status",
                            "instock" if stock_total > 0 else "outofstock",
                        )
                        if precio_objetivo > 0:
                            price_text = format_price(precio_objetivo)
                            woo_client.update_post_meta(product_id, "_price", price_text)
                            woo_client.update_post_meta(product_id, "_regular_price", price_text)
                        if weight is not None:
                            woo_client.update_post_meta(product_id, "_weight", str(weight))
                        if brand:
                            woo_client.set_brand(product_id, brand)
                        if category:
                            woo_client.set_category(product_id, category)
                        if should_update_image and image_source:
                            woo_client.import_image(product_id, image_source)
                    except WPCLIError as exc:
                        entry["action"] = "error"
                        entry["reason"] = format_wp_error(exc)
                        skipped.append(entry)
                        metrics["skipped"] += 1
                        metrics["wp_errors"] += 1
                        log.write(f"{sku}: error WP create → {exc}\n")
                        continue
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
    parser.add_argument("--writer", choices=["sim", "wp"], default=None)
    parser.add_argument("--wp-path", default=None)
    parser.add_argument("--wp-args", default=None)
    return parser.parse_args()


if __name__ == "__main__":
    stage10(parse_args())
