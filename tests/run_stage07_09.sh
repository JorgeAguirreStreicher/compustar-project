#!/usr/bin/env bash
set -euo pipefail

CSV="/home/compustar/htdocs/ProductosHora.csv"
FROM_ROW=1
ROWS=500
RUN_DIR="tests/tmp/run-$(date +%s)"
TMP_DIR="tests/tmp"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --csv=*)
      CSV="${1#*=}"
      shift
      ;;
    --from=*)
      FROM_ROW="${1#*=}"
      shift
      ;;
    --rows=*)
      ROWS="${1#*=}"
      shift
      ;;
    --run-dir=*|--run_dir=*)
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

if ! [[ "$FROM_ROW" =~ ^[0-9]+$ ]] || ! [[ "$ROWS" =~ ^[0-9]+$ ]]; then
  echo "--from y --rows deben ser numéricos" >&2
  exit 1
fi

mkdir -p "$TMP_DIR"
mkdir -p "$RUN_DIR"
mkdir -p "$RUN_DIR/logs"

SUBSET_CSV="$CSV"
cleanup_subset=false
if [[ "$FROM_ROW" -gt 1 || "$ROWS" -gt 0 ]]; then
  SUBSET_CSV="${TMP_DIR}/subset-${FROM_ROW}-${ROWS}-$$.csv"
  cleanup_subset=true
  python - "$CSV" "$FROM_ROW" "$ROWS" "$SUBSET_CSV" <<'PY'
import sys
source, start, rows, dest = sys.argv[1:5]
start = int(start)
rows = int(rows)

with open(source, "r", encoding="utf-8", errors="ignore") as src, \
     open(dest, "w", encoding="utf-8", errors="ignore") as dst:
    header = src.readline()
    if header:
        dst.write(header)
    current = 0
    for line in src:
        current += 1
        if current < start:
            continue
        dst.write(line)
        if rows and current >= start + rows - 1:
            break
PY
fi

trap 'if $cleanup_subset && [[ -f "$SUBSET_CSV" ]]; then rm -f "$SUBSET_CSV"; fi' EXIT

RUN_ID="$(basename "$RUN_DIR")"

php tests/stage_runner.php 01-fetch --file="$SUBSET_CSV" --run-dir="$RUN_DIR" --run-id="$RUN_ID" >/dev/null
php tests/stage_runner.php 02-normalize --run-dir="$RUN_DIR" --run-id="$RUN_ID" >/dev/null
php tests/stage_runner.php 03-validate --run-dir="$RUN_DIR" --run-id="$RUN_ID" >/dev/null
php tests/stage_runner.php 04-resolve --run-dir="$RUN_DIR" --run-id="$RUN_ID" >/dev/null
php tests/stage_runner.php 06-package --run-dir="$RUN_DIR" --run-id="$RUN_ID" >/dev/null

if [[ ! -f "$RUN_DIR/resolved.jsonl" ]]; then
  echo "Falta resolved.jsonl en $RUN_DIR" >&2
  exit 1
fi

PYTHON=python3
if ! command -v "$PYTHON" >/dev/null 2>&1; then
  PYTHON=python
fi

$PYTHON python/stage07_pricing.py \
  --run-dir "$RUN_DIR" \
  --input "$RUN_DIR/resolved.jsonl" \
  --output "$RUN_DIR/priced.jsonl" \
  --log "$RUN_DIR/logs/stage-07.log" \
  --margin-config "config/margin_rules.json"

$PYTHON python/stage08_inventory.py \
  --run-dir "$RUN_DIR" \
  --input "$RUN_DIR/priced.jsonl" \
  --output "$RUN_DIR/inventory.jsonl" \
  --log "$RUN_DIR/logs/stage-08.log"

$PYTHON python/stage09_media.py \
  --run-dir "$RUN_DIR" \
  --input "$RUN_DIR/inventory.jsonl" \
  --output "$RUN_DIR/media.jsonl" \
  --log "$RUN_DIR/logs/stage-09.log"

for path in "$RUN_DIR/priced.jsonl" "$RUN_DIR/inventory.jsonl" "$RUN_DIR/media.jsonl" \
            "$RUN_DIR/logs/stage-07.log" "$RUN_DIR/logs/stage-08.log" "$RUN_DIR/logs/stage-09.log"; do
  if [[ ! -f "$path" ]]; then
    echo "Falta artefacto esperado: $path" >&2
    exit 1
  fi
done

SUMMARY_FILES=()
if [[ -f "$RUN_DIR/summary.json" ]]; then
  SUMMARY_FILES+=("$RUN_DIR/summary.json")
fi
if [[ -f "$RUN_DIR/final/summary.json" ]]; then
  SUMMARY_FILES+=("$RUN_DIR/final/summary.json")
fi

if [[ ${#SUMMARY_FILES[@]} -gt 0 ]]; then
  echo "\nResumen actualizado:" 
  for summary in "${SUMMARY_FILES[@]}"; do
    echo "- $summary"
    cat "$summary" | "$PYTHON" - <<'PY'
import json, sys
print(json.dumps(json.load(sys.stdin), indent=2, ensure_ascii=False))
PY
  done
fi

echo "\nConteo de filas:" 
for path in "$RUN_DIR/priced.jsonl" "$RUN_DIR/inventory.jsonl" "$RUN_DIR/media.jsonl"; do
  lines=$(wc -l < "$path" 2>/dev/null || echo 0)
  echo "  - $path: ${lines}"
done

for path in "$RUN_DIR/priced.jsonl" "$RUN_DIR/inventory.jsonl" "$RUN_DIR/media.jsonl"; do
  if [[ ! -s "$path" ]]; then
    continue
  fi
  echo "\nLlaves en $(basename "$path") (primera fila):"
  head -n 1 "$path" | jq 'keys'
  if [[ "$path" == *"priced.jsonl" ]]; then
    echo "Muestra de precios (primeras 3 filas):"
    head -n 3 "$path" | jq -r '[.sku, .price_8_final, .price_16_final] | @tsv'
  elif [[ "$path" == *"inventory.jsonl" ]]; then
    echo "Muestra de inventario (primeras 3 filas):"
    head -n 3 "$path" | jq -r '[.sku, .stock_for_import] | @tsv'
  fi
done

echo "\nLogs recientes:"
tail -n 3 "$RUN_DIR/logs/stage-07.log"
tail -n 3 "$RUN_DIR/logs/stage-08.log"
tail -n 3 "$RUN_DIR/logs/stage-09.log"

echo "\nRUN_DIR: $RUN_DIR"
