# CLI smoke test transcript

## Environment
- Root: `/workspace/compustar-project/htdocs`
- Plugin: `/workspace/compustar-project/server-mirror/compu-import-lego`
- CSV: `/workspace/compustar-project/data/cli-smoke-products.csv`
- Runner wrapper: `/workspace/compustar-project/htdocs/compu-run-cli.php`

## Check 1 — `--help`
- Command: `php -d display_errors=1 /workspace/compustar-project/htdocs/compu-run-cli.php --help`
- Exit code: `0`
- STDERR (first 20 lines): _empty_

## Check 2 — invalid stages
- Command: `php -d display_errors=1 /workspace/compustar-project/htdocs/compu-run-cli.php --stages=99`
- Exit code: `2`
- STDERR (first 20 lines):
  ```
  Invalid --stages specification. Allowed values: 02..06 or a comma-separated subset of 02,03,04,06.
  ```

## Check 3 — dry-run structure
- Command: `php -d display_errors=1 /workspace/compustar-project/htdocs/compu-run-cli.php --stages=02,03 --dry-run=1 --csv=/workspace/compustar-project/data/cli-smoke-products.csv --wp-root=/workspace/compustar-project/htdocs --plugin-dir=/workspace/compustar-project/server-mirror/compu-import-lego`
- Exit code: `0`
- STDERR (first 20 lines): _empty_
- RUN_DIR: `/workspace/compustar-project/htdocs/wp-content/uploads/compu-import/run-20251007205337448`
- `tail -n 20` of `logs/run.log`:
  ```
{"ts":"2025-10-07T20:53:37+00:00","stage":"RUN","level":"INFO","msg":"Run started","run_id":"20251007205337448","stages":["02","03"]}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"METRIC","metrics":{"dry_run":1,"duration_ms":0}}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"INFO","notes":"Dry-run: skipped stage execution"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"WARN","msg":"Expected output artifact missing","artifact":"normalized.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"WARN","msg":"Expected output artifact missing","artifact":"normalized.csv"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"DONE","status":"WARN"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"WARN","msg":"Missing input artifact","artifact":"normalized.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"METRIC","metrics":{"dry_run":1,"duration_ms":0}}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"INFO","notes":"Dry-run: skipped stage execution"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"WARN","msg":"Expected output artifact missing","artifact":"validated.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"DONE","status":"WARN"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"RUN","level":"INFO","msg":"Run finished","status":"WARN","summary":"/workspace/compustar-project/htdocs/wp-content/uploads/compu-import/run-20251007205337448/final/summary.json"}
  ```
- `tail -n 20` of `logs/stage-02.log`:
  ```
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"METRIC","metrics":{"dry_run":1,"duration_ms":0}}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"INFO","notes":"Dry-run: skipped stage execution"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"WARN","msg":"Expected output artifact missing","artifact":"normalized.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"WARN","msg":"Expected output artifact missing","artifact":"normalized.csv"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"02","level":"DONE","status":"WARN"}
  ```
- `tail -n 20` of `logs/stage-03.log`:
  ```
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"WARN","msg":"Missing input artifact","artifact":"normalized.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"METRIC","metrics":{"dry_run":1,"duration_ms":0}}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"INFO","notes":"Dry-run: skipped stage execution"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"WARN","msg":"Expected output artifact missing","artifact":"validated.jsonl"}
{"ts":"2025-10-07T20:53:37+00:00","stage":"03","level":"DONE","status":"WARN"}
  ```

## Check 4 — staged run (02..06)
- Command: `php -d display_errors=1 /workspace/compustar-project/htdocs/compu-run-cli.php --stages=02..06 --from=1000 --rows=201 --dry-run=0 --require-term=1 --csv=/workspace/compustar-project/data/cli-smoke-products.csv --wp-root=/workspace/compustar-project/htdocs --plugin-dir=/workspace/compustar-project/server-mirror/compu-import-lego`
- Exit code: `0`
- STDERR (first 20 lines): _empty_
- RUN_DIR: `/workspace/compustar-project/htdocs/wp-content/uploads/compu-import/run-20251007205338152`
- `tail -n 20` of `logs/run.log`:
  ```
{"ts":"2025-10-07T20:53:38+00:00","stage":"RUN","level":"INFO","msg":"Run started","run_id":"20251007205338152","stages":["02","03","04","06"]}
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"METRIC","metrics":{"duration_ms":31,"rows_in":5,"rows_out":5,"skipped":0,"missing_sku":0,"missing_lvl1_id":0,"missing_lvl2_id":0}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"DONE","status":"OK"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"METRIC","metrics":{"duration_ms":0,"rows_in":5,"rows_out":5,"skipped":0,"missing_sku":0,"missing_lvl1_id":0,"missing_lvl2_id":0}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"DONE","status":"OK"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"INFO","msg":"Starting stage","title":"Resolve category mapping"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"METRIC","metrics":{"duration_ms":0,"rows_in":5,"rows_out":5,"skipped":0,"unmapped":5}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"DONE","status":"WARN"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"INFO","msg":"Starting stage","title":"Products simulation writer"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"METRIC","metrics":{"rows_in":5,"rows_out":0,"skipped":0,"duration_ms":91}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/imported.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/updated.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/skipped.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"DONE","status":"OK"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"RUN","level":"INFO","msg":"Run finished","status":"WARN","summary":"/workspace/compustar-project/htdocs/wp-content/uploads/compu-import/run-20251007205338152/final/summary.json"}
  ```
- `tail -n 20` of `logs/stage-02.log`:
  ```
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"METRIC","metrics":{"duration_ms":31,"rows_in":5,"rows_out":5,"skipped":0,"missing_sku":0,"missing_lvl1_id":0,"missing_lvl2_id":0}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"02","level":"DONE","status":"OK"}
  ```
- `tail -n 20` of `logs/stage-03.log`:
  ```
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"METRIC","metrics":{"duration_ms":0,"rows_in":5,"rows_out":5,"skipped":0,"missing_sku":0,"missing_lvl1_id":0,"missing_lvl2_id":0}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"03","level":"DONE","status":"OK"}
  ```
- `tail -n 20` of `logs/stage-04.log`:
  ```
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"INFO","msg":"Starting stage","title":"Resolve category mapping"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"METRIC","metrics":{"duration_ms":0,"rows_in":5,"rows_out":5,"skipped":0,"unmapped":5}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"04","level":"DONE","status":"WARN"}
  ```
- `tail -n 20` of `logs/stage-06.log`:
  ```
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"INFO","msg":"Starting stage","title":"Products simulation writer"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"METRIC","metrics":{"rows_in":5,"rows_out":0,"skipped":0,"duration_ms":91}}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/imported.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/updated.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"WARN","msg":"Expected output artifact missing","artifact":"final/skipped.csv"}
{"ts":"2025-10-07T20:53:38+00:00","stage":"06","level":"DONE","status":"OK"}
  ```

_All timestamps are UTC._
