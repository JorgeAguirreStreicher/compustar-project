## 2025-10-02 02:00:07 UTC — RUN_ID=1759370407

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759370407
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
Probando nota manual de diario

## 2025-10-02 02:09:06 UTC — RUN_ID=1759370946

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759370946
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
Probando nota manual de diario

## 2025-10-02 02:18:55 UTC — RUN_ID=1759371535

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759371535
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
Tu nota aquí

## 2025-10-02 02:34:50 UTC — RUN_ID=1759372490

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759372490
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
# Contexto base del proyecto (Compustar)

## Repos y automatización
- **GitHub**: org `JorgeAguirreStreicher`, repo `compustar-project`.
  - Workflows en `.github/workflows/`: `deploy.yml`, `db-backup.yml`, `sync-to-drive.yml`.
  - Usamos PRs/commits para versionar cambios de launcher, plugin y scripts.
- **Knowledge repo local**: `/home/compustar/knowledge` (git inicializado).
  - Diario: `/home/compustar/knowledge/compucodex-journal.md` (+ hook automático desde launcher).
  - Autor recomendado del repo: `Compustar Ops (CCX) <aguirre@okdock.mx>`.

## CompuCodex (asistente)
- Soporta: revisión de código, parches Bash/PHP/WP-CLI, generación de scripts “idempotentes”, verificación sintáctica, y plantillas de journaling.
- Puede trabajar con el diario (script `ccx-journal.sh`) y con el repo de `knowledge`.

## Almacenamiento documental
- **Google Drive** disponible (workflow `sync-to-drive.yml`).
- Podemos subir artefactos (CSV finales, logs resumidos, changelogs) y snapshots del diario.

## Propósito de los stages (02→11)
- `02-normalize`: normaliza filas del CSV fuente → `normalized.jsonl`.
- `03-validate`: valida y filtra → `validated.jsonl`.
- `04-resolve-map`: aplica mapeos (headers/campos) → `resolved.jsonl`.
- `05-terms`: asegura términos/categorías en WP (vía WP-CLI).
- `06-products`: crea/actualiza productos; escribe `final/imported.csv`, `updated.csv`, `skipped.csv`.
- `07-media`, `08-offers`, `09-pricing`, `10-publish`, `11-report`: pasos posteriores de medios/ofertas/precios/publicación/reporte.

## Plugin Syscom (compu-import-lego)
- Plugin de importación que implementa los stages y bridges PHP (compatibles con `wp eval-file`).
- Ruta base: `$WP/wp-content/plugins/compu-import-lego/includes/stages/`.

## Lanzamiento unificado (cron/manuel)
- **Launcher**: `/home/compustar/compu-cron-full_v3.5.sh` (symlink: `/home/compustar/launch.sh`).
- Ejecuta 02→11, crea `RUN_DIR` y artefactos, llama a bridges con **WP-CLI** usando `--skip-themes --skip-plugins`.
- Variables típicas (export al invocar):
  - `WP=/home/compustar/htdocs`
  - `SOURCE_MASTER=/home/compustar/ProductosHora.csv`
  - `DRY_RUN=0|1`  `LIMIT=0`  `REQUIRE_TERM=1`
  - Subset opcional: `SUBSET_FROM=1`  `SUBSET_ROWS=N` (0 = copia completa)
- **WP-CLI**: `/usr/local/bin/wp --path="$WP" --skip-themes --skip-plugins eval-file ...`
  - `WP_CLI_PHP_ARGS="-d display_errors=1 -d error_reporting=22527"`
- **RUN_DIR base**: `$WP/wp-content/uploads/compu-import/run-<RUN_ID>`
  - Logs: `$RUN_DIR/logs/*.log`
  - Finales: `$RUN_DIR/final/{imported,updated,skipped}.csv`

## Cron
- Objetivo: correr el launcher desde cron (usuario `compustar`) con `DRY_RUN=0`.
- El cron debe exportar las mismas variables de entorno usadas en manual.

## Helpers
- `run_stages_0206.sh`: helper para ejecutar 02–06 fuera del launcher (depuración controlada).
- `ccx-journal.sh`: añade entradas al diario (hook post-run ya integrado en el launcher).

## Política de operación
- Siempre probar desde `launch.sh` aunque se esté depurando un stage puntual.
- Cada sesión: registrar “Objetivo → Cambios → Resultados → Decisiones/Next” en el diario.
- Ante fallos intermitentes: priorizar reproducibilidad usando el launcher + ENV fijo.

## 2025-10-02 02:42:54 UTC — RUN_ID=1759363194

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759363194
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=14488  updated=53  skipped=13996

### Notas
Launcher como entrada única; corrida full OK y journal enganchado.

## 2025-10-02 02:43:16 UTC — RUN_ID=1759363194

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759363194
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=14488  updated=53  skipped=13996

### Notas
Revisados finales; top skipped=zero_stock_all.

## 2025-10-02 02:47:25 UTC — RUN_ID=1759363194

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759363194
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=14488  updated=53  skipped=13996

### Notas
### Canvas 2025-10-02 · Resumen operativo

**Launcher unificado**
- Usar siempre: `/home/compustar/launch.sh` → symlink a `compu-cron-full_v3.5.sh`.
- Variables clave al lanzar:
  - `WP=/home/compustar/htdocs`
  - `SOURCE_MASTER=/home/compustar/ProductosHora.csv`
  - `DRY_RUN=0|1  LIMIT=0  REQUIRE_TERM=1`
  - `SUBSET_FROM=1  SUBSET_ROWS=N` (0 o total para full).
- WP-CLI forzado con `--skip-themes --skip-plugins` y
  `WP_CLI_PHP_ARGS="-d display_errors=1 -d error_reporting=22527"` para reducir “Deprecated”.

**Stages & artefactos**
- 02 normalize → `normalized.jsonl`
- 03 validate → `validated.jsonl`
- 04 resolve-map → `resolved.jsonl`
- 05 terms (WP-CLI)
- 06 products → `final/{imported,updated,skipped}.csv`
- Regla de oro: si falla 06, verificar que **exista y sea legible** `resolved.jsonl`.

**Roles/Permisos**
- Evitamos sudo directo con `RUNPREFIX`:
  - si `id -un == compustar` ⇒ `env`
  - si no ⇒ `sudo -u compustar env`
- Cuando WP-CLI se ejecuta vía sudo sin permisos, bloquea 05: corregido con `RUNPREFIX`.

**Herramientas auxiliares**
- `run_stages_0206.sh`: depuración 02→06, pero **siempre preferir** `launch.sh` para pruebas.
- `ccx-journal.sh` + alias `note`: diario en `~/knowledge/compucodex-journal.md` con commit automático.
- Git del journal configurado como:
  - `user.name = JorgeAguirreStreicher` (global)
  - repo `~/knowledge`: `user.name = Compustar Ops (CCX)`, `user.email = aguirre@okdock.mx`.

**Hallazgos clave**
- Inestabilidad venía de entornos parciales (variables no exportadas, set -u y nombres no definidos, sudo).
- Estabilizado al centralizar **siempre** el arranque desde `launch.sh` con ENV explícito.
- Corrida full OK (RUN_ID=1759363194): imported=14488, updated=53, skipped=13996 (principalmente `zero_stock_all`).

**Pendientes sugeridos**
- (Opcional) Remote para `~/knowledge` y `git push`.
- Cron real de producción llamando `launch.sh` con `DRY_RUN=0`.
- Reporte automático de TOP skipped tras cada run.

