#!/usr/bin/env bash
set -euo pipefail

CSV="tests/data/stage10_media_sample.jsonl"
RUN_DIR="tests/tmp/run-stage10-11-$(date +%s)"
DRY_RUN=1
WRITER="sim"
WP_PATH="${WP_PATH:-wp}"
WP_PATH_ARGS="${WP_PATH_ARGS:---path=/home/compustar/htdocs --no-color}"
WP_ROOT=""
PLUGIN_DIR=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --run-dir=*|--run_dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    --input=*)
      CSV="${1#*=}"
      shift
      ;;
    --dry-run=*)
      DRY_RUN="${1#*=}"
      shift
      ;;
    --writer=*)
      WRITER="${1#*=}"
      shift
      ;;
    --wp-path=*)
      WP_PATH="${1#*=}"
      shift
      ;;
    --wp-args=*)
      WP_PATH_ARGS="${1#*=}"
      shift
      ;;
    --wp-root=*)
      WP_ROOT="${1#*=}"
      WP_PATH_ARGS="--path=${WP_ROOT} --no-color"
      shift
      ;;
    --plugin-dir=*)
      PLUGIN_DIR="${1#*=}"
      shift
      ;;
    *)
      shift
      ;;
  esac
done

if [[ ! -f "$CSV" ]]; then
  echo "No existe el archivo de entrada: $CSV" >&2
  exit 1
fi

mkdir -p "$RUN_DIR"
mkdir -p "$RUN_DIR/logs"
mkdir -p "$RUN_DIR/final"

cp "$CSV" "$RUN_DIR/media.jsonl"

PYTHON=python3
if ! command -v "$PYTHON" >/dev/null 2>&1; then
  PYTHON=python
fi

export WP_PATH
export WP_PATH_ARGS

if [[ "$WRITER" == "wp" && "$DRY_RUN" == "0" ]]; then
  echo "== IMPORT MODE: writer=wp (real) =="
fi

$PYTHON python/stage10_import.py \
  --run-dir "$RUN_DIR" \
  --input "$RUN_DIR/media.jsonl" \
  --log "$RUN_DIR/logs/stage-10.log" \
  --report "$RUN_DIR/final/import-report.json" \
  --dry-run "$DRY_RUN" \
  --writer "$WRITER" \
  --wp-path "$WP_PATH" \
  --wp-args "$WP_PATH_ARGS"

$PYTHON python/stage11_postcheck.py \
  --run-dir "$RUN_DIR" \
  --import-report "$RUN_DIR/final/import-report.json" \
  --postcheck "$RUN_DIR/final/postcheck.json" \
  --log "$RUN_DIR/logs/stage-11.log" \
  --dry-run "$DRY_RUN" \
  --run-id "$(basename "$RUN_DIR")" \
  --writer "$WRITER" \
  --wp-path "$WP_PATH" \
  --wp-args "$WP_PATH_ARGS"

for path in "$RUN_DIR/final/import-report.json" "$RUN_DIR/final/postcheck.json" \
            "$RUN_DIR/logs/stage-10.log" "$RUN_DIR/logs/stage-11.log"; do
  if [[ ! -f "$path" ]]; then
    echo "Falta artefacto esperado: $path" >&2
    exit 1
  fi
done

echo "\nResumen import-report.json (conteos):"
python - "$RUN_DIR/final/import-report.json" <<'PY'
import json, sys
path = sys.argv[1]
with open(path, "r", encoding="utf-8") as fh:
    data = json.load(fh)
print({k: len(v) for k, v in data.items() if isinstance(v, list)})

created = data.get("created", [])
updated = data.get("updated", [])
skipped = data.get("skipped", [])

assert len(created) == 1, created
assert len(updated) == 2, updated
assert len(skipped) == 2, skipped

sku_new = created[0]
assert sku_new["sku"] == "SKU-NEW-1", sku_new
assert sku_new["flags"]["category_assigned"] is True
assert sku_new["flags"]["brand_assigned"] is True

sku_update = next(item for item in updated if item["sku"] == "SKU-EXIST-LOW")
assert sku_update["flags"]["price_set"] is True
assert sku_update["flags"]["stock_set"] is True

sku_price_zero = next(item for item in updated if item["sku"] == "SKU-EXIST-PZ")
assert sku_price_zero["reason"] == "price_zero"
assert sku_price_zero["flags"]["stock_set"] is True

sku_skip_price_zero = next(item for item in skipped if item["sku"] == "SKU-NEW-PZ")
assert sku_skip_price_zero["reason"] == "price_zero"

sku_skip_stock = next(item for item in skipped if item["sku"] == "SKU-NEW-NOSTOCK")
assert sku_skip_stock["reason"] == "stock_zero"
PY

echo "\nimport-report.json → wc -l y head:"
wc -l "$RUN_DIR/final/import-report.json"
head "$RUN_DIR/final/import-report.json"

echo "\npostcheck.json → wc -l y head:"
wc -l "$RUN_DIR/final/postcheck.json"
head "$RUN_DIR/final/postcheck.json"

echo "\nLogs Stage 10 y 11 (tail -n 5):"
tail -n 5 "$RUN_DIR/logs/stage-10.log"
tail -n 5 "$RUN_DIR/logs/stage-11.log"

echo "\nRUN_DIR: $RUN_DIR"
