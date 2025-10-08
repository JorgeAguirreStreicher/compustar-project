#!/usr/bin/env bash
set -euo pipefail

ROOT_DEFAULT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/htdocs"
ROOT="${ROOT_OVERRIDE:-$ROOT_DEFAULT}"
CLI="$ROOT/compu-run-cli.php"
PHP_BIN="${PHP_BIN:-php}"
CSV_DEFAULT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/data/cli-smoke-products.csv"
CSV_PATH="${CSV_OVERRIDE:-$CSV_DEFAULT}"
PLUGIN_DEFAULT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/server-mirror/compu-import-lego"
PLUGIN_DIR="${PLUGIN_OVERRIDE:-$PLUGIN_DEFAULT}"
TMPDIR="${TMPDIR:-/tmp}"
OUT_PREFIX="$TMPDIR/cli-smoke"

mkdir -p "$ROOT/wp-content/uploads"

printf 'Running CLI smoke tests with ROOT=%s\n' "$ROOT"

# Check 1 — help
HELP_OUT="$OUT_PREFIX.help.out"
HELP_ERR="$OUT_PREFIX.help.err"
set +e
"$PHP_BIN" -d display_errors=1 "$CLI" --help >"$HELP_OUT" 2>"$HELP_ERR"
status=$?
set -e
if [[ $status -ne 0 ]]; then
  echo "Check 1 failed with exit $status" >&2
  exit 1
fi
if ! grep -q 'Compu Import unified runner' "$HELP_OUT"; then
  echo "Check 1 output missing header" >&2
  exit 1
fi

# Check 2 — invalid stages
ST99_OUT="$OUT_PREFIX.st99.out"
ST99_ERR="$OUT_PREFIX.st99.err"
set +e
"$PHP_BIN" -d display_errors=1 "$CLI" --stages=99 >"$ST99_OUT" 2>"$ST99_ERR"
status=$?
set -e
if [[ $status -ne 2 ]]; then
  echo "Check 2 expected exit 2, got $status" >&2
  exit 1
fi
if ! grep -q 'Invalid --stages specification' "$ST99_ERR"; then
  echo "Check 2 missing invalid stages message" >&2
  exit 1
fi

# Check 3 — dry-run structure
DRY_OUT="$OUT_PREFIX.dry.out"
DRY_ERR="$OUT_PREFIX.dry.err"
set +e
"$PHP_BIN" -d display_errors=1 "$CLI" \
  --stages=01,02,03 --dry-run=1 \
  --csv="$CSV_PATH" --wp-root="$ROOT" --plugin-dir="$PLUGIN_DIR" \
  >"$DRY_OUT" 2>"$DRY_ERR"
status=$?
set -e
if [[ $status -ne 0 ]]; then
  echo "Check 3 expected exit 0, got $status" >&2
  exit 1
fi
RUN_DIR_DRY=$(ls -1dt "$ROOT"/wp-content/uploads/compu-import/run-* 2>/dev/null | head -1 || true)
if [[ -z "$RUN_DIR_DRY" ]]; then
  echo "Check 3 could not locate run directory" >&2
  exit 1
fi
if [[ ! -d "$RUN_DIR_DRY/logs" ]]; then
  echo "Check 3 missing logs directory" >&2
  exit 1
fi
if [[ ! -f "$RUN_DIR_DRY/logs/run.log" ]]; then
  echo "Check 3 missing run.log" >&2
  exit 1
fi
if [[ ! -f "$RUN_DIR_DRY/source.csv" && ! -L "$RUN_DIR_DRY/source.csv" ]]; then
  echo "Check 3 missing source.csv link or file" >&2
  exit 1
fi
printf 'Dry-run run dir: %s\n' "$RUN_DIR_DRY"

# Check 4 — real subset run
REAL_OUT="$OUT_PREFIX.real.out"
REAL_ERR="$OUT_PREFIX.real.err"
set +e
"$PHP_BIN" -d display_errors=1 "$CLI" \
  --stages=01..06 \
  --from=1000 --rows=201 --dry-run=0 --require-term=1 \
  --csv="$CSV_PATH" --wp-root="$ROOT" --plugin-dir="$PLUGIN_DIR" \
  >"$REAL_OUT" 2>"$REAL_ERR"
status=$?
set -e
if [[ $status -ne 0 ]]; then
  echo "Check 4 expected exit 0, got $status" >&2
  exit 1
fi
RUN_DIR_REAL=$(ls -1dt "$ROOT"/wp-content/uploads/compu-import/run-* 2>/dev/null | head -1 || true)
if [[ -z "$RUN_DIR_REAL" ]]; then
  echo "Check 4 could not locate run directory" >&2
  exit 1
fi
if [[ ! -f "$RUN_DIR_REAL/final/summary.json" ]]; then
  echo "Check 4 missing summary.json" >&2
  exit 1
fi
for stage in 01 02 03 04 06; do
  if [[ ! -f "$RUN_DIR_REAL/logs/stage-$stage.log" ]]; then
    echo "Check 4 missing log for stage $stage" >&2
    exit 1
  fi
done
printf 'Real run dir: %s\n' "$RUN_DIR_REAL"

echo "CLI smoke tests completed successfully."
