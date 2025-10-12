# Stage 10 v2

Stage 10 v2 aplica los payloads JSONL contra WordPress/WooCommerce usando el script `python/stage10_v2.py` y el ejecutor rápido `stage10_apply_fast_v2.php`.

## Variables de entorno

- `ST10_V2`: habilita el uso del nuevo ejecutor desde `scripts/run_compustar_import.sh`.
- `WP_BIN`: ruta al binario de WP-CLI (por defecto `/usr/local/bin/wp`).
- `WP_PATH_ARGS`: argumentos extra para WP-CLI; si no incluyen `--path` se añade automáticamente con la ruta de WordPress.
- `WP_ROOT`: ruta base de WordPress (por defecto `/home/compustar/htdocs`).
- `ST10_FAST_PHP`: ruta alternativa al archivo `stage10_apply_fast_v2.php`.
- `ST10_GUARD_PRICE_ZERO`: activa/desactiva el guardado de precios cero (por defecto activado).
- `ST10_ALLOW_CREATE`: permite crear productos nuevos cuando no existen (desactivado por defecto).
- `DRY_RUN`: si está activo, Stage 10 v2 no ejecuta WP-CLI y marca cada payload como `dry_run`.

## Ejemplo

```bash
ST10_V2=1 scripts/run_compustar_import.sh --rows 1120-1135
```
