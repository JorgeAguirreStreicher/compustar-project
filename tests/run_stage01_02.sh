#!/usr/bin/env bash
set -euo pipefail

CSV="data/ProductosHora_subset_1000_5000.csv"
RUN_DIR="tests/tmp/run-$(date +%s)"

while [[ $# -gt 0 ]]; do
  case $1 in
    --csv=*)
      CSV="${1#*=}"
      shift
      ;;
    --run_dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    --run-dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    *)
      shift
      ;;
  esac
done

mkdir -p "${RUN_DIR}"

php tests/stage_runner.php 01-fetch --file="${CSV}" --run-dir="${RUN_DIR}" >/dev/null
php tests/stage_runner.php 02-normalize --run-dir="${RUN_DIR}" >/dev/null

SOURCE_CSV="${RUN_DIR}/source.csv"
NORMALIZED_JSONL="${RUN_DIR}/normalized.jsonl"
NORMALIZED_CSV="${RUN_DIR}/normalized.csv"

if [[ ! -f "${SOURCE_CSV}" ]]; then
  echo "Falta ${SOURCE_CSV}" >&2
  exit 1
fi

if [[ $(wc -l <"${SOURCE_CSV}") -le 1 ]]; then
  echo "source.csv no contiene datos" >&2
  exit 1
fi

if ! diff -q <(head -n1 "${CSV}") <(head -n1 "${SOURCE_CSV}") >/dev/null; then
  echo "El encabezado de source.csv no coincide con el CSV de entrada" >&2
  exit 1
fi

if [[ ! -f "${NORMALIZED_JSONL}" ]]; then
  echo "Falta ${NORMALIZED_JSONL}" >&2
  exit 1
fi

if [[ ! -f "${NORMALIZED_CSV}" ]]; then
  echo "Falta ${NORMALIZED_CSV}" >&2
  exit 1
fi

python - "$CSV" "$SOURCE_CSV" "$NORMALIZED_JSONL" "$NORMALIZED_CSV" <<'PY'
import csv
import json
import sys
import unicodedata
from pathlib import Path

csv_path = Path(sys.argv[1])
source_path = Path(sys.argv[2])
jsonl_path = Path(sys.argv[3])
norm_csv_path = Path(sys.argv[4])

if not jsonl_path.read_text(encoding="utf-8", errors="ignore").strip():
    sys.stderr.write("normalized.jsonl está vacío\n")
    sys.exit(1)

def normalize_header(value: str) -> str:
    value = value.strip()
    value = unicodedata.normalize("NFD", value)
    value = "".join(ch for ch in value if unicodedata.category(ch) != "Mn")
    value = unicodedata.normalize("NFC", value)
    value = value.replace("\xa0", " ")
    while "  " in value:
        value = value.replace("  ", " ")
    value = value.replace("\t", " ")
    value = value.replace("\n", " ")
    import re
    value = re.sub(r"\s+", "_", value)
    value = re.sub(r"[^A-Za-z0-9_]", "_", value)
    value = re.sub(r"_+", "_", value)
    return value.strip("_")

with source_path.open(newline="", encoding="utf-8", errors="ignore") as fh:
    reader = csv.reader(fh)
    source_header = next(reader)
    source_rows = sum(1 for _ in reader)

expected_keys = [normalize_header(col) for col in source_header]

json_rows = []
with jsonl_path.open(encoding="utf-8", errors="ignore") as fh:
    for line in fh:
        line = line.strip()
        if not line:
            continue
        json_rows.append(json.loads(line))

if not json_rows:
    sys.stderr.write("normalized.jsonl no contiene filas\n")
    sys.exit(1)

first_keys = list(json_rows[0].keys())
expected_set = set(expected_keys)
for key in expected_set:
    if key and key not in first_keys:
        sys.stderr.write(f"La clave normalizada '{key}' no está presente en normalized.jsonl\n")
        sys.exit(1)

sku_values = [row.get("SKU", "") for row in json_rows]
if any(value == "" for value in sku_values):
    sys.stderr.write("Existen filas con SKU vacío\n")
    sys.exit(1)

modelo_key = "Modelo"
if modelo_key not in first_keys:
    sys.stderr.write("No se encontró la columna Modelo normalizada\n")
    sys.exit(1)

for row in json_rows[:10]:
    if row.get("SKU", "") != row.get(modelo_key, ""):
        sys.stderr.write("El SKU no coincide con Modelo en algunas filas\n")
        sys.exit(1)

with norm_csv_path.open(newline="", encoding="utf-8", errors="ignore") as fh:
    reader = csv.reader(fh)
    norm_header = next(reader)
    norm_rows = list(reader)

if norm_header != first_keys:
    sys.stderr.write("normalized.csv y normalized.jsonl difieren en columnas u orden\n")
    sys.exit(1)

if len(json_rows) != len(norm_rows):
    sys.stderr.write("normalized.jsonl y normalized.csv tienen distinto número de filas\n")
    sys.exit(1)

if len(json_rows) not in (source_rows, source_rows - 1):
    sys.stderr.write("El conteo de filas normalizadas no coincide con source.csv\n")
    sys.exit(1)

PY

echo "Stage 01 y Stage 02 completados correctamente en ${RUN_DIR}" >&2
