# Compu Import Unified Runner

## CLI entrypoint
- Script: `/home/compustar/htdocs/compu-run.php` (PHP CLI).
- Example full run: `php /home/compustar/htdocs/compu-run.php --stages=02..06 --dry-run=0 --require-term=1`.
- Subset example (02..06, 201 rows starting at 1000): `php /home/compustar/htdocs/compu-run.php --stages=02..06 --from=1000 --rows=201 --limit=0 --offset=0`.
- Exit codes: `0` success/only warnings, `2` input/config errors, `3` stage failure.

## Flags & environment
- `--stages=` accepts ranges (`02..06`) or lists (`02,03,04`).
- `--dry-run`, `--require-term`, `--sample600`, `--limit`, `--offset`, `--from`/`--rows` (subset aliases), `--csv` (source override), `--run-base`, `--run-dir`, `--run-id`, `--wp-root`, `--plugin-dir`, `--wp-cli`, `--php-bin`.
- Environment overrides (`export VAR=...`): `WP_ROOT`, `PLUGIN_DIR`, `RUN_BASE`, `CSV_SRC`, `SOURCE_MASTER`, `DRY_RUN`, `LIMIT`, `OFFSET`, `REQUIRE_TERM`, `SAMPLE600`, `SUBSET_FROM`, `SUBSET_ROWS`.

## Run directory layout
- A new `RUN_ID` (e.g. `20251007-161639-0392a3`) is created under `RUN_BASE` (`wp-content/uploads/compu-import`).
- Structure:
  - `source.csv` → symlink/copy of input CSV when provided.
  - `logs/run.log` → overall JSONL timeline.
  - `logs/stage-XX.log` → per-stage JSONL (same schema).
  - `final/` → stage outputs (`imported.csv`, `updated.csv`, `skipped.csv`, `summary.json`, etc.).
  - `tmp/` → scratch area per stage.

## Logging & observability
- JSONL record shape: `{ "ts": ISO8601, "stage": "XX", "level": "INFO|WARN|ERROR|METRIC|DONE", ... }`.
- Metrics are attached via `level="METRIC"` and include `rows_in`, `rows_out`, `skipped`, `duration_ms`, plus stage-specific counters.
- `run.log` aggregates every stage record; per-stage files mirror only their own events.

## Summary interpretation
- `final/summary.json` consolidates each stage status (`OK|WARN|ERROR`), metrics, artifacts, and notes.
- Treat WARN as actionable checks (e.g., missing artifacts, dry-runs, skipped rows). ERROR stops the pipeline and returns exit code `3`.
- Artifacts list absolute paths (CSV/JSONL/logs) for regression and handoff.
