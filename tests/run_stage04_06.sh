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

ARTIFACTS=(
  "$RUN_DIR/source.csv"
  "$RUN_DIR/normalized.jsonl"
  "$RUN_DIR/validated.jsonl"
  "$RUN_DIR/resolved.jsonl"
  "$RUN_DIR/final/import-ready.csv"
  "$RUN_DIR/final/skipped.csv"
  "$RUN_DIR/final/summary.json"
)

for path in "${ARTIFACTS[@]}"; do
  if [[ ! -f "$path" ]]; then
    echo "Falta artefacto esperado: $path" >&2
    exit 1
  fi
done

DOCS_STAGE04="docs/runs/${RUN_ID}/step-04"
DOCS_STAGE06="docs/runs/${RUN_ID}/step-06"

for path in "$DOCS_STAGE04/resolved.jsonl" "$DOCS_STAGE04/logs/stage-04.log" "$DOCS_STAGE06/final/import-ready.csv" "$DOCS_STAGE06/final/summary.json" "$DOCS_STAGE06/logs/stage-06.log"; do
  if [[ ! -f "$path" ]]; then
    echo "Falta artefacto documentado: $path" >&2
    exit 1
  fi
done

python - "$RUN_DIR/resolved.jsonl" "$RUN_DIR/validated.jsonl" <<'PY'
import json
import math
import sys
from pathlib import Path

resolved_path = Path(sys.argv[1])
validated_path = Path(sys.argv[2])

def read_first(path: Path, limit: int):
    rows = []
    with path.open(encoding="utf-8", errors="ignore") as fh:
        for line in fh:
            line = line.strip()
            if not line:
                continue
            rows.append(json.loads(line))
            if len(rows) >= limit:
                break
    return rows

resolved_rows = read_first(resolved_path, 5)
if not resolved_rows:
    sys.stderr.write("resolved.jsonl vacío\n")
    sys.exit(1)

validated_rows = read_first(validated_path, 5)
if not validated_rows:
    sys.stderr.write("validated.jsonl vacío\n")
    sys.exit(1)

def ensure_margin(row, label):
    if "margin_pct" not in row:
        sys.stderr.write(f"{label} sin margin_pct\n")
        sys.exit(1)
    margin = row["margin_pct"]
    if not isinstance(margin, (int, float)):
        sys.stderr.write(f"{label} margin_pct debe ser numérico\n")
        sys.exit(1)
    if margin < 0 or margin > 1:
        sys.stderr.write(f"{label} margin_pct fuera de rango\n")
        sys.exit(1)
    if "margin_default" in row and not isinstance(row["margin_default"], bool):
        sys.stderr.write(f"{label} margin_default debe ser booleano\n")
        sys.exit(1)
    return margin

resolved_margins = {}
for row in resolved_rows:
    sku = row.get("SKU") or row.get("sku")
    if not sku:
        continue
    resolved_margins[sku] = ensure_margin(row, "resolved")

if not resolved_margins:
    sys.stderr.write("No se encontraron SKUs con margen en resolved.jsonl\n")
    sys.exit(1)

for row in validated_rows:
    sku = row.get("SKU") or row.get("sku")
    if not sku or sku not in resolved_margins:
        continue
    margin = ensure_margin(row, "validated")
    if not math.isclose(margin, resolved_margins[sku], rel_tol=1e-6, abs_tol=1e-6):
        sys.stderr.write("validated.jsonl no coincide con margin_pct de resolved.jsonl\n")
        sys.exit(1)
    break
else:
    sys.stderr.write("No se pudo confirmar margin_pct en validated.jsonl\n")
    sys.exit(1)
PY

echo "RUN_DIR: $RUN_DIR"

echo "Artefactos principales:"
for path in "${ARTIFACTS[@]}"; do
  lines=$(wc -l < "$path" 2>/dev/null || echo 0)
  echo "  - $path (${lines} líneas)"
done

echo "\nVista previa import-ready.csv (primeras 3 filas):"
head -n 3 "$RUN_DIR/final/import-ready.csv"

echo "\nVista previa skipped.csv (primeras 3 filas):"
head -n 3 "$RUN_DIR/final/skipped.csv"

echo "\nResumen JSON:"
python - "$RUN_DIR/final/summary.json" <<'PY'
import json
import sys
from pathlib import Path

summary_path = Path(sys.argv[1])
with summary_path.open(encoding='utf-8', errors='ignore') as fh:
    data = json.load(fh)
print(json.dumps(data, indent=2, ensure_ascii=False))
PY

echo "\nStage 04 log (últimas 3 entradas):"
tail -n 3 "$RUN_DIR/logs/stage-04.log"

echo "\nStage 06 log (últimas 3 entradas):"
tail -n 3 "$RUN_DIR/logs/stage-06.log"
