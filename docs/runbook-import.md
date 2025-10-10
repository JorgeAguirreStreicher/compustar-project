# Runbook — Orquestador de importación Compustar

## 1. Descripción general

El script `scripts/run_compustar_import.sh` ejecuta en secuencia los Stages 01 a 11 del pipeline **Compustar LEGO** dentro de un único RUN, asegurando exclusión mutua (`flock`), logging detallado y parametrización para ejecuciones completas o parciales. Cada etapa produce artefactos que alimentan a la siguiente, con validaciones estrictas tras cada paso.

| Stage | Herramienta            | Descripción resumida                                 | Artefactos clave                          |
|-------|------------------------|------------------------------------------------------|-------------------------------------------|
| 01    | PHP (stage_runner)     | Copia el CSV fuente al RUN (`source.csv`).           | `source.csv`                              |
| 02    | PHP (stage_runner)     | Normaliza encabezados y registros.                   | `normalized.jsonl`                        |
| 03    | PHP (stage_runner)     | Valida registros normalizados.                       | `validated.jsonl`                         |
| 04    | Python                 | Resuelve el mapeo de categorías contra `compu_cats_map`. | `resolved.jsonl`, métricas y reportes     |
| 05    | WP-CLI `eval-file`     | Enlaza términos existentes sin crear categorías.     | `terms_resolved.jsonl` (auxiliar)         |
| 06    | PHP                    | Prepara productos para importación.                  | Artefactos intermedios en el RUN          |
| 07    | WP-CLI `eval-file`     | Gestiona media/imágenes del catálogo.                | `media.jsonl`, `media/`                   |
| 08    | WP-CLI `eval-file`     | (Opcional) Precios/ofertas.                          | `final/offers_*` (si aplica)              |
| 09    | WP-CLI `eval-file`     | (Opcional) Stock/actualizaciones de pricing.         | `final/pricing_*` (si aplica)             |
| 10    | Python                 | Importa productos en WooCommerce (`writer=wp`).      | `final/import-report.json`, logs          |
| 11    | Python                 | Auditoría post-import.                               | `final/postcheck.json`                    |

Todos los artefactos se generan en `wp-content/uploads/compu-import/run-<epoch>` dentro de la instalación de WordPress.

## 2. Instalación y requisitos

1. **Ubicación de scripts**: el orquestador reside en `scripts/run_compustar_import.sh` dentro del repositorio `/home/compustar/compustar-project`.
2. **Permisos**: otorgar permisos de ejecución.
   ```bash
   chmod +x /home/compustar/compustar-project/scripts/run_compustar_import.sh
   chmod +x /home/compustar/compustar-project/tests/smoke_import.sh
   ```
3. **Dependencias** (verificadas en el `preflight`):
   - `wp` (`/usr/local/bin/wp`)
   - `php`
   - `python3`
   - `jq`
4. **WordPress**: ruta base `/home/compustar/htdocs` con plugin `compu-import-lego` instalado.
5. **Hotfix requerido**: el script aplica automáticamente la vista `wp_compu_cats_map` → `compu_cats_map` para entornos donde el prefijo `wp_` es obligatorio.

## 3. Variables y parámetros

### 3.1 CLI

| Opción             | Descripción                                                          | Default                                      |
|--------------------|----------------------------------------------------------------------|----------------------------------------------|
| `--source <path>`  | CSV a procesar.                                                      | `/home/compustar/htdocs/ProductosHora.csv`   |
| `--rows a-b`       | Subconjunto de filas (sin contar encabezado).                        | *(vacío → full)*                             |
| `--dry-run 0|1`    | Propaga modo dry-run a Stage 10.                                     | `0`                                          |
| `--wp-path <dir>`  | Ruta a WordPress.                                                    | `/home/compustar/htdocs`                     |
| `--require-term 0|1` | Exigir categoría mapeada en Stage 04.                             | `1`                                          |

Ejemplo rápido de ayuda:
```bash
scripts/run_compustar_import.sh --help
```

### 3.2 Variables de entorno

Si existe `scripts/.env`, se carga automáticamente antes de parsear argumentos. Plantilla en `scripts/.env.example`:

| Variable        | Uso                                                                 |
|-----------------|---------------------------------------------------------------------|
| `WP`, `REPO`, `WP_CLI`, `PLUG` | Rutas base para WordPress, repo y plugin.             |
| `DRY_RUN`, `REQUIRE_TERM`, `ROWS`, `SOURCE` | Valores por defecto para la ejecución. |
| `RUNS_TO_KEEP` | Número de runs a conservar (rotación simple).                        |

Variables exportadas durante el run (disponibles para todos los Stages):

- `RUN_DIR` / `RUN_PATH`
- `RUN_ID`
- `CSV_SRC`, `SOURCE_MASTER`, `CSV`
- `FORCE_CSV=1`
- `DRY_RUN`, `REQUIRE_TERM`
- `LIMIT=0`
- `STAGE_DEBUG=1`
- `WP_PATH`, `WP_CLI`, `WP_PATH_ARGS="--no-color"`, `WP_TABLE_PREFIX`

## 4. Ejecución manual

### 4.1 Ejecución completa
```bash
cd /home/compustar/compustar-project
scripts/run_compustar_import.sh --source /home/compustar/htdocs/ProductosHora.csv
```

### 4.2 Subset rápido (filas 1000-1050)
```bash
cd /home/compustar/compustar-project
scripts/run_compustar_import.sh --rows 1000-1050
```

El script crea un RUN en `wp-content/uploads/compu-import/run-<epoch>` con subdirectorios `logs/` y `final/`. Cada stage escribe un log dedicado (`<stage-label>.log`) y la salida consolidada se encuentra en `logs/master.log`.

## 5. Resultados esperados

Artefactos obligatorios (el script aborta si alguno falta):

- `source.csv`
- `normalized.jsonl`
- `validated.jsonl`
- `resolved.jsonl`
- `media.jsonl`
- `final/import-report.json`
- `final/postcheck.json`

Artefactos adicionales generados por etapas intermedias: `final/unmapped.csv`, `final/invalid_term_ids.csv`, `final/stage04-metrics.json`, reportes de media y cualquier archivo creado por stages opcionales 08/09.

## 6. Logs y monitoreo

- Master log: `$RUN_DIR/logs/master.log`
- Logs por stage: `$RUN_DIR/logs/<stage>.log` (por ejemplo `01-fetch.log`, `07-media.log`)
- Logs especiales: Stage 07 escribe `stage-07.log`, Stage 10 y 11 generan sus propios logs definidos por CLI.

Para ver las últimas líneas de un stage:
```bash
tail -n 100 "$RUN_DIR/logs/stage-07.log"
```

Para ver el resumen consolidado:
```bash
tail -n 200 "$RUN_DIR/logs/master.log"
```

## 7. Troubleshooting

| Síntoma                              | Acción recomendada |
|-------------------------------------|---------------------|
| Falta `media.jsonl` tras Stage 07   | Verificar que `RUN_DIR` y `RUN_PATH` estén exportadas; revisar `$RUN_DIR/logs/07-media.log` para errores de WooCommerce. |
| Stage 04 falla con `term_not_found` | Confirmar que la vista `wp_compu_cats_map` exista y contenga datos; validar la tabla `compu_cats_map`. |
| `--wp-args` no aplicado             | Usar sintaxis con `=`: `--wp-args="--no-color"`. |
| WP-CLI no encuentra instalación     | Ajustar `--wp-path` o `WP` en `.env`. |
| Pre-flight falla por espacio        | Liberar espacio en `wp-content/uploads/compu-import`. |

Consultas útiles con `jq`:
```bash
# Contar registros resueltos con categoría asignada
jq -c '. | select(.resolution == "mapped")' "$RUN_DIR/resolved.jsonl" | wc -l

# Listar filas sin categoría mapeada
jq -r 'select(.resolution != "mapped") | .sku' "$RUN_DIR/resolved.jsonl"
```

### Stage 07 (media)

- **Ruta efectiva:** el orquestador invoca `wp eval-file` con `wp-content/plugins/compu-import-lego/includes/stages/07-media.php`. Las copias históricas bajo `server-mirror/compu-import-lego/compu-import-lego/...` son únicamente referencia y no participan en la ejecución.
- **Entradas:**
  - `resolved.jsonl` (principal).
  - `validated.jsonl` (fallback; se registra `WARN` en el log si se usa).
- **Salidas:**
  - `media.jsonl` en el `RUN_DIR` actual.
  - Log detallado en `$RUN_DIR/logs/stage-07.log` y mensajes de progreso por STDOUT.
- **Variables de entorno:** `RUN_DIR` (preferido) y `RUN_PATH` (respaldo si el primero está vacío).
- **Estructura de `media.jsonl`:** una línea JSON por SKU con las llaves `sku`, `image_url`, `gallery_urls` (array), `image_status` (`ok`, `missing`, `invalid_url`, `http_error`, `timeout`), `source` (`url`/`file`) y `notes` opcional. No hay encabezados ni arrays envolventes.
- **Validaciones clave:**
  - Verifica que el `RUN_DIR` exista y sea escribible; si no, aborta con código 1.
  - Falla explícitamente si no encuentra archivo de entrada o no puede crear/escribir `media.jsonl`.
  - Evalúa URLs con `wp_remote_head()` y fallback `wp_remote_get()` (timeout 8 s, máximo 3 redirecciones, cuerpo limitado a 32 KB) para clasificar el estado de cada imagen.
  - Registra progreso cada 20 registros y resume totales al finalizar (`ok`, `missing`, `errors`).

Ejemplos útiles:

```bash
# Resumen rápido de estado de imágenes
jq -r '[(.sku//"-"), (.image_status//"-"), (.image_url//"-")] | @tsv' \
  "$RUN_DIR/media.jsonl" | column -t -s $'\t' | head

# Detectar timeouts/errores HTTP
jq -r 'select(.image_status != "ok" and .image_status != "missing") | {sku, image_status, notes}' \
  "$RUN_DIR/media.jsonl"

# Contar líneas generadas por Stage 07
wc -l "$RUN_DIR/media.jsonl"
```

- **Fallos habituales:**
  - `RUN_DIR`/`RUN_PATH` no exportados → el stage aborta inmediatamente con `[07] RUN_DIR/RUN_PATH inválido`.
  - Permisos insuficientes en `$RUN_DIR` → se registra `[07] RUN_DIR no es escribible` y termina en error.
  - Faltan `resolved.jsonl` y `validated.jsonl` → salida `[07] No existe resolved.jsonl ni validated.jsonl`.
  - Errores de red persistentes → revisar `stage-07.log` para identificar timeouts o códigos HTTP.

## 8. Cron y automatización

### 8.1 Cron diario
Agregar al crontab del usuario `compustar` (`crontab -e`):
```cron
# Compustar — Import diario 02:15 AM
15 2 * * * /home/compustar/compustar-project/scripts/run_compustar_import.sh --source /home/compustar/htdocs/ProductosHora.csv >> /var/log/compustar/import-$(date +\%Y\%m\%d).log 2>&1
```
El script ya gestiona `flock`, por lo que no es necesario envolver la ejecución.

### 8.2 Systemd timer (opcional)
Para entornos donde se prefiera systemd, se incluyen unidades de referencia en `systemd/compustar-import.service` y `systemd/compustar-import.timer`. Copiar a `/etc/systemd/system/`, ajustar rutas y habilitar manualmente (`systemctl enable --now compustar-import.timer`).

## 9. Retención y housekeeping

`RUNS_TO_KEEP` controla el número máximo de runs en `wp-content/uploads/compu-import`. El valor por defecto es `10`. El script elimina automáticamente los runs más antiguos tras cada ejecución exitosa, preservando siempre el actual.

## 10. Concurrencia y seguridad

- Se utiliza `flock` sobre `/var/lock/compu-import.lock` para evitar ejecuciones simultáneas.
- Validaciones estrictas tras cada stage; el script retorna código distinto de cero si faltan artefactos obligatorios.
- Pre-flight confirma comandos, espacio libre y estado de la base de datos (`wp db check`).

## 11. Smoke test

El script `tests/smoke_import.sh` permite validar rápidamente la configuración local usando un subconjunto de filas (`1000-1050`). Tras completarse, muestra un resumen de artefactos y un extracto de `import-report.json`.

```bash
cd /home/compustar/compustar-project
tests/smoke_import.sh
```

## 12. Contacto y mantenimiento

- Mantener actualizada la tabla `compu_cats_map` antes de correr Stage 04.
- Revisar periódicamente los logs de Stage 10 y 11 para detectar banderas (`flags`) y diferencias (`diffs`).
- Ante cambios en rutas o comandos, actualizar `scripts/.env` y la documentación correspondiente.
