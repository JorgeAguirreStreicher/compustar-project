#!/usr/bin/env bash
set -euo pipefail

CSV="data/ProductosHora_subset_1000_5000.csv"
ROWS=""
RUN_DIR="tests/tmp/run-$(date +%s)"
TMP_DIR="tests/tmp"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --csv=*)
      CSV="${1#*=}"
      shift
      ;;
    --rows=*)
      ROWS="${1#*=}"
      shift
      ;;
    --run-dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    --run_dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    *)
      shift
      ;;
  esac
done

if [[ ! -f "$CSV" ]]; then
  echo "No existe el CSV de entrada: $CSV" >&2
  exit 1
fi

mkdir -p "$TMP_DIR"
mkdir -p "$RUN_DIR"

SUBSET_CSV="$CSV"
cleanup_subset=false
if [[ -n "$ROWS" ]]; then
  if ! [[ "$ROWS" =~ ^[0-9]+$ ]]; then
    echo "--rows debe ser numérico" >&2
    exit 1
  fi
  SUBSET_CSV="${TMP_DIR}/subset-${ROWS}-$$.csv"
  cleanup_subset=true
  python - "$CSV" "$ROWS" "$SUBSET_CSV" <<'PY'
import sys

source = sys.argv[1]
rows = int(sys.argv[2])
dest = sys.argv[3]

with open(source, "r", encoding="utf-8", errors="ignore") as src, open(dest, "w", encoding="utf-8", errors="ignore") as dst:
    for idx, line in enumerate(src):
        dst.write(line)
        if idx >= rows:
            break
PY
fi

trap 'if $cleanup_subset && [[ -f "$SUBSET_CSV" ]]; then rm -f "$SUBSET_CSV"; fi' EXIT

php tests/stage_runner.php 01-fetch --file="$SUBSET_CSV" --run-dir="$RUN_DIR" >/dev/null
php tests/stage_runner.php 02-normalize --run-dir="$RUN_DIR" >/dev/null
php tests/stage_runner.php 03-validate --run-dir="$RUN_DIR" >/dev/null

NORMALIZED_JSONL="$RUN_DIR/normalized.jsonl"
VALIDATED_JSONL="$RUN_DIR/validated.jsonl"
HEADER_MAP="$RUN_DIR/header-map.json"

if [[ ! -f "$NORMALIZED_JSONL" ]]; then
  echo "Falta normalized.jsonl en $RUN_DIR" >&2
  exit 1
fi
if [[ ! -f "$VALIDATED_JSONL" ]]; then
  echo "Falta validated.jsonl en $RUN_DIR" >&2
  exit 1
fi
if [[ ! -f "$HEADER_MAP" ]]; then
  echo "Falta header-map.json en $RUN_DIR" >&2
  exit 1
fi

jq -c . "$NORMALIZED_JSONL" >/dev/null
jq -c . "$VALIDATED_JSONL" >/dev/null

python - "$RUN_DIR" <<'PY'
import json
import sys
from collections import Counter
from pathlib import Path

run_dir = Path(sys.argv[1])
normalized_path = run_dir / "normalized.jsonl"
validated_path = run_dir / "validated.jsonl"
header_map_path = run_dir / "header-map.json"

def read_jsonl(path: Path):
    rows = []
    with path.open(encoding="utf-8", errors="ignore") as fh:
        for idx, line in enumerate(fh, 1):
            line = line.strip()
            if not line:
                continue
            try:
                rows.append(json.loads(line))
            except json.JSONDecodeError as exc:
                sys.stderr.write(f"JSON inválido en {path} línea {idx}: {exc}\n")
                sys.exit(1)
    return rows

normalized_rows = read_jsonl(normalized_path)
validated_rows = read_jsonl(validated_path)

if not validated_rows:
    sys.stderr.write("validated.jsonl está vacío\n")
    sys.exit(1)

expected_new_fields = ["Nombre", "Stock_Suma_Sin_Tijuana", "Stock_Suma_Total"]
for idx, row in enumerate(validated_rows[:3], 1):
    for field in expected_new_fields:
        if field not in row:
            sys.stderr.write(f"validated.jsonl carece de {field} en las primeras filas (fila {idx})\n")
            sys.exit(1)
    sin_tj = row.get("Stock_Suma_Sin_Tijuana")
    total = row.get("Stock_Suma_Total")
    if not isinstance(sin_tj, int) or not isinstance(total, int):
        sys.stderr.write("Los campos de stock agregados deben ser enteros\n")
        sys.exit(1)

normalized_keys = set()
for row in normalized_rows:
    normalized_keys.update(row.keys())

validated_keys = set()
for row in validated_rows:
    validated_keys.update(row.keys())

if not normalized_keys.issubset(validated_keys):
    missing = sorted(normalized_keys - validated_keys)
    sys.stderr.write("validated.jsonl perdió claves normalizadas: " + ", ".join(missing) + "\n")
    sys.exit(1)

header_meta = json.loads(header_map_path.read_text(encoding="utf-8"))
columns = header_meta.get("columns", [])
sku_field = next((col.get("normalized") for col in columns if col.get("is_sku_column")), "SKU")
if not sku_field:
    sku_field = "SKU"

required_fields = {sku_field}
available_fields = {col.get("normalized") for col in columns if isinstance(col.get("normalized"), str) and col.get("normalized")}
for field in ("Marca", "Titulo", "Tipo_de_Cambio"):
    if field in available_fields:
        required_fields.add(field)

price_fields = {col.get("normalized") for col in columns if isinstance(col.get("normalized"), str) and (col["normalized"] == "Su_Precio" or col["normalized"].startswith("Precio"))}
stock_fields = {col.get("normalized") for col in columns if isinstance(col.get("normalized"), str) and (col["normalized"].startswith("Stock_") or col["normalized"] in {"Stock", "Existencias"})}

price_stock_fields = {f for f in price_fields.union(stock_fields) if f}

def has_value(value) -> bool:
    if value is None:
        return False
    if isinstance(value, str):
        return value.strip() != ""
    if isinstance(value, (int, float)):
        return True
    return bool(value)

for idx, row in enumerate(validated_rows, 1):
    for field in required_fields:
        if not has_value(row.get(field)):
            sys.stderr.write(f"Fila {idx} carece de valor en {field}\n")
            sys.exit(1)
    if price_stock_fields:
        if not any(has_value(row.get(field)) for field in price_stock_fields):
            sys.stderr.write(f"Fila {idx} carece de valores en campos de precio o stock\n")
            sys.exit(1)

sku_counts = Counter()
for row in normalized_rows:
    value = row.get(sku_field)
    if isinstance(value, str):
        value = value.strip()
    if value:
        sku_counts[value] += 1

duplicates = {sku: count for sku, count in sku_counts.items() if count > 1}
if duplicates:
    print("SKU duplicados detectados en normalized.jsonl:")
    for sku, count in sorted(duplicates.items()):
        print(f"  - {sku}: {count}")
else:
    print("No se detectaron SKUs duplicados en normalized.jsonl.")

docs_dir = Path("docs") / "runs" / run_dir.name / "step-03"
expected_files = ["normalized.jsonl", "validated.jsonl", "header-map.json"]
for name in expected_files:
    if not (docs_dir / name).is_file():
        sys.stderr.write(f"Falta artefacto {docs_dir / name}\n")
        sys.exit(1)

log_file = docs_dir / "logs" / "stage-03.log"
if not log_file.is_file():
    sys.stderr.write(f"Falta log de Stage 03 en {log_file}\n")
    sys.exit(1)
PY

echo "Stage 03 validó correctamente en $RUN_DIR" >&2
