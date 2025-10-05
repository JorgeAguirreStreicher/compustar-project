#!/bin/sh
# jsonl_doctor.sh - Analyze LEGO import JSONL stages and highlight problematic fields.
#
# Usage:
#   jsonl_doctor.sh --run_dir PATH
#   jsonl_doctor.sh  # defaults to COMPU_IMPORT_RUN_DIR or the current directory
#
# The script inspects normalized/validated/resolved/terms JSONL outputs inside the
# chosen run directory, summarises per-field coverage, and highlights numerical
# fields whose positive values nearly vanish. Dependencies: jq, awk.

set -eu

usage() {
    cat <<'USAGE'
Usage: jsonl_doctor.sh [--run_dir PATH]

Options:
  --run_dir PATH   Directory containing stage JSONL files (normalized.jsonl, ...).
  -h, --help       Show this message.

Environment:
  COMPU_IMPORT_RUN_DIR  Default directory when --run_dir is omitted.
USAGE
}

RUN_DIR=""
while [ "${1-}" != "" ]; do
    case $1 in
        --run_dir)
            if [ "${2-}" = "" ]; then
                echo "--run_dir requires a value" >&2
                exit 1
            fi
            RUN_DIR=$2
            shift 2
            ;;
        --run_dir=*)
            RUN_DIR=${1#*=}
            shift 1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

if [ "$RUN_DIR" = "" ]; then
    if [ "${COMPU_IMPORT_RUN_DIR-}" != "" ]; then
        RUN_DIR=$COMPU_IMPORT_RUN_DIR
    else
        RUN_DIR=.
    fi
fi

if [ ! -d "$RUN_DIR" ]; then
    echo "Run directory not found: $RUN_DIR" >&2
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required" >&2
    exit 1
fi

if ! command -v awk >/dev/null 2>&1; then
    echo "awk is required" >&2
    exit 1
fi

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ALIAS_FILE=$SCRIPT_DIR/../aliases.json

BASE_FIELDS="sku brand title price_list price_special price_customer exchange_rate weight_kg tax_code description image_url cat_lvl1 cat_lvl2 cat_lvl3 cat_id_lvl1 cat_id_lvl2 cat_id_lvl3"
BASE_JSON=$(printf '%s\n' $BASE_FIELDS | jq -Rn '[inputs] | map(select(length>0)) | reduce .[] as $f ({}; .[$f] = [$f])')

if [ -f "$ALIAS_FILE" ]; then
    CANONICAL_JSON=$(jq -n --argjson base "$BASE_JSON" --slurpfile alias "$ALIAS_FILE" '($alias[0] // {}) as $aliases | reduce ($aliases | to_entries[]) as $entry ($base; .[$entry.key] = (((.[$entry.key] // []) + [$entry.key] + ($entry.value // [])) | unique))')
else
    echo "Warning: aliases.json not found, only base fields will be used." >&2
    CANONICAL_JSON=$BASE_JSON
fi

ALIAS_TMP=$(mktemp)
JQ_PROG=$(mktemp)
trap 'rm -f "$ALIAS_TMP" "$JQ_PROG"' EXIT

printf '%s' "$CANONICAL_JSON" | jq -r 'to_entries[] | "\(.key)\t\(.value | join(", "))"' >"$ALIAS_TMP"

cat <<'JQ' >"$JQ_PROG"
  def clean($v):
    if $v == null then null
    elif ($v | type) == "string" then ($v | gsub("^\\s+|\\s+$"; ""))
    else $v end;
  def present_values($row; $names):
    reduce $names[] as $n ({};
      (clean($row[$n])) as $val |
      if $val == null then .
      elif ($val | type) == "string" and $val == "" then .
      elif ($val | type) == "array" and ($val | length) == 0 then .
      else . + {($n): $val} end);
  def has_positive($vals):
    [$vals[] |
      if type == "number" then .
      elif type == "string" then (try (tonumber) catch null)
      else null end |
      select(. != null and . > 0)
    ] | length > 0;
  ($field_map | keys) as $keys |
  reduce $keys[] as $k ({total:0, fields:{}}; .fields[$k] = {present:0, positive:0, examples:[], numeric: ($k | test($numeric_pattern))}) |
  reduce inputs as $row (
    .;
    .total += 1 |
    reduce $keys[] as $k (
      .;
      ($field_map[$k]) as $names |
      (present_values($row; $names)) as $vals |
      if ($vals | length) > 0 then
        .fields[$k].present += 1 |
        (.fields[$k].examples |= (if length < 3 then . + [$vals] else . end)) |
        (if (.fields[$k].numeric) and has_positive($vals) then
          .fields[$k].positive += 1
        else . end)
      else
        .
      end
    )
  )
JQ

NUMERIC_PATTERN='^(price_|stock_|exchange_rate|weight_)'
COVERAGE_THRESHOLD=0.01

printf 'Analyzing run directory: %s\n' "$RUN_DIR"

STAGES="normalized validated resolved terms"
for stage in $STAGES; do
    stage_file=$RUN_DIR/$stage.jsonl
    if [ ! -f "$stage_file" ]; then
        printf '\nStage: %s (missing %s)\n' "$stage" "$stage_file"
        continue
    fi

    metrics_json=$(jq -n --argjson field_map "$CANONICAL_JSON" --arg numeric_pattern "$NUMERIC_PATTERN" -f "$JQ_PROG" "$stage_file")
    total=$(printf '%s' "$metrics_json" | jq '.total')

    printf '\nStage: %s (%s rows)\n' "$stage" "$total"
    if [ "$total" -eq 0 ]; then
        printf '  (no rows found)\n'
        continue
    fi

    printf '  %-24s %8s %9s %8s %9s\n' Field Present Coverage Positive 'Pos >0'

    metrics_lines=$(printf '%s' "$metrics_json" | jq -r '. as $root | ($root.fields | to_entries | sort_by(.key))[] | [ .key, (.value.present // 0), (.value.positive // 0), (if $root.total == 0 then 0 else ((.value.present // 0) / $root.total) end), (if (.value.present // 0) == 0 then 0 else ((.value.positive // 0) / (.value.present // 0)) end), (.value.examples[0] // {} | @json), (if .value.numeric then 1 else 0 end) ] | @tsv')

    first_row=$(sed -n '1p' "$stage_file")

    printf '%s\n' "$metrics_lines" | while IFS="$(printf '\t')" read -r field present positive coverage_ratio positive_ratio example numeric_flag; do
        coverage_pct=$(awk -v v="$coverage_ratio" 'BEGIN{printf "%.2f", v * 100}')
        coverage_display=${coverage_pct}%

        if [ "$numeric_flag" = "1" ]; then
            positive_pct=$(awk -v v="$positive_ratio" 'BEGIN{printf "%.2f", v * 100}')
            positive_display=$positive
            positive_pct_display=${positive_pct}%
            positive_flag=$(awk -v v="$positive_ratio" -v t="$COVERAGE_THRESHOLD" 'BEGIN{if (v <= t) print 1; else print 0}')
        else
            positive_display=-
            positive_pct_display=-
            positive_flag=0
            positive_pct=0
        fi

        printf '  %-24s %8s %9s %8s %9s\n' "$field" "$present" "$coverage_display" "$positive_display" "$positive_pct_display"

        coverage_flag=$(awk -v v="$coverage_ratio" -v t="$COVERAGE_THRESHOLD" 'BEGIN{if (v <= t) print 1; else print 0}')
        aliases=$(awk -F'\t' -v key="$field" 'BEGIN{out=""} $1==key {out=$2} END{print out}' "$ALIAS_TMP")

        if [ "$coverage_flag" -eq 1 ] && [ "$total" -gt 0 ]; then
            if [ "$present" -eq 0 ]; then
                printf '    ⚠️  %s present in only %s (%s/%s rows). Aliases: %s\n' "$field" "$coverage_display" "$present" "$total" "${aliases:-$field}"
                if [ "$first_row" != "" ]; then
                    printf '        Example row: %s\n' "$first_row"
                fi
            else
                printf '    ⚠️  %s coverage is %s (%s/%s rows).\n' "$field" "$coverage_display" "$present" "$total"
                if [ "$example" != '{}' ]; then
                    printf '        Example values: %s\n' "$example"
                fi
            fi
        fi

        if [ "$positive_flag" -eq 1 ] && [ "$present" -gt 0 ]; then
            printf '    ⚠️  %s positive (>0) rate is %s (%s/%s rows).\n' "$field" "$positive_pct_display" "$positive" "$present"
            if [ "$example" != '{}' ]; then
                printf '        Example values: %s\n' "$example"
            fi
        fi
    done
done
