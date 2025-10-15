#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

LOCK="/var/lock/compu-import.lock"
exec 9>"$LOCK" || { echo "[FATAL] No se pudo abrir lock $LOCK"; exit 1; }
if ! flock -n 9; then
  echo "[LOCK] Otro proceso en ejecución." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Defaults
WP_PATH="/home/compustar/htdocs"
REPO="/home/compustar/compustar-project"
WP_CLI="/usr/local/bin/wp"
PHP_BIN="php"
PYTHON_BIN="python3"
SRC_DEFAULT="/home/compustar/htdocs/ProductosHora.csv"
PLUG=""
DRY_RUN=0
REQUIRE_TERM=1
ROWS=""
SOURCE="$SRC_DEFAULT"
RUNS_TO_KEEP=10
PHP_OPTS=(-d display_errors=1 -d error_reporting=E_ALL -d memory_limit=1024M)
RUN_BASE=""
RUN_DIR=""
RUN_ID=""
MASTER_LOG=""
WP_TABLE_PREFIX="wp_"

ENV_FILE="$SCRIPT_DIR/.env"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1091
  set -a
  source "$ENV_FILE"
  set +a
fi

if [[ -n "${WP:-}" ]]; then
  WP_PATH="$WP"
fi

usage() {
  cat <<'USAGE'
Uso: run_compustar_import.sh [opciones]
  --source <ruta_csv>        Ruta al CSV de origen (default /home/compustar/htdocs/ProductosHora.csv)
  --rows <ini-fin>           Rango opcional de filas (ej: 1000-1050) para subset
  --dry-run {0|1}            Ejecuta Stage 10 en modo dry-run (default 0)
  --wp-path <ruta>           Ruta a la instalación de WordPress (default /home/compustar/htdocs)
  --require-term {0|1}       Requerir categoría mapeada en Stage 04 (default 1)
  -h, --help                 Mostrar esta ayuda
USAGE
}

parse_args() {
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --source)
        [[ $# -ge 2 ]] || { echo "Falta valor para --source" >&2; exit 2; }
        SOURCE="$2"
        shift 2
        ;;
      --rows)
        [[ $# -ge 2 ]] || { echo "Falta valor para --rows" >&2; exit 2; }
        ROWS="$2"
        shift 2
        ;;
      --dry-run)
        [[ $# -ge 2 ]] || { echo "Falta valor para --dry-run" >&2; exit 2; }
        DRY_RUN="$2"
        shift 2
        ;;
      --wp-path)
        [[ $# -ge 2 ]] || { echo "Falta valor para --wp-path" >&2; exit 2; }
        WP_PATH="$2"
        shift 2
        ;;
      --require-term)
        [[ $# -ge 2 ]] || { echo "Falta valor para --require-term" >&2; exit 2; }
        REQUIRE_TERM="$2"
        shift 2
        ;;
      -h|--help)
        usage
        exit 0
        ;;
      --)
        shift
        break
        ;;
      *)
        echo "Opción desconocida: $1" >&2
        usage
        exit 2
        ;;
    esac
  done
}

log() {
  local timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
  local message="$*"
  if [[ -n "$MASTER_LOG" ]]; then
    echo "[$timestamp] $message" | tee -a "$MASTER_LOG"
  else
    echo "[$timestamp] $message"
  fi
}

die() {
  local exit_code=${2:-1}
  log "[ERROR] $1"
  exit "$exit_code"
}

ensure_numeric_flag() {
  local name="$1"
  local value="$2"
  if [[ ! "$value" =~ ^[01]$ ]]; then
    die "Valor inválido para $name: $value (esperado 0 o 1)"
  fi
}

mk_run_dir() {
  PLUG="${PLUG:-"$WP_PATH/wp-content/plugins/compu-import-lego"}"
  RUN_BASE="${WP_PATH}/wp-content/uploads/compu-import"
  mkdir -p "$RUN_BASE"
  local epoch
  epoch="$(date +%s)"
  while :; do
    local candidate="$RUN_BASE/run-$epoch"
    if [[ ! -e "$candidate" ]]; then
      RUN_DIR="$candidate"
      break
    fi
    epoch=$((epoch + 1))
  done
  RUN_ID="$(basename "$RUN_DIR")"
  mkdir -p "$RUN_DIR" "$RUN_DIR/logs" "$RUN_DIR/final"
  MASTER_LOG="$RUN_DIR/logs/master.log"
  touch "$MASTER_LOG"
  chmod 640 "$MASTER_LOG" 2>/dev/null || true
}

subset_csv() {
  local range="$ROWS"
  local src="$SOURCE"
  [[ -f "$src" ]] || die "No se encontró el CSV fuente: $src"
  if [[ -z "$range" ]]; then
    CSV_SRC="$src"
    SOURCE_MASTER="$src"
    return
  fi
  if [[ ! "$range" =~ ^([0-9]+)-([0-9]+)$ ]]; then
    die "Formato inválido para --rows (esperado ini-fin): $range"
  fi
  local start="${BASH_REMATCH[1]}"
  local end="${BASH_REMATCH[2]}"
  if (( end < start )); then
    die "El rango --rows debe tener inicio <= fin"
  fi
  local subset="$RUN_DIR/source_${start}-${end}.csv"
  log "Generando subset $start-$end desde $src"
  "$PYTHON_BIN" - "$src" "$subset" "$start" "$end" <<'PYCODE'
import sys
src, dest, start, end = sys.argv[1:5]
start_i = int(start)
end_i = int(end)
written = 0
with open(src, 'r', encoding='utf-8', errors='ignore') as fin, open(dest, 'w', encoding='utf-8', errors='ignore') as fout:
    header = fin.readline()
    if header:
        fout.write(header)
        written += 1
    for idx, line in enumerate(fin, start=1):
        if idx < start_i:
            continue
        if idx > end_i:
            break
        fout.write(line)
        written += 1
if written <= 1:
    sys.stderr.write('Subset sin filas en el rango solicitado\n')
    sys.exit(1)
PYCODE
  CSV_SRC="$subset"
  SOURCE_MASTER="$subset"
}

ensure_command() {
  local cmd="$1"
  command -v "$cmd" >/dev/null 2>&1 || die "Comando requerido no disponible: $cmd"
}

preflight_checks() {
  log "== Pre-flight checks =="
  ensure_command "$WP_CLI"
  ensure_command "$PHP_BIN"
  ensure_command "$PYTHON_BIN"
  ensure_command jq

  [[ -f "$CSV_SRC" ]] || die "CSV fuente no accesible: $CSV_SRC"

  local available
  available=$(df -Pk "$RUN_BASE" | awk 'NR==2 {print $4}')
  if [[ -n "$available" ]] && (( available < 524288 )); then
    die "Espacio libre insuficiente en $RUN_BASE (se requieren al menos 512 MB)"
  fi

  log "WP-CLI db check"
  local wp_cmd=("$WP_CLI" "--path=$WP_PATH" "--no-color" db check)
  if ! "${wp_cmd[@]}" >/dev/null; then
    die "wp db check falló"
  fi

  log "Aplicando hotfix de vista wp_compu_cats_map"
  local sql="CREATE OR REPLACE VIEW wp_compu_cats_map AS SELECT * FROM compu_cats_map;"
  if ! "$WP_CLI" "--path=$WP_PATH" "--no-color" db query "$sql" >/dev/null; then
    die "No se pudo crear/actualizar la vista wp_compu_cats_map"
  fi

  log "Pre-flight checks completados"
}

resolve_stage_script() {
  local filename="$1"
  local candidates=(
    "$PLUG/stages/$filename"
    "$PLUG/includes/stages/$filename"
    "$REPO/server-mirror/compu-import-lego/includes/stages/$filename"
  )
  local path
  for path in "${candidates[@]}"; do
    if [[ -f "$path" ]]; then
      echo "$path"
      return 0
    fi
  done
  return 1
}

run_stage() {
  local stage_label="$1"
  shift
  local stage_log="$RUN_DIR/logs/${stage_label}.log"
  log "-- Ejecutando stage ${stage_label}"
  if ! ("$@" 2>&1 | tee "$stage_log" | tee -a "$MASTER_LOG"); then
    die "Stage ${stage_label} falló"
  fi
  log "-- Stage ${stage_label} completado"
}

ensure_file() {
  local path="$1"
  local message="$2"
  if [[ ! -s "$path" ]]; then
    die "$message"
  fi
}

run_optional_stage() {
  local label="$1"
  shift
  local script_name="$1"
  shift
  local script_path
  if ! script_path=$(resolve_stage_script "$script_name"); then
    log "Stage opcional $label omitido (no se encontró $script_name)"
    return 0
  fi
  run_stage "$label" "$@" "$script_path"
}

verify_stage10_results() {
  if [[ "$DRY_RUN" == "1" ]]; then
    log "Stage10 en modo dry-run; omitiendo verificación de escrituras"
    return
  fi

  if [[ -z "$RUN_ID" ]]; then
    die "RUN_ID no definido para verificación de Stage10"
  fi

  log "== Verificando escrituras de Stage10 para RUN_ID=$RUN_ID =="

  local offers_sql
  printf -v offers_sql 'SELECT COUNT(*) AS offers_rows FROM wp_compu_offers WHERE run_id = "%s";' "$RUN_ID"
  log "$offers_sql"
  local offers_output
  if ! offers_output=$("$WP_CLI" "--path=$WP_PATH" "--no-color" db query "$offers_sql" --skip-column-names 2>&1); then
    log "$offers_output"
    die "Fallo al ejecutar verificación de ofertas en Stage10"
  fi
  offers_output=$(echo "$offers_output" | tr -d '\r')
  log "Resultado offers_rows: $offers_output"
  local offers_count
  offers_count=$(echo "$offers_output" | awk 'NR==1 {print $1}' | tr -d '[:space:]')
  if [[ -z "$offers_count" || ! "$offers_count" =~ ^[0-9]+$ ]]; then
    die "No se pudo determinar offers_rows para RUN_ID=$RUN_ID"
  fi
  if (( offers_count <= 0 )); then
    die "Stage10 no generó filas en wp_compu_offers para RUN_ID=$RUN_ID"
  fi

  local prices_sql
  printf -v prices_sql 'SELECT COUNT(*) AS woo_price_set FROM wp_postmeta WHERE meta_key IN ("_price","_regular_price") AND post_id IN (SELECT post_id FROM wp_compu_products WHERE last_run_id = "%s");' "$RUN_ID"
  log "$prices_sql"
  local prices_output
  if ! prices_output=$("$WP_CLI" "--path=$WP_PATH" "--no-color" db query "$prices_sql" --skip-column-names 2>&1); then
    log "$prices_output"
    die "Fallo al ejecutar verificación de precios en Stage10"
  fi
  prices_output=$(echo "$prices_output" | tr -d '\r')
  log "Resultado woo_price_set: $prices_output"
  local prices_count
  prices_count=$(echo "$prices_output" | awk 'NR==1 {print $1}' | tr -d '[:space:]')
  if [[ -z "$prices_count" || ! "$prices_count" =~ ^[0-9]+$ ]]; then
    die "No se pudo determinar woo_price_set para RUN_ID=$RUN_ID"
  fi
  if (( prices_count <= 0 )); then
    die "Stage10 no actualizó precios en wp_postmeta para RUN_ID=$RUN_ID"
  fi

  log "Verificación de Stage10 completada: offers_rows=$offers_count, woo_price_set=$prices_count"
}

summarize_artifact() {
  local label="$1"
  local path="$2"
  if [[ -f "$path" ]]; then
    local wc_output
    if ! wc_output=$(wc -l "$path" 2>/dev/null); then
      wc_output="(error al ejecutar wc -l) $path"
    fi
    log "$label: $wc_output"
  else
    log "${label}: NO ENCONTRADO ($path)"
  fi
}

summary_report() {
  log "== Resumen del run =="
  summarize_artifact "source.csv" "$RUN_DIR/source.csv"
  summarize_artifact "normalized.jsonl" "$RUN_DIR/normalized.jsonl"
  summarize_artifact "validated.jsonl" "$RUN_DIR/validated.jsonl"
  summarize_artifact "resolved.jsonl" "$RUN_DIR/resolved.jsonl"
  summarize_artifact "media.jsonl" "$RUN_DIR/media.jsonl"
  summarize_artifact "import-report.json" "$RUN_DIR/final/import-report.json"
  summarize_artifact "postcheck.json" "$RUN_DIR/final/postcheck.json"

  if command -v jq >/dev/null 2>&1; then
    if [[ -f "$RUN_DIR/final/import-report.json" ]]; then
      local import_summary
      import_summary=$(jq -r 'if type=="object" and has("summary") then (.summary | to_entries | map("\(.key)=\(.value)") | join(", ")) elif type=="object" and has("totals") then (.totals | to_entries | map("\(.key)=\(.value)") | join(", ")) else empty end' "$RUN_DIR/final/import-report.json" || true)
      if [[ -n "$import_summary" ]]; then
        log "Import-report resumen: $import_summary"
      fi
      local dry
      dry=$(jq -r 'if type=="object" and has("dry_run") then ("dry_run=" + (if .dry_run then "yes" else "no" end)) else empty end' "$RUN_DIR/final/import-report.json" || true)
      if [[ -n "$dry" ]]; then
        log "Import-report dry_run: $dry"
      fi
      local flags
      flags=$(jq -r 'if type=="object" and has("flags") then (.flags | to_entries | map("\(.key)=\(.value)") | join(", ")) else empty end' "$RUN_DIR/final/import-report.json" || true)
      if [[ -n "$flags" ]]; then
        log "Import-report flags: $flags"
      fi
    fi
    if [[ -f "$RUN_DIR/final/postcheck.json" ]]; then
      local diffs
      diffs=$(jq -r 'if has("diffs") then ("diffs=" + ((.diffs | length) | tostring)) else empty end' "$RUN_DIR/final/postcheck.json" || true)
      if [[ -n "$diffs" ]]; then
        log "Stage11: $diffs"
      fi
    fi
  fi

  log "Dry-run flag: $DRY_RUN"
  log "Run ID: $RUN_ID"
  log "Run directory: $RUN_DIR"
}

rotate_runs() {
  local keep="$RUNS_TO_KEEP"
  if [[ -z "$keep" || ! "$keep" =~ ^[0-9]+$ ]]; then
    return
  fi
  if (( keep <= 0 )); then
    return
  fi
  local runs=()
  while IFS= read -r line; do
    runs+=("$line")
  done < <(ls -1dt "$RUN_BASE"/run-* 2>/dev/null || true)
  local total=${#runs[@]}
  if (( total <= keep )); then
    return
  fi
  for ((i=keep; i<total; i++)); do
    local path="${runs[$i]}"
    if [[ "$path" != "$RUN_DIR" && -d "$path" ]]; then
      log "Eliminando run antiguo: $path"
      rm -rf "$path"
    fi
  done
}

run_pipeline() {
  ensure_numeric_flag "--dry-run" "$DRY_RUN"
  ensure_numeric_flag "--require-term" "$REQUIRE_TERM"
  mk_run_dir
  trap 'status=$?; if [[ $status -ne 0 ]]; then log "Run abortado con código $status"; else log "Run finalizado con éxito"; fi' EXIT

  subset_csv

  export RUN_DIR
  export RUN_PATH="$RUN_DIR"
  export RUN_ID
  export DRY_RUN REQUIRE_TERM
  export LIMIT=0
  export STAGE_DEBUG=1
  export FORCE_CSV=1
  export CSV_SRC SOURCE_MASTER
  export SOURCE_MASTER
  export CSV="$CSV_SRC"
  export WP_PATH
  export WP_CLI
  export WP_PATH_ARGS="--no-color"
  export WP_TABLE_PREFIX

  log "== Inicio del run =="
  log "Run ID: $RUN_ID"
  log "Fuente: $CSV_SRC (rows=${ROWS:-'completo'})"
  log "WP path: $WP_PATH"
  log "Repo: $REPO"

  preflight_checks

  local stage_runner="$REPO/tests/stage_runner.php"
  [[ -f "$stage_runner" ]] || die "No se encontró stage_runner.php en $stage_runner"

  run_stage "01-fetch" "$PHP_BIN" "${PHP_OPTS[@]}" "$stage_runner" 01-fetch "--run-dir=$RUN_DIR" "--source=$CSV_SRC"
  ensure_file "$RUN_DIR/source.csv" "Stage 01 no generó source.csv"

  run_stage "02-normalize" "$PHP_BIN" "${PHP_OPTS[@]}" "$stage_runner" 02-normalize "--run-dir=$RUN_DIR"
  ensure_file "$RUN_DIR/normalized.jsonl" "Stage 02 no generó normalized.jsonl"

  run_stage "03-validate" "$PHP_BIN" "${PHP_OPTS[@]}" "$stage_runner" 03-validate "--run-dir=$RUN_DIR"
  ensure_file "$RUN_DIR/validated.jsonl" "Stage 03 no generó validated.jsonl"

  local stage04_script
  if [[ -f "$REPO/python/python/stage04_resolve_map.py" ]]; then
    stage04_script="$REPO/python/python/stage04_resolve_map.py"
  else
    stage04_script="$REPO/python/stage04_resolve_map.py"
  fi
  [[ -f "$stage04_script" ]] || die "No se encontró stage04_resolve_map.py"
  run_stage "04-resolve" "$PYTHON_BIN" "$stage04_script" \
    --run-dir "$RUN_DIR" \
    --input "$RUN_DIR/validated.jsonl" \
    --output "$RUN_DIR/resolved.jsonl" \
    --log "$RUN_DIR/logs/stage04.log" \
    --unmapped "$RUN_DIR/final/unmapped.csv" \
    --invalid "$RUN_DIR/final/invalid_term_ids.csv" \
    --metrics "$RUN_DIR/final/stage04-metrics.json" \
    --wp-cli "$WP_CLI" \
    --wp-path "$WP_PATH" \
    --wp-args="--no-color" \
    --table-prefix "$WP_TABLE_PREFIX" \
    --require-term "$REQUIRE_TERM"
  ensure_file "$RUN_DIR/resolved.jsonl" "Stage 04 no generó resolved.jsonl"

  local stage05
  stage05=$(resolve_stage_script "05-terms.php") || die "No se encontró stage 05"
  run_stage "05-terms" "$WP_CLI" "--path=$WP_PATH" "--no-color" eval-file "$stage05"

  local stage06
  stage06=$(resolve_stage_script "06-products.php") || die "No se encontró stage 06"
  run_stage "06-products" "$PHP_BIN" "${PHP_OPTS[@]}" "$stage06"

  local stage07
  stage07=$(resolve_stage_script "07-media.php") || die "No se encontró stage 07"
  run_stage "07-media" "$WP_CLI" "--path=$WP_PATH" "--no-color" eval-file "$stage07"
  ensure_file "$RUN_DIR/media.jsonl" "Stage 07 no generó media.jsonl"

  run_optional_stage "08-offers" "08-offers.php" "$WP_CLI" "--path=$WP_PATH" "--no-color" eval-file
  run_optional_stage "09-pricing" "09-pricing.php" "$WP_CLI" "--path=$WP_PATH" "--no-color" eval-file

  local stage10_script
  stage10_script=$(resolve_stage_script "stage10_apply_fast_v2.php") || die "No se encontró stage10_apply_fast_v2.php"
  export ST10_WRITE_OFFERS=1
  export ST10_AUTO_ENSURE_COMPU_PRODUCT=1
  log "Stage10 dry-run flag: $DRY_RUN"
  run_stage "10-apply-fast" "$WP_CLI" "--path=$WP_PATH" "--no-color" eval-file "$stage10_script"
  ensure_file "$RUN_DIR/final/import-report.json" "Stage 10 no generó final/import-report.json"
  verify_stage10_results

  local stage11_script
  stage11_script="$REPO/python/stage11_postcheck.py"
  [[ -f "$stage11_script" ]] || die "No se encontró stage11_postcheck.py"
  run_stage "11-postcheck" "$PYTHON_BIN" "$stage11_script" \
    --run-dir "$RUN_DIR" \
    --import-report "$RUN_DIR/final/import-report.json" \
    --postcheck "$RUN_DIR/final/postcheck.json" \
    --log "$RUN_DIR/logs/stage11.log" \
    --dry-run "$DRY_RUN" \
    --writer wp \
    --wp-path "$WP_PATH" \
    --wp-args="--no-color" \
    --run-id "$RUN_ID"
  ensure_file "$RUN_DIR/final/postcheck.json" "Stage 11 no generó final/postcheck.json"

  summary_report
  rotate_runs
}

parse_args "$@"
run_pipeline
