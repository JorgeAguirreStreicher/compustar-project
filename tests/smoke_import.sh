#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/scripts/.env"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1091
  set -a
  source "$ENV_FILE"
  set +a
fi

RUNNER="$PROJECT_ROOT/scripts/run_compustar_import.sh"
if [[ ! -x "$RUNNER" ]]; then
  echo "Runner no ejecutable: $RUNNER" >&2
  exit 1
fi

TMP_OUTPUT="$(mktemp)"
trap 'rm -f "$TMP_OUTPUT"' EXIT

if ! "$RUNNER" --rows 1000-1050 "$@" | tee "$TMP_OUTPUT"; then
  echo "Smoke test falló durante la ejecución del orquestador" >&2
  exit 1
fi

RUN_DIR="$(grep -oE 'Run directory: .*' "$TMP_OUTPUT" | tail -n1 | sed 's/Run directory: //')"
if [[ -z "$RUN_DIR" || ! -d "$RUN_DIR" ]]; then
  WP_ROOT="${WP:-/home/compustar/htdocs}"
  BASE="${WP_ROOT}/wp-content/uploads/compu-import"
  RUN_DIR="$(ls -1dt "$BASE"/run-* 2>/dev/null | head -n1 || true)"
fi

if [[ -z "$RUN_DIR" || ! -d "$RUN_DIR" ]]; then
  echo "No se pudo determinar el RUN_DIR generado" >&2
  exit 1
fi

echo "== Smoke test summary ($RUN_DIR) =="
for file in source.csv normalized.jsonl validated.jsonl resolved.jsonl media.jsonl final/import-report.json final/postcheck.json; do
  path="$RUN_DIR/$file"
  if [[ -f "$path" ]]; then
    printf '%-28s %s\n' "$file" "$(wc -l < "$path" 2>/dev/null || echo 'N/A') líneas"
  else
    printf '%-28s %s\n' "$file" "(faltante)"
  fi
done

REPORT="$RUN_DIR/final/import-report.json"
if command -v jq >/dev/null 2>&1 && [[ -f "$REPORT" ]]; then
  echo "-- Extracto import-report.json --"
  jq -r 'if has("summary") then ("summary: " + (.summary | tojson)) else if has("flags") then ("flags: " + (.flags | tojson)) else (.[0:3] | tojson) end' "$REPORT"
elif [[ -f "$REPORT" ]]; then
  echo "-- import-report.json (primeras líneas) --"
  head -n 20 "$REPORT"
fi
