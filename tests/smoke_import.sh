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

MODE="random50"
RUNNER_ARGS=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --mode)
      [[ $# -ge 2 ]] || { echo "Falta valor para --mode" >&2; exit 2; }
      MODE="$2"
      shift 2
      ;;
    *)
      RUNNER_ARGS+=("$1")
      shift
      ;;
  esac
done

TMP_OUTPUT="$(mktemp)"
TMP_FILES=("$TMP_OUTPUT")

WP_ROOT="${WP:-/home/compustar/htdocs}"
SOURCE_MASTER_PATH="${SOURCE_MASTER:-$WP_ROOT/ProductosHora.csv}"

case "$MODE" in
  rows)
    RUN_COMMAND=("$RUNNER" --rows 1000-1050 "${RUNNER_ARGS[@]}")
    ;;
  random50)
    if [[ ! -f "$SOURCE_MASTER_PATH" ]]; then
      echo "No se encontró ProductosHora.csv en $SOURCE_MASTER_PATH" >&2
      exit 1
    fi
    RANDOM_SOURCE="$(mktemp)"
    TMP_FILES+=("$RANDOM_SOURCE")
    {
      head -n 1 "$SOURCE_MASTER_PATH"
      tail -n +2 "$SOURCE_MASTER_PATH" | shuf -n 50
    } > "$RANDOM_SOURCE"
    echo "== Smoke mode: random50 (${SOURCE_MASTER_PATH} → $RANDOM_SOURCE) =="
    RUN_COMMAND=("$RUNNER" --source "$RANDOM_SOURCE" "${RUNNER_ARGS[@]}")
    ;;
  *)
    echo "Modo desconocido: $MODE" >&2
    exit 2
    ;;
esac

cleanup() {
  local file
  for file in "${TMP_FILES[@]}"; do
    [[ -n "$file" ]] && rm -f "$file"
  done
}
trap cleanup EXIT

if ! "${RUN_COMMAND[@]}" | tee "$TMP_OUTPUT"; then
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
missing=0
for file in source.csv normalized.jsonl validated.jsonl resolved.jsonl media.jsonl final/import-report.json final/postcheck.json; do
  path="$RUN_DIR/$file"
  if [[ -f "$path" ]]; then
    printf '%-28s %s\n' "$file" "$(wc -l < "$path" 2>/dev/null || echo 'N/A') líneas"
  else
    printf '%-28s %s\n' "$file" "(faltante)"
    missing=$((missing + 1))
  fi
done
if (( missing > 0 )); then
  echo "Faltan artefactos obligatorios en $RUN_DIR" >&2
  exit 1
fi

REPORT="$RUN_DIR/final/import-report.json"
if command -v jq >/dev/null 2>&1 && [[ -f "$REPORT" ]]; then
  echo "-- Extracto import-report.json --"
  jq -r 'if has("summary") then ("summary: " + (.summary | tojson)) else if has("flags") then ("flags: " + (.flags | tojson)) else (.[0:3] | tojson) end' "$REPORT"
elif [[ -f "$REPORT" ]]; then
  echo "-- import-report.json (primeras líneas) --"
  head -n 20 "$REPORT"
fi

MEDIA="$RUN_DIR/media.jsonl"
if [[ -f "$MEDIA" ]]; then
  echo "-- media.jsonl (primeras 3 líneas) --"
  head -n 3 "$MEDIA"
  media_lines=$(wc -l < "$MEDIA")
  if (( media_lines <= 0 )); then
    echo "media.jsonl está vacío" >&2
    exit 1
  fi

  input_jsonl=""
  if [[ -f "$RUN_DIR/resolved.jsonl" ]]; then
    input_jsonl="$RUN_DIR/resolved.jsonl"
  elif [[ -f "$RUN_DIR/validated.jsonl" ]]; then
    input_jsonl="$RUN_DIR/validated.jsonl"
  fi

  if [[ -n "$input_jsonl" ]]; then
    input_lines=$(wc -l < "$input_jsonl")
    echo "media.jsonl líneas: $media_lines (input: $input_lines)"
  fi

  if command -v jq >/dev/null 2>&1; then
    if ! head -n 3 "$MEDIA" | jq -e 'if has("sku") and has("image_status") and has("source") then empty else error("missing fields") end' >/dev/null; then
      echo "media.jsonl carece de llaves obligatorias" >&2
      exit 1
    fi
  else
    echo "jq no disponible; omitiendo validación estructural de media.jsonl" >&2
  fi
else
  echo "-- media.jsonl no encontrado --"
  exit 1
fi

for stage_log in stage10 stage11; do
  log_path="$RUN_DIR/logs/${stage_log}.log"
  if [[ -f "$log_path" ]]; then
    echo "-- tail ${stage_log}.log --"
    tail -n 5 "$log_path"
  else
    echo "-- ${stage_log}.log no encontrado --"
  fi
done
