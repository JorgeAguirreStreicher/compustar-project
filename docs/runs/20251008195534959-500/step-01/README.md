# Auditoría Stage 01..03 — 500 filas

## Comandos ejecutados
- Ayuda de `compu-run-cli`
- Ejecución stages 01..03 (500 filas, modo cron)

## RUN_DIR
- `/home/compustar/htdocs/wp-content/uploads/compu-import/run-20251008195534959`

## Conteo de filas
- `source.csv`: 501
- `normalized.jsonl`: 500
- `validated.jsonl`: 440

## header-map.json
- Generado correctamente para el run.

## Últimas líneas de logs

### run.log (20)
```
{"ts":"2025-10-08T19:55:34+00:00","stage":"RUN","level":"INFO","msg":"Run started","run_id":"20251008195534959","stages":["01","02","03"]}
{"ts":"2025-10-08T19:55:34+00:00","stage":"01","level":"INFO","msg":"Starting stage","title":"Fetch source CSV"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"01","level":"METRIC","metrics":{"duration_ms":3,"rows_out":500,"rows_in":500}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"01","level":"DONE","status":"OK"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"METRIC","metrics":{"duration_ms":294,"rows_in":500,"rows_out":500,"skipped":0,"missing_sku":0,"missing_lvl1_id":500,"missing_lvl2_id":500}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"DONE","status":"WARN"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"METRIC","metrics":{"duration_ms":137,"rows_in":500,"rows_out":440,"skipped":60,"missing_sku":0,"missing_lvl1_id":440,"missing_lvl2_id":440}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"DONE","status":"WARN"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"RUN","level":"INFO","msg":"Run finished","status":"WARN","summary":"/home/compustar/htdocs/wp-content/uploads/compu-import/run-20251008195534959/final/summary.json"}
```

### stage-01.log (10)
```
{"ts":"2025-10-08T19:55:34+00:00","stage":"01","level":"INFO","msg":"Starting stage","title":"Fetch source CSV"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"01","level":"METRIC","metrics":{"duration_ms":3,"rows_out":500,"rows_in":500}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"01","level":"DONE","status":"OK"}
```

### stage-02.log (10)
```
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"INFO","msg":"Starting stage","title":"Normalize source CSV"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"METRIC","metrics":{"duration_ms":294,"rows_in":500,"rows_out":500,"skipped":0,"missing_sku":0,"missing_lvl1_id":500,"missing_lvl2_id":500}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"02","level":"DONE","status":"WARN"}
```

### stage-03.log (10)
```
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"INFO","msg":"Starting stage","title":"Validate normalized data"}
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"METRIC","metrics":{"duration_ms":137,"rows_in":500,"rows_out":440,"skipped":60,"missing_sku":0,"missing_lvl1_id":440,"missing_lvl2_id":440}}
{"ts":"2025-10-08T19:55:35+00:00","stage":"03","level":"DONE","status":"WARN"}
```

## Motivos principales de descarte (top 5)
- `[validate] invalid_lvl1` — 50
- `Campo obligatorio faltante: Título` — 10
