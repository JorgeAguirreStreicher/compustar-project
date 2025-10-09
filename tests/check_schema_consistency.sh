#!/usr/bin/env bash
set -euo pipefail

RUN_DIR=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --run-dir=*|--run_dir=*)
      RUN_DIR="${1#*=}"
      shift
      ;;
    *)
      shift
      ;;
  esac
done

if [[ -z "$RUN_DIR" ]]; then
  echo "Uso: $0 --run_dir=<ruta>" >&2
  exit 1
fi

VALIDATED="$RUN_DIR/validated.jsonl"
RESOLVED="$RUN_DIR/resolved.jsonl"

if [[ ! -f "$VALIDATED" ]]; then
  echo "No existe validated.jsonl en $RUN_DIR" >&2
  exit 1
fi
if [[ ! -f "$RESOLVED" ]]; then
  echo "No existe resolved.jsonl en $RUN_DIR" >&2
  exit 1
fi

python - "$VALIDATED" "$RESOLVED" <<'PY'
import json
import sys
from pathlib import Path

validated_path = Path(sys.argv[1])
resolved_path = Path(sys.argv[2])

def read_jsonl(path: Path):
    keys = set()
    rows = 0
    with path.open(encoding='utf-8', errors='ignore') as fh:
        for idx, line in enumerate(fh, 1):
            line = line.strip()
            if not line:
                continue
            try:
                data = json.loads(line)
            except json.JSONDecodeError as exc:
                print(f"JSON inválido en {path} línea {idx}: {exc}", file=sys.stderr)
                sys.exit(1)
            if not isinstance(data, dict):
                print(f"Fila {idx} en {path} no es un objeto JSON", file=sys.stderr)
                sys.exit(1)
            keys.update(data.keys())
            rows += 1
    return keys, rows

validated_keys, validated_rows = read_jsonl(validated_path)
resolved_keys, resolved_rows = read_jsonl(resolved_path)

if validated_rows != resolved_rows:
    print(f"Las filas difieren entre validated ({validated_rows}) y resolved ({resolved_rows})", file=sys.stderr)
    sys.exit(1)

missing = sorted(validated_keys - resolved_keys)
if missing:
    print("resolved.jsonl perdió claves:", ", ".join(missing), file=sys.stderr)
    sys.exit(1)

added = sorted(resolved_keys - validated_keys)
print("Filas validadas/resueltas:", validated_rows)
print("Claves nuevas agregadas en resolved.jsonl:")
for key in added:
    print(f"  - {key}")

if not added:
    print("  (ninguna)")
PY
