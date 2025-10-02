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

## 2025-10-02 02:50:30 UTC — RUN_ID=1759363194

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759363194
- DRY_RUN: <nil>    LIMIT: <nil>
- Source: <nil>
- Final: imported=14488  updated=53  skipped=13996

### Notas
Nuevo canvas: retomando desde /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759363194. Launcher=/home/compustar/launch.sh, SOURCE_MASTER=/home/compustar/ProductosHora.csv.

## 2025-10-02 03:12:59 UTC — RUN_ID=1759374779

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759374779
- DRY_RUN: 1    LIMIT: 0
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
**DRY_RUN que sí deja CSVs + ENV mínimo para pruebas**

1) DRY_RUN=1 por defecto NO escribe final/*.csv en 05–11. Para ver imported/updated/skipped en pruebas:
   - FORCE_CSV=1
   - (opcional) PREVIEW_ONLY=1

2) ENV mínimo recomendado (pruebas rápidas):
   WP=/home/compustar/htdocs
   SOURCE_MASTER=/home/compustar/ProductosHora.csv
   DRY_RUN=1 REQUIRE_TERM=1 LIMIT=0
   SUBSET_FROM=1 SUBSET_ROWS=2000
   FORCE_CSV=1 PREVIEW_ONLY=1
   STAGES="02 03 04 05 06"
   WP_CLI_PHP_ARGS="-d display_errors=1 -d error_reporting=22527"

   Lanzar: /home/compustar/launch.sh

3) Corrida “real”:
   DRY_RUN=0 SUBSET_ROWS=0 STAGES="02 03 04 05 06"
   /home/compustar/launch.sh

4) Ubicar resultados:
   RUNS="$WP/wp-content/uploads/compu-import"
   RUN_DIR=$(ls -1dt "$RUNS"/run-* | head -n1)
   Artefactos: normalized.jsonl, validated.jsonl, resolved.jsonl
   Finales:   final/{imported.csv,updated.csv,skipped.csv}

5) Notas de launcher v3.5:
   - Symlink: /home/compustar/launch.sh → /home/compustar/compu-cron-full_v3.5.sh
   - WP-CLI con --skip-themes --skip-plugins (parchado)
   - RUNPREFIX evita sudo si ya somos compustar

## 2025-10-02 04:52:00 UTC — RUN_ID=1759379899

- WP: /home/compustar/htdocs
- RUN_DIR: /home/compustar/htdocs/wp-content/uploads/compu-import/run-1759379899
- DRY_RUN: 1    LIMIT: 0
- Source: <nil>
- Final: imported=0  updated=0  skipped=0

### Notas
Ping: alias 'note' y script ccx-journal.sh operativos.

### SNAPSHOT · STAGES LIST
(Directorio de stages y permisos)
```text
total 1580
drwxrwxr-x 4 compustar compustar 12288 Oct  2 04:33 .
drwxrwxr-x 4 compustar compustar  4096 Oct  2 04:33 ..
-rw-rw-r-- 1 compustar compustar   621 Oct  2 04:33 01-fetch.php
-rw-rw-r-- 1 compustar compustar   621 Sep 20 22:37 01-fetch.php.bak
-rw-rw-r-- 1 compustar compustar 23754 Oct  2 04:33 02-normalize.php
-rw-rw-r-- 1 compustar compustar 18509 Sep 20 22:37 02-normalize.php.bak
-rw-rw-r-- 1 compustar compustar 17563 Sep 20 13:07 02-normalize.php.bak.2025-09-20_130744
-rw-rw-r-- 1 compustar compustar 17563 Sep 20 13:34 02-normalize.php.bak.2025-09-20_133415
-rw-rw-r-- 1 compustar compustar 18509 Sep 24 03:18 02-normalize.php.BAK.2025-09-24-031812
-rw-rw-r-- 1 compustar compustar 19950 Sep 24 03:28 02-normalize.php.BAK.2025-09-24-032808
-rw-rw-r-- 1 compustar compustar 18509 Sep 27 22:29 02-normalize.php.bak.2025-09-27-223421
-rw-rw-r-- 1 compustar compustar 19062 Sep 27 22:34 02-normalize.php.bak.2025-09-27-234121
-rw-rw-r-- 1 compustar compustar 19582 Sep 30 04:05 02-normalize.php.BAK.2025-09-30-040528
-rw-rw-r-- 1 compustar compustar 19582 Sep 30 04:18 02-normalize.php.BAK.2025-09-30-041836
-rw-rw-r-- 1 compustar compustar 20133 Sep 30 04:38 02-normalize.php.BAK.2025-09-30-063858
-rw-rw-r-- 1 compustar compustar 21852 Sep 30 20:58 02-normalize.php.BAK.2025-09-30-205826
-rw-rw-r-- 1 compustar compustar 21852 Sep 30 21:01 02-normalize.php.BAK.2025-09-30-210125
-rw-rw-r-- 1 compustar compustar 21852 Sep 30 21:35 02-normalize.php.BAK.2025-09-30-213527
-rw-rw-r-- 1 compustar compustar 21852 Sep 30 21:46 02-normalize.php.BAK.2025-09-30-214609
-rw-rw-r-- 1 compustar compustar 22318 Sep 30 21:56 02-normalize.php.BAK.2025-09-30-215657
-rw-rw-r-- 1 compustar compustar 22794 Sep 30 21:59 02-normalize.php.BAK.2025-09-30-215957
-rw-rw-r-- 1 compustar compustar 22794 Sep 30 22:02 02-normalize.php.BAK.2025-09-30-220205
-rw-rw-r-- 1 compustar compustar 22794 Sep 30 22:08 02-normalize.php.BAK.2025-09-30-220853
-rw-rw-r-- 1 compustar compustar 23311 Sep 30 22:24 02-normalize.php.BAK.2025-09-30-222445
-rw-rw-r-- 1 compustar compustar 23754 Sep 30 23:51 02-normalize.php.BAK.2025-09-30-235147
-rw-rw-r-- 1 compustar compustar 23742 Sep 30 23:55 02-normalize.php.BAK.2025-09-30-235504
-rw-rw-r-- 1 compustar compustar  1321 Oct  2 04:33 03-validate.php
-rw-rw-r-- 1 compustar compustar  1321 Sep 20 22:37 03-validate.php.bak
-rw-rw-r-- 1 compustar compustar  4722 Oct  2 04:33 04-resolve-map.php
-rw-rw-r-- 1 compustar compustar  4312 Sep 20 22:37 04-resolve-map.php.bak
-rw-rw-r-- 1 compustar compustar  4312 Sep 30 20:58 04-resolve-map.php.BAK.2025-09-30-205826
-rw-rw-r-- 1 compustar compustar  4312 Sep 30 21:01 04-resolve-map.php.BAK.2025-09-30-210125
-rw-rw-r-- 1 compustar compustar  4312 Sep 30 21:21 04-resolve-map.php.BAK.2025-09-30-212123
-rw-rw-r-- 1 compustar compustar  4312 Sep 30 22:26 04-resolve-map.php.BAK.2025-09-30-222614
-rw-rw-r-- 1 compustar compustar  4132 Oct  2 04:33 05-terms.php
-rw-rw-r-- 1 compustar compustar 20646 Sep 20 22:37 05-terms.php.bak
-rw-rw-r-- 1 compustar compustar  4088 Sep 21 01:23 05-terms.php.bak.2025-09-23_200425
-rw-rw-r-- 1 compustar compustar  8552 Oct  2 04:33 06-products.php
-rw-rw-r-- 1 compustar compustar 11550 Sep 21 00:00 06-products.php.bak
-rw-rw-r-- 1 compustar compustar 11574 Sep 20 22:37 06-products.php.bak.20250921_000033
-rw-rw-r-- 1 compustar compustar 11996 Sep 21 20:51 06-products.php.BAK.2025-09-21_205246
-rw-rw-r-- 1 compustar compustar 11996 Sep 21 20:51 06-products.php.BAK.2025-09-21_210502
-rw-rw-r-- 1 compustar compustar 11996 Sep 21 20:51 06-products.php.BAK.2025-09-21_210952
-rw-rw-r-- 1 compustar compustar  9459 Sep 21 21:19 06-products.php.BAK.2025-09-21_225645
-rw-rw-r-- 1 compustar compustar 12138 Sep 21 23:05 06-products.php.BAK.2025-09-21_233106
-rw-rw-r-- 1 compustar compustar  4301 Sep 22 00:04 06-products.php.BAK.2025-09-22_001855
-rw-rw-r-- 1 compustar compustar  6041 Sep 22 01:14 06-products.php.BAK.2025-09-22_012110
-rw-rw-r-- 1 compustar compustar  4626 Sep 30 20:58 06-products.php.BAK.2025-09-30-205826
-rw-rw-r-- 1 compustar compustar  4626 Sep 30 21:01 06-products.php.BAK.2025-09-30-210125
-rw-rw-r-- 1 compustar compustar  4626 Sep 30 21:21 06-products.php.BAK.2025-09-30-212123
-rw-rw-r-- 1 compustar compustar  4626 Sep 30 22:25 06-products.php.BAK.2025-09-30-222521
-rw-rw-r-- 1 compustar compustar  4626 Oct  1 02:15 06-products.php.BAK.2025-10-01-021514
-rw-rw-r-- 1 compustar compustar  5695 Oct  1 02:21 06-products.php.BAK.2025-10-01-022119
-rw-rw-r-- 1 compustar compustar  5695 Oct  1 02:25 06-products.php.BAK.2025-10-01-022548
-rw-rw-r-- 1 compustar compustar  5695 Oct  1 02:51 06-products.php.BAK.2025-10-01-025122
-rw-rw-r-- 1 compustar compustar  5695 Oct  1 03:55 06-products.php.BAK.2025-10-01-035515
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:09 06-products.php.BAK.2025-10-01-180948
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:20 06-products.php.BAK.2025-10-01-182051
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:24 06-products.php.BAK.2025-10-01-182408
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:27 06-products.php.BAK.2025-10-01-182744
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:30 06-products.php.BAK.2025-10-01-183022
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:32 06-products.php.BAK.2025-10-01-183237
-rw-rw-r-- 1 compustar compustar  8327 Oct  1 18:39 06-products.php.BAK.2025-10-01-183919
-rw-rw-r-- 1 compustar compustar 11574 Sep 17 22:17 06-products.php.OFF
-rw-rw-r-- 1 compustar compustar  8517 Oct  2 04:25 06-products.php.orig
-rw-rw-r-- 1 compustar compustar 12037 Sep 21 21:09 06-products.php.patched.1
-rw-rw-r-- 1 compustar compustar   146 Oct  2 04:25 06-products.php.rej
-rw-rw-r-- 1 compustar compustar  3121 Oct  2 04:33 07-media.php
-rw-rw-r-- 1 compustar compustar  2397 Sep 21 00:00 07-media.php.bak
-rw-rw-r-- 1 compustar compustar  2421 Sep 20 22:25 07-media.php.bak.20250920_222916
-rw-rw-r-- 1 compustar compustar  2397 Sep 20 22:37 07-media.php.bak.20250921_000033
-rw-rw-r-- 1 compustar compustar  2351 Sep 20 22:25 07-media.php.bak.20250921_002507
-rw-rw-r-- 1 compustar compustar  3077 Sep 22 03:04 07-media.php.bak.2025-09-23_201032
-rw-rw-r-- 1 compustar compustar  3121 Sep 23 20:10 07-media.php.bak.2025-09-23_205112
-rw-rw-r-- 1 compustar compustar  3121 Sep 23 20:51 07-media.php.bak.2025-09-24_004823
-rw-rw-r-- 1 compustar compustar  5754 Oct  2 04:33 08-offers.php
-rw-rw-r-- 1 compustar compustar  5850 Sep 21 00:00 08-offers.php.bak
-rw-rw-r-- 1 compustar compustar  5874 Sep 20 22:25 08-offers.php.bak.20250920_222916
-rw-rw-r-- 1 compustar compustar  5850 Sep 20 22:37 08-offers.php.bak.20250921_000033
-rw-rw-r-- 1 compustar compustar  5803 Sep 20 22:25 08-offers.php.bak.20250921_002507
-rw-rw-r-- 1 compustar compustar  2892 Sep 22 03:05 08-offers.php.bak.2025-09-23_201032
-rw-rw-r-- 1 compustar compustar  2936 Sep 23 20:10 08-offers.php.bak.2025-09-23_202949
-rw-rw-r-- 1 compustar compustar  2936 Sep 23 20:10 08-offers.php.bak.2025-09-23_205112
-rw-rw-r-- 1 compustar compustar  3086 Sep 23 21:14 08-offers.php.bak.2025-09-23_212219
-rw-rw-r-- 1 compustar compustar  4450 Sep 23 21:22 08-offers.php.bak.2025-09-23_215305
-rw-rw-r-- 1 compustar compustar  4570 Sep 23 21:53 08-offers.php.bak.2025-09-23_215758
-rw-rw-r-- 1 compustar compustar  4761 Sep 23 21:57 08-offers.php.bak.2025-09-24_002454
-rw-rw-r-- 1 compustar compustar  5754 Sep 24 00:24 08-offers.php.bak.2025-09-24_004823
-rw-rw-r-- 1 compustar compustar  2966 Oct  2 04:33 09-pricing.php
-rw-rw-r-- 1 compustar compustar  5539 Sep 21 00:00 09-pricing.php.bak
-rw-rw-r-- 1 compustar compustar  5563 Sep 20 22:25 09-pricing.php.bak.20250920_222916
-rw-rw-r-- 1 compustar compustar  5539 Sep 20 22:37 09-pricing.php.bak.20250921_000034
-rw-rw-r-- 1 compustar compustar  5491 Sep 20 22:25 09-pricing.php.bak.20250921_002507
-rw-rw-r-- 1 compustar compustar  2870 Sep 22 03:05 09-pricing.php.bak.2025-09-23_201032
-rw-rw-r-- 1 compustar compustar  2914 Sep 23 20:10 09-pricing.php.bak.2025-09-23_202949
-rw-rw-r-- 1 compustar compustar  2914 Sep 23 20:10 09-pricing.php.bak.2025-09-23_205112
-rw-rw-r-- 1 compustar compustar  2966 Sep 23 21:13 09-pricing.php.bak.2025-09-24_004823
-rw-rw-r-- 1 compustar compustar  1123 Oct  2 04:33 10-publish.php
-rw-rw-r-- 1 compustar compustar  3315 Sep 21 00:00 10-publish.php.bak
-rw-rw-r-- 1 compustar compustar  3339 Sep 20 22:20 10-publish.php.bak.20250920_222916
-rw-rw-r-- 1 compustar compustar  3315 Sep 20 22:37 10-publish.php.bak.20250921_000034
-rw-rw-r-- 1 compustar compustar  3267 Sep 20 22:20 10-publish.php.bak.20250921_002023
-rw-rw-r-- 1 compustar compustar  1079 Sep 22 03:06 10-publish.php.bak.2025-09-23_201032
-rw-rw-r-- 1 compustar compustar  1123 Sep 23 20:10 10-publish.php.bak.2025-09-23_205112
-rw-rw-r-- 1 compustar compustar  1123 Sep 23 20:51 10-publish.php.bak.2025-09-24_004823
-rw-rw-r-- 1 compustar compustar  1432 Oct  2 04:33 11-report.php
-rw-rw-r-- 1 compustar compustar  6002 Sep 21 00:00 11-report.php.bak
-rw-rw-r-- 1 compustar compustar  6026 Sep 20 22:20 11-report.php.bak.20250920_222916
-rw-rw-r-- 1 compustar compustar  6002 Sep 20 22:37 11-report.php.bak.20250921_000034
-rw-rw-r-- 1 compustar compustar  5955 Sep 20 22:20 11-report.php.bak.20250921_002023
-rw-rw-r-- 1 compustar compustar  1388 Sep 22 03:07 11-report.php.bak.2025-09-23_201032
-rw-rw-r-- 1 compustar compustar  1432 Sep 23 20:10 11-report.php.bak.2025-09-23_205112
-rw-rw-r-- 1 compustar compustar  1432 Sep 23 20:51 11-report.php.bak.2025-09-24_004823
-rw-rw-r-- 1 compustar compustar  1751 Sep 24 01:21 11-report.php.bak.2025-09-24_013551
-rw-rw-r-- 1 compustar compustar  1905 Sep 24 01:35 11-report.php.bak.2025-09-24_014405
drwxrwsr-x 2 compustar compustar  4096 Sep 29 19:49 final
drwxrwxr-x 2 compustar compustar  4096 Sep 30 04:56 logs

```

### SNAPSHOT · STAGES GREP (patrones validate/products/s06/writers)
```text
06-products.php.BAK.2025-10-01-183237:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-183237:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-183237:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-183237:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-183237:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-183237:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-183237:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-183237:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-183237:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-183237:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
10-publish.php.bak.2025-09-24_004823:7:if (!function_exists('wc_get_products')) { fwrite(STDERR,"[10] WooCommerce no cargado\n"); return; }
10-publish.php.bak.2025-09-24_004823:15:$ids = wc_get_products($args);
10-publish.php.bak.2025-09-24_004823:18:  $p=wc_get_product($pid); if(!$p)continue;
06-products.php.BAK.2025-09-30-222521:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-09-30-222521:28:function s06_log($msg) {
06-products.php.BAK.2025-09-30-222521:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-09-30-222521:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-30-222521:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-09-30-222521:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-09-30-222521:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-09-30-222521:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-09-30-222521:143:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
07-media.php.bak.2025-09-24_004823:31:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
07-media.php.bak.2025-09-24_004823:33:if (empty($rows)) { SLOG07("No hay datos en resolved/validated"); return; }
07-media.php.bak.2025-09-24_004823:35:if (!function_exists('wc_get_product_id_by_sku')) { SLOG07("WooCommerce no cargado; abortando stage 07"); return; }
07-media.php.bak.2025-09-24_004823:51:  $pid = wc_get_product_id_by_sku($sku);
07-media.php.bak.2025-09-24_004823:52:  if(!$pid) { fputcsv($er,[$sku,$url,"product_not_found"]); $fail++; continue; }
09-pricing.php.bak.2025-09-23_202949:15:if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }
09-pricing.php.bak.2025-09-23_202949:45:  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
09-pricing.php.bak.2025-09-23_202949:46:  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}
08-offers.php.bak.2025-09-24_002454:24:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-24_002454:46:  // intentamos mapear al product_id por SKU de Woo (si existe)
08-offers.php.bak.2025-09-24_002454:47:  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
08-offers.php.bak.2025-09-24_002454:49:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-24_002454:51:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-24_002454:60:  // - product_id   := si se pudo resolver
08-offers.php.bak.2025-09-24_002454:65:  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);
08-offers.php.bak.2025-09-24_002454:71:            || (($pid !== null) && (intval($row['product_id']) !== $pid));
08-offers.php.bak.2025-09-24_002454:81:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
08-offers.php.bak.2025-09-24_002454:104:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
06-products.php.BAK.2025-10-01-182408:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-182408:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-182408:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-182408:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-182408:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-182408:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-182408:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-182408:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-182408:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-182408:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_225645:3: * Stage 06 — Products (blindado / minimal)
06-products.php.BAK.2025-09-21_225645:4: * - Lee RUN_DIR/resolved.jsonl (o validated.jsonl si el primero no existe)
06-products.php.BAK.2025-09-21_225645:5: * - Crea/actualiza productos WooCommerce (conservador: título, excerpt, contenido, SKU y tipo)
06-products.php.BAK.2025-09-21_225645:11: *  - Nunca imprime JSON de “05-terms”. La única salida final es un JSON de “06-products-minimal”.
06-products.php.BAK.2025-09-21_225645:65:function s06_log($msg) {
06-products.php.BAK.2025-09-21_225645:71:s06_log("RUN_ID={$GLOBALS['RUN_ID']} RUN_DIR={$GLOBALS['RUN_DIR']} DRY_RUN={$GLOBALS['DRY']} LIMIT=".($GLOBALS['LIMIT']??'none')." OFFSET={$GLOBALS['OFFSET']} REQUIRE_TERM={$GLOBALS['REQUIRE_TERM']}");
06-products.php.BAK.2025-09-21_225645:75:$inValidated = $RUN_DIR.'/validated.jsonl';
06-products.php.BAK.2025-09-21_225645:76:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.BAK.2025-09-21_225645:78:    s06_log("ERROR: no existe resolved.jsonl ni validated.jsonl en $RUN_DIR");
06-products.php.BAK.2025-09-21_225645:86:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-21_225645:92:function s06_first(array $row, array $keys, $default=null) {
06-products.php.BAK.2025-09-21_225645:98:function s06_clip($txt, $len=300) {
06-products.php.BAK.2025-09-21_225645:102:function s06_build_name(array $row): string {
06-products.php.BAK.2025-09-21_225645:104:    $b = trim((string)s06_first($row,['brand','marca'],''));
06-products.php.BAK.2025-09-21_225645:105:    $m = trim((string)s06_first($row,['model','modelo'],''));
06-products.php.BAK.2025-09-21_225645:106:    $t = trim((string)s06_first($row,['title','titulo','Título'],''));
06-products.php.BAK.2025-09-21_225645:110:function s06_find_pid_by_sku(string $sku): ?int {
06-products.php.BAK.2025-09-21_225645:117:function s06_upsert_compu_products(string $sku, array $row, int $pid, int $DRY): void {
06-products.php.BAK.2025-09-21_225645:119:    $table = $wpdb->prefix.'compu_products';
06-products.php.BAK.2025-09-21_225645:124:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_225645:125:    $short = s06_clip(s06_first($row,['short_description','short','resumen','excerpt'],$name));
06-products.php.BAK.2025-09-21_225645:126:    $long  = (string) s06_first($row,['description','Descripción','descripcion'],'');
06-products.php.BAK.2025-09-21_225645:127:    $images= (string) s06_first($row,['image','Imagen Principal','img','imagen'],'');
06-products.php.BAK.2025-09-21_225645:148:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.BAK.2025-09-21_225645:149:    $sku = trim((string)s06_first($row,['sku','SKU','Sku','SkuID','clave'],''));
06-products.php.BAK.2025-09-21_225645:152:    $pidExisting = s06_find_pid_by_sku($sku);
06-products.php.BAK.2025-09-21_225645:153:    $termId = (int) s06_first($row, ['term_id','termId','woo_term_id','category_id'], 0);
06-products.php.BAK.2025-09-21_225645:159:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_225645:160:    $short = s06_clip(s06_first($row,['short_description','short','resumen','excerpt'],$name));
06-products.php.BAK.2025-09-21_225645:161:    $long  = (string) s06_first($row,['description','Descripción','descripcion'],'');
06-products.php.BAK.2025-09-21_225645:173:        'post_type'=>'product','post_status'=>'draft',
06-products.php.BAK.2025-09-21_225645:179:    wp_set_object_terms($pid,'simple','product_type');
06-products.php.BAK.2025-09-21_225645:188:if (!$fh) { s06_log("ERROR: no pude abrir $inFile"); exit(5); }
06-products.php.BAK.2025-09-21_225645:205:    $sku = trim((string)s06_first($row,['sku','SKU','Sku','SkuID','clave'],''));
06-products.php.BAK.2025-09-21_225645:208:    $out = ['sku'=>$sku,'product_id'=>'','action'=>'','reason'=>''];
06-products.php.BAK.2025-09-21_225645:210:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $out);
06-products.php.BAK.2025-09-21_225645:213:    s06_upsert_compu_products($sku,$row,(int)$pid,$DRY);
06-products.php.BAK.2025-09-21_225645:215:    $out['product_id'] = (string)$pid;
06-products.php.BAK.2025-09-21_225645:229:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_225645:232:    'stage'     => '06-products-minimal',
06-products.php.BAK.2025-10-01-021514:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-021514:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-021514:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-021514:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-021514:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-021514:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-021514:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-021514:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-021514:143:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
09-pricing.php.bak.2025-09-24_004823:15:if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }
09-pricing.php.bak.2025-09-24_004823:45:  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
09-pricing.php.bak.2025-09-24_004823:46:  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}
07-media.php.bak.2025-09-23_205112:31:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
07-media.php.bak.2025-09-23_205112:33:if (empty($rows)) { SLOG07("No hay datos en resolved/validated"); return; }
07-media.php.bak.2025-09-23_205112:35:if (!function_exists('wc_get_product_id_by_sku')) { SLOG07("WooCommerce no cargado; abortando stage 07"); return; }
07-media.php.bak.2025-09-23_205112:51:  $pid = wc_get_product_id_by_sku($sku);
07-media.php.bak.2025-09-23_205112:52:  if(!$pid) { fputcsv($er,[$sku,$url,"product_not_found"]); $fail++; continue; }
08-offers.php.bak:18:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
08-offers.php.bak:43:      // Mapeo: si viene "unmapped" o sin lvl3_id, podemos saltar (cuando el archivo es validated.jsonl puede no traer mapeo)
08-offers.php.bak:48:      if (function_exists('wc_get_product_id_by_sku')) { $post_id = intval(wc_get_product_id_by_sku($sku)); }
08-offers.php.bak:49:      if (!$post_id && function_exists('compu_import_find_product_id_by_sku')) { $post_id = intval(compu_import_find_product_id_by_sku($sku)); }
08-offers.php.bak:53:      if (!$post_id) { $skipped_mode++; continue; } // por simplicidad, solo upsert a productos existentes
06-products.php.patched.1:3: * Stage 06 — Products (minimal scope)
06-products.php.patched.1:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.patched.1:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.patched.1:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.patched.1:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.patched.1:24: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.patched.1:28: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.patched.1:88:function s06_log(string $msg) {
06-products.php.patched.1:95:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.patched.1:99:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.patched.1:100:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.patched.1:102:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.patched.1:120:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.patched.1:126:function s06_first(array $row, array $keys, $default=null) {
06-products.php.patched.1:135:function s06_clip(string $text, int $len=300): string {
06-products.php.patched.1:142:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.patched.1:143:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.patched.1:145:    $table = $wpdb->prefix . 'compu_products';
06-products.php.patched.1:146:    $name  = s06_build_name($row);
06-products.php.patched.1:147:    $short = s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.patched.1:148:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.patched.1:149:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.patched.1:153:        'wp_post_id'        => $product_id,
06-products.php.patched.1:161:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.patched.1:174:function s06_build_name(array $row): string {
06-products.php.patched.1:176:    $brand = trim((string) s06_first($row, ['brand','marca'], ''));
06-products.php.patched.1:177:    $model = trim((string) s06_first($row, ['model','modelo'], ''));
06-products.php.patched.1:178:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.patched.1:185:/** Find product by SKU */
06-products.php.patched.1:186:function s06_find_product_id_by_sku(string $sku): ?int {
06-products.php.patched.1:192:/** Create or update product shell */
06-products.php.patched.1:193:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.patched.1:194:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave']));
06-products.php.patched.1:198:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.patched.1:201:    $termId = (int) s06_first($row, ['term_id','termId','woo_term_id','category_id'], 0);
06-products.php.patched.1:207:    $name = s06_build_name($row);
06-products.php.patched.1:208:    $short= s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], $name));
06-products.php.patched.1:209:    $long = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.patched.1:218:        // Minimal product updates
06-products.php.patched.1:237:        'post_type'   => 'product',
06-products.php.patched.1:249:    // Set SKU & basic product type
06-products.php.patched.1:251:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.patched.1:260:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.patched.1:273:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave'], ''));
06-products.php.patched.1:276:    $out = ['sku'=>$sku, 'product_id'=>'', 'action'=>'', 'reason'=>''];
06-products.php.patched.1:278:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $out);
06-products.php.patched.1:285:    // Write to compu_products (minimal fields)
06-products.php.patched.1:286:    s06_upsert_compu_product($sku, $row, (int)$pid, $DRY);
06-products.php.patched.1:289:    $out['product_id'] = (string) $pid;
06-products.php.patched.1:319:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.patched.1:323:    'stage'     => '06-products-minimal',
04-resolve-map.php.BAK.2025-09-30-210125:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php.BAK.2025-09-30-210125:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
06-products.php.BAK.2025-09-30-212123:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-09-30-212123:28:function s06_log($msg) {
06-products.php.BAK.2025-09-30-212123:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-09-30-212123:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-30-212123:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-09-30-212123:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-09-30-212123:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-09-30-212123:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-09-30-212123:143:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
08-offers.php:37:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php:59:  // intentamos mapear al product_id por SKU de Woo (si existe)
08-offers.php:60:  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
08-offers.php:62:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php:63:  if($product_id<=0){ $err++; $act="error"; SLOG08("NOPROD sku=".$sku); fputcsv($out,[$sku,0,$supplier,15,"MAIN",0,0,$currency,$act]); continue; }
08-offers.php:65:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php:67:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php:76:  // - product_id   := si se pudo resolver
08-offers.php:81:  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);
08-offers.php:87:            || (($pid !== null) && (intval($row['product_id']) !== $pid));
08-offers.php:97:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
08-offers.php:120:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
09-pricing.php.bak.20250921_000034:6: * - Selecciona la mejor oferta (precio más bajo con stock) desde wp_compu_offers por producto.
09-pricing.php.bak.20250921_000034:24:    if ($onlyExisting && !function_exists('wc_get_product_id_by_sku')) {
09-pricing.php.bak.20250921_000034:25:      \WP_CLI::error('WooCommerce no disponible (wc_get_product_id_by_sku).');
09-pricing.php.bak.20250921_000034:31:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
09-pricing.php.bak.20250921_000034:32:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
09-pricing.php.bak.20250921_000034:47:      $pid = wc_get_product_id_by_sku($sku);
04-resolve-map.php.bak:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php.bak:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
10-publish.php.bak.20250920_222916:5: * Objetivo: Publicar productos ya preparados (mapeados, con precio y stock aplicados en stages previos).
10-publish.php.bak.20250920_222916:25:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
10-publish.php.bak.20250920_222916:26:    if (!file_exists($src)) { \WP_CLI::error('Falta resolved/validated.jsonl'); }
10-publish.php.bak.20250920_222916:41:      $pid = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;
10-publish.php.bak.20250920_222916:67:        if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($pid); }
10-publish.php.bak.20250920_222916:68:        if (function_exists('wc_update_product_lookup_tables')) { wc_update_product_lookup_tables($pid); }
10-publish.php.bak.20250920_222916:71:        compu_import_log($run_id,'publish','info','Producto publicado/visible', ['post_id'=>$pid,'sku'=>$sku]);
04-resolve-map.php.BAK.2025-09-30-212123:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php.BAK.2025-09-30-212123:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
06-products.php.BAK.2025-10-01-182744:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-182744:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-182744:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-182744:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-182744:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-182744:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-182744:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-182744:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-182744:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-182744:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
11-report.php.bak.20250921_000034:7: * No modifica productos ni tablas de ofertas/precios.
10-publish.php.bak.20250921_002023:4: * Objetivo: Publicar productos ya preparados (mapeados, con precio y stock aplicados en stages previos).
10-publish.php.bak.20250921_002023:23:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
10-publish.php.bak.20250921_002023:24:    if (!file_exists($src)) { \WP_CLI::error('Falta resolved/validated.jsonl'); }
10-publish.php.bak.20250921_002023:39:      $pid = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;
10-publish.php.bak.20250921_002023:65:        if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($pid); }
10-publish.php.bak.20250921_002023:66:        if (function_exists('wc_update_product_lookup_tables')) { wc_update_product_lookup_tables($pid); }
10-publish.php.bak.20250921_002023:69:        compu_import_log($run_id,'publish','info','Producto publicado/visible', ['post_id'=>$pid,'sku'=>$sku]);
08-offers.php.bak.2025-09-23_215305:23:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_215305:44:  // intentamos mapear al product_id por SKU de Woo (si existe)
08-offers.php.bak.2025-09-23_215305:45:  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
08-offers.php.bak.2025-09-23_215305:54:  // - product_id   := si se pudo resolver
08-offers.php.bak.2025-09-23_215305:59:  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);
08-offers.php.bak.2025-09-23_215305:65:            || (($pid !== null) && (intval($row['product_id']) !== $pid));
08-offers.php.bak.2025-09-23_215305:75:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
08-offers.php.bak.2025-09-23_215305:98:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
06-products.php:4: * Stage 06 - products (stable writer)
06-products.php:29:function s06_log($msg) {
06-products.php:55:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php:58:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php:64:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php:76:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php:80:  s06_log("FATAL input no legible: $inJsonl");
06-products.php:87:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php:197:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php:234:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
07-media.php.bak:14:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
07-media.php.bak:15:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
07-media.php.bak:25:      $pid = wc_get_product_id_by_sku($sku);
07-media.php.bak.2025-09-23_201032:30:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
07-media.php.bak.2025-09-23_201032:32:if (empty($rows)) { SLOG07("No hay datos en resolved/validated"); return; }
07-media.php.bak.2025-09-23_201032:34:if (!function_exists('wc_get_product_id_by_sku')) { SLOG07("WooCommerce no cargado; abortando stage 07"); return; }
07-media.php.bak.2025-09-23_201032:50:  $pid = wc_get_product_id_by_sku($sku);
07-media.php.bak.2025-09-23_201032:51:  if(!$pid) { fputcsv($er,[$sku,$url,"product_not_found"]); $fail++; continue; }
05-terms.php:33:    $in_validated = $run_dir . '/validated.jsonl';
05-terms.php:36:    $input_file = file_exists($in_resolved) ? $in_resolved : (file_exists($in_validated) ? $in_validated : '');
05-terms.php:38:      error_log("[05-terms] ERROR: No existe ni resolved.jsonl ni validated.jsonl en {$run_dir}");
05-terms.php:102:        // Sin mapeo: 06 lo marcará como skipped (no_term_mapping_for_new_product)
11-report.php.bak.20250921_002023:6: * No modifica productos ni tablas de ofertas/precios.
09-pricing.php.bak.20250920_222916:6: * - Selecciona la mejor oferta (precio más bajo con stock) desde wp_compu_offers por producto.
09-pricing.php.bak.20250920_222916:24:    if ($onlyExisting && !function_exists('wc_get_product_id_by_sku')) {
09-pricing.php.bak.20250920_222916:25:      \WP_CLI::error('WooCommerce no disponible (wc_get_product_id_by_sku).');
09-pricing.php.bak.20250920_222916:31:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
09-pricing.php.bak.20250920_222916:32:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
09-pricing.php.bak.20250920_222916:47:      $pid = wc_get_product_id_by_sku($sku);
08-offers.php.bak.2025-09-24_004823:37:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-24_004823:59:  // intentamos mapear al product_id por SKU de Woo (si existe)
08-offers.php.bak.2025-09-24_004823:60:  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
08-offers.php.bak.2025-09-24_004823:62:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-24_004823:63:  if($product_id<=0){ $err++; $act="error"; SLOG08("NOPROD sku=".$sku); fputcsv($out,[$sku,0,$supplier,15,"MAIN",0,0,$currency,$act]); continue; }
08-offers.php.bak.2025-09-24_004823:65:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-24_004823:67:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-24_004823:76:  // - product_id   := si se pudo resolver
08-offers.php.bak.2025-09-24_004823:81:  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);
08-offers.php.bak.2025-09-24_004823:87:            || (($pid !== null) && (intval($row['product_id']) !== $pid));
08-offers.php.bak.2025-09-24_004823:97:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
08-offers.php.bak.2025-09-24_004823:120:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
06-products.php.rej:1:--- includes/stages/06-products.php
06-products.php.rej:2:+++ includes/stages/06-products.php
05-terms.php.bak:46:        $brand_tax = 'product_brand';
05-terms.php.bak:181:        $titulo = $this->val($data, ['Título','Titulo','title','product_title']);
05-terms.php.bak:322:                $tt = get_term($ids['l3'], 'product_cat');
05-terms.php.bak:325:                        $t2 = get_term($tt->parent, 'product_cat');
05-terms.php.bak:329:                                $t1 = get_term($t2->parent, 'product_cat');
05-terms.php.bak:342:            $t3 = get_term_by('name', $l3_name, 'product_cat');
05-terms.php.bak:347:                    $t2 = get_term($t3->parent, 'product_cat');
05-terms.php.bak:351:                            $t1 = get_term($t2->parent, 'product_cat');
07-media.php.bak.20250921_000033:14:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
07-media.php.bak.20250921_000033:15:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
07-media.php.bak.20250921_000033:25:      $pid = wc_get_product_id_by_sku($sku);
10-publish.php.bak.20250921_000034:5: * Objetivo: Publicar productos ya preparados (mapeados, con precio y stock aplicados en stages previos).
10-publish.php.bak.20250921_000034:25:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
10-publish.php.bak.20250921_000034:26:    if (!file_exists($src)) { \WP_CLI::error('Falta resolved/validated.jsonl'); }
10-publish.php.bak.20250921_000034:41:      $pid = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;
10-publish.php.bak.20250921_000034:67:        if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($pid); }
10-publish.php.bak.20250921_000034:68:        if (function_exists('wc_update_product_lookup_tables')) { wc_update_product_lookup_tables($pid); }
10-publish.php.bak.20250921_000034:71:        compu_import_log($run_id,'publish','info','Producto publicado/visible', ['post_id'=>$pid,'sku'=>$sku]);
08-offers.php.bak.20250921_000033:18:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
08-offers.php.bak.20250921_000033:43:      // Mapeo: si viene "unmapped" o sin lvl3_id, podemos saltar (cuando el archivo es validated.jsonl puede no traer mapeo)
08-offers.php.bak.20250921_000033:48:      if (function_exists('wc_get_product_id_by_sku')) { $post_id = intval(wc_get_product_id_by_sku($sku)); }
08-offers.php.bak.20250921_000033:49:      if (!$post_id && function_exists('compu_import_find_product_id_by_sku')) { $post_id = intval(compu_import_find_product_id_by_sku($sku)); }
08-offers.php.bak.20250921_000033:53:      if (!$post_id) { $skipped_mode++; continue; } // por simplicidad, solo upsert a productos existentes
10-publish.php.bak:5: * Objetivo: Publicar productos ya preparados (mapeados, con precio y stock aplicados en stages previos).
10-publish.php.bak:25:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
10-publish.php.bak:26:    if (!file_exists($src)) { \WP_CLI::error('Falta resolved/validated.jsonl'); }
10-publish.php.bak:41:      $pid = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($sku) : 0;
10-publish.php.bak:67:        if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients($pid); }
10-publish.php.bak:68:        if (function_exists('wc_update_product_lookup_tables')) { wc_update_product_lookup_tables($pid); }
10-publish.php.bak:71:        compu_import_log($run_id,'publish','info','Producto publicado/visible', ['post_id'=>$pid,'sku'=>$sku]);
08-offers.php.bak.2025-09-23_212219:17:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_212219:28:  $pid = (function_exists('wc_get_product_id_by_sku')?wc_get_product_id_by_sku($sku):0); if(!$pid){/*permitimos upsert sin PID*/}
06-products.php.BAK.2025-09-21_210952:3: * Stage 06 — Products (minimal scope)
06-products.php.BAK.2025-09-21_210952:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.BAK.2025-09-21_210952:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.BAK.2025-09-21_210952:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.BAK.2025-09-21_210952:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.BAK.2025-09-21_210952:24: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.BAK.2025-09-21_210952:28: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.BAK.2025-09-21_210952:88:function s06_log(string $msg) {
06-products.php.BAK.2025-09-21_210952:95:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.BAK.2025-09-21_210952:99:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.BAK.2025-09-21_210952:100:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.BAK.2025-09-21_210952:102:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.BAK.2025-09-21_210952:120:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-21_210952:126:function s06_first(array $row, array $keys, $default=null) {
06-products.php.BAK.2025-09-21_210952:135:function s06_clip(string $text, int $len=300): string {
06-products.php.BAK.2025-09-21_210952:142:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.BAK.2025-09-21_210952:143:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.BAK.2025-09-21_210952:145:    $table = $wpdb->prefix . 'compu_products';
06-products.php.BAK.2025-09-21_210952:146:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_210952:147:    $short = s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.BAK.2025-09-21_210952:148:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_210952:149:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.BAK.2025-09-21_210952:153:        'wp_post_id'        => $product_id,
06-products.php.BAK.2025-09-21_210952:161:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.BAK.2025-09-21_210952:174:function s06_build_name(array $row): string {
06-products.php.BAK.2025-09-21_210952:176:    $brand = trim((string) s06_first($row, ['brand','marca'], ''));
06-products.php.BAK.2025-09-21_210952:177:    $model = trim((string) s06_first($row, ['model','modelo'], ''));
06-products.php.BAK.2025-09-21_210952:178:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.BAK.2025-09-21_210952:185:/** Find product by SKU */
06-products.php.BAK.2025-09-21_210952:186:function s06_find_product_id_by_sku(string $sku): ?int {
06-products.php.BAK.2025-09-21_210952:192:/** Create or update product shell */
06-products.php.BAK.2025-09-21_210952:193:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.BAK.2025-09-21_210952:194:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave']));
06-products.php.BAK.2025-09-21_210952:198:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.BAK.2025-09-21_210952:201:    $termId = (int) s06_first($row, ['term_id','termId','woo_term_id','category_id'], 0);
06-products.php.BAK.2025-09-21_210952:207:    $name = s06_build_name($row);
06-products.php.BAK.2025-09-21_210952:208:    $short= s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], $name));
06-products.php.BAK.2025-09-21_210952:209:    $long = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_210952:218:        // Minimal product updates
06-products.php.BAK.2025-09-21_210952:237:        'post_type'   => 'product',
06-products.php.BAK.2025-09-21_210952:249:    // Set SKU & basic product type
06-products.php.BAK.2025-09-21_210952:251:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.BAK.2025-09-21_210952:260:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.BAK.2025-09-21_210952:273:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave'], ''));
06-products.php.BAK.2025-09-21_210952:276:    $out = ['sku'=>$sku, 'product_id'=>'', 'action'=>'', 'reason'=>''];
06-products.php.BAK.2025-09-21_210952:278:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $out);
06-products.php.BAK.2025-09-21_210952:285:    // Write to compu_products (minimal fields)
06-products.php.BAK.2025-09-21_210952:286:    s06_upsert_compu_product($sku, $row, (int)$pid, $DRY);
06-products.php.BAK.2025-09-21_210952:289:    $out['product_id'] = (string) $pid;
06-products.php.BAK.2025-09-21_210952:319:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_210952:323:    'stage'     => '06-products-minimal',
06-products.php.BAK.2025-10-01-025122:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-025122:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-025122:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-025122:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-025122:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-025122:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-025122:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-025122:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-025122:126:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-025122:163:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
08-offers.php.bak.2025-09-23_215758:23:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_215758:44:  // intentamos mapear al product_id por SKU de Woo (si existe)
08-offers.php.bak.2025-09-23_215758:45:  $pid = (function_exists('wc_get_product_id_by_sku')? wc_get_product_id_by_sku($sku) : 0);
08-offers.php.bak.2025-09-23_215758:47:  $product_id = intval($pid ?: 0); $supplier_sku = $sku;
08-offers.php.bak.2025-09-23_215758:56:  // - product_id   := si se pudo resolver
08-offers.php.bak.2025-09-23_215758:61:  $row = $wpdb->get_row($wpdb->prepare("SELECT id, price_cost, currency, stock, product_id FROM $table WHERE supplier_sku=%s",$sku), ARRAY_A);
08-offers.php.bak.2025-09-23_215758:67:            || (($pid !== null) && (intval($row['product_id']) !== $pid));
08-offers.php.bak.2025-09-23_215758:77:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
08-offers.php.bak.2025-09-23_215758:100:    if ($pid !== null) { $data['product_id'] = $pid; $fmt[] = '%d'; }
06-products.php.BAK.2025-09-30-210125:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-09-30-210125:28:function s06_log($msg) {
06-products.php.BAK.2025-09-30-210125:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-09-30-210125:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-30-210125:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-09-30-210125:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-09-30-210125:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-09-30-210125:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-09-30-210125:143:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
09-pricing.php.bak.20250921_002507:5: * - Selecciona la mejor oferta (precio más bajo con stock) desde wp_compu_offers por producto.
09-pricing.php.bak.20250921_002507:22:    if ($onlyExisting && !function_exists('wc_get_product_id_by_sku')) {
09-pricing.php.bak.20250921_002507:23:      \WP_CLI::error('WooCommerce no disponible (wc_get_product_id_by_sku).');
09-pricing.php.bak.20250921_002507:29:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
09-pricing.php.bak.20250921_002507:30:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
09-pricing.php.bak.20250921_002507:45:      $pid = wc_get_product_id_by_sku($sku);
08-offers.php.bak.2025-09-23_205112:17:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_205112:28:  $pid = (function_exists('wc_get_product_id_by_sku')?wc_get_product_id_by_sku($sku):0); if(!$pid){/*permitimos upsert sin PID*/}
09-pricing.php:15:if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }
09-pricing.php:45:  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
09-pricing.php:46:  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}
06-products.php.bak.20250921_000033:3: * Stage 06 — Products (minimal scope)
06-products.php.bak.20250921_000033:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.bak.20250921_000033:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.bak.20250921_000033:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.bak.20250921_000033:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.bak.20250921_000033:26: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.bak.20250921_000033:30: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.bak.20250921_000033:69:function s06_log(string $msg) {
06-products.php.bak.20250921_000033:76:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.bak.20250921_000033:80:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.bak.20250921_000033:81:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.bak.20250921_000033:83:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.bak.20250921_000033:91:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.bak.20250921_000033:97:function s06_first(array $row, array $keys, $default=null) {
06-products.php.bak.20250921_000033:106:function s06_build_name(array $row): string {
06-products.php.bak.20250921_000033:107:    $brand = trim((string) s06_first($row, ['brand','marca','Marca'], ''));
06-products.php.bak.20250921_000033:108:    $model = trim((string) s06_first($row, ['model','modelo','Modelo','sku','SKU'], ''));
06-products.php.bak.20250921_000033:109:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.bak.20250921_000033:112:    return $name !== '' ? $name : ($model ?: $title ?: 'Producto sin nombre');
06-products.php.bak.20250921_000033:115:function s06_clip(?string $text, int $len=240): string {
06-products.php.bak.20250921_000033:122:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.bak.20250921_000033:123:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.bak.20250921_000033:125:    $table = $wpdb->prefix . 'compu_products';
06-products.php.bak.20250921_000033:126:    $name  = s06_build_name($row);
06-products.php.bak.20250921_000033:127:    $short = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.bak.20250921_000033:128:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.bak.20250921_000033:129:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.bak.20250921_000033:133:        'wp_post_id'        => $product_id,
06-products.php.bak.20250921_000033:141:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.bak.20250921_000033:154:function s06_apply_inventory_and_weight(int $product_id, array $row, int $DRY): void {
06-products.php.bak.20250921_000033:155:    $stock  = (int) s06_first($row, ['stock','existencias','Existencias'], 0);
06-products.php.bak.20250921_000033:156:    $weight = s06_first($row, ['weight','peso_kg','Peso Kg'], null);
06-products.php.bak.20250921_000033:159:        update_post_meta($product_id, '_manage_stock', 'yes');
06-products.php.bak.20250921_000033:160:        update_post_meta($product_id, '_stock', max(0, $stock));
06-products.php.bak.20250921_000033:161:        update_post_meta($product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
06-products.php.bak.20250921_000033:163:            update_post_meta($product_id, '_weight', (string) $weight);
06-products.php.bak.20250921_000033:168:/** Find product by SKU */
06-products.php.bak.20250921_000033:169:function s06_find_product_id_by_sku(string $sku): int {
06-products.php.bak.20250921_000033:170:    if (function_exists('wc_get_product_id_by_sku')) {
06-products.php.bak.20250921_000033:171:        $pid = (int) wc_get_product_id_by_sku($sku);
06-products.php.bak.20250921_000033:182:/** Create or update the Woo product shell */
06-products.php.bak.20250921_000033:183:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.bak.20250921_000033:184:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.bak.20250921_000033:187:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.bak.20250921_000033:190:    $termId = s06_first($row, ['term_id','category_term_id','termId','term'], null);
06-products.php.bak.20250921_000033:192:        $out['reason'] = 'no_term_mapping_for_new_product';
06-products.php.bak.20250921_000033:196:    $name   = s06_build_name($row);
06-products.php.bak.20250921_000033:197:    $long   = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.bak.20250921_000033:198:    $short  = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen'], $long));
06-products.php.bak.20250921_000033:221:        'post_type'   => 'product',
06-products.php.bak.20250921_000033:233:    // Set SKU & basic product type
06-products.php.bak.20250921_000033:235:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.bak.20250921_000033:244:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.bak.20250921_000033:257:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.bak.20250921_000033:258:    $report = ['sku' => $sku, 'product_id' => '', 'action' => '', 'reason' => ''];
06-products.php.bak.20250921_000033:260:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $report);
06-products.php.bak.20250921_000033:264:        s06_log("SKIP sku=$sku reason=" . ($report['reason'] ?: 'unknown'));
06-products.php.bak.20250921_000033:270:        s06_apply_inventory_and_weight($pid, $row, $DRY);
06-products.php.bak.20250921_000033:273:        s06_upsert_compu_product($sku, $row, $pid, $DRY);
06-products.php.bak.20250921_000033:275:        $report['product_id'] = (string) $pid;
06-products.php.bak.20250921_000033:278:            s06_log("UPDATED sku=$sku pid=$pid");
06-products.php.bak.20250921_000033:281:            s06_log("IMPORTED sku=$sku pid=$pid");
06-products.php.bak.20250921_000033:287:        s06_log("DRY-IMPORTED (simulated) sku=$sku");
06-products.php.bak.20250921_000033:297:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.bak.20250921_000033:301:    'stage'     => '06-products-minimal',
03-validate.php.bak:4:class Compu_Stage_Validate {
03-validate.php.bak:12:    $out  = $dir . '/validated.jsonl';
03-validate.php.bak:22:        compu_import_log($run_id,'validate','error','Fila inválida: '.implode('; ', $errors), $r, isset($r['row_key'])?$r['row_key']:null);
03-validate.php.bak:28:    compu_import_log($run_id,'validate','info','Validación completa', ['ok'=>$ok,'err'=>$err]);
10-publish.php:7:if (!function_exists('wc_get_products')) { fwrite(STDERR,"[10] WooCommerce no cargado\n"); return; }
10-publish.php:15:$ids = wc_get_products($args);
10-publish.php:18:  $p=wc_get_product($pid); if(!$p)continue;
07-media.php.bak.20250921_002507:12:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
07-media.php.bak.20250921_002507:13:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
07-media.php.bak.20250921_002507:23:      $pid = wc_get_product_id_by_sku($sku);
11-report.php.bak.20250920_222916:7: * No modifica productos ni tablas de ofertas/precios.
04-resolve-map.php.BAK.2025-09-30-222614:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php.BAK.2025-09-30-222614:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
06-products.php.BAK.2025-09-21_205246:3: * Stage 06 — Products (minimal scope)
06-products.php.BAK.2025-09-21_205246:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.BAK.2025-09-21_205246:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.BAK.2025-09-21_205246:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.BAK.2025-09-21_205246:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.BAK.2025-09-21_205246:24: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.BAK.2025-09-21_205246:28: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.BAK.2025-09-21_205246:88:function s06_log(string $msg) {
06-products.php.BAK.2025-09-21_205246:95:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.BAK.2025-09-21_205246:99:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.BAK.2025-09-21_205246:100:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.BAK.2025-09-21_205246:102:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.BAK.2025-09-21_205246:120:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-21_205246:126:function s06_first(array $row, array $keys, $default=null) {
06-products.php.BAK.2025-09-21_205246:135:function s06_clip(string $text, int $len=300): string {
06-products.php.BAK.2025-09-21_205246:142:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.BAK.2025-09-21_205246:143:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.BAK.2025-09-21_205246:145:    $table = $wpdb->prefix . 'compu_products';
06-products.php.BAK.2025-09-21_205246:146:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_205246:147:    $short = s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.BAK.2025-09-21_205246:148:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_205246:149:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.BAK.2025-09-21_205246:153:        'wp_post_id'        => $product_id,
06-products.php.BAK.2025-09-21_205246:161:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.BAK.2025-09-21_205246:174:function s06_build_name(array $row): string {
06-products.php.BAK.2025-09-21_205246:176:    $brand = trim((string) s06_first($row, ['brand','marca'], ''));
06-products.php.BAK.2025-09-21_205246:177:    $model = trim((string) s06_first($row, ['model','modelo'], ''));
06-products.php.BAK.2025-09-21_205246:178:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.BAK.2025-09-21_205246:185:/** Find product by SKU */
06-products.php.BAK.2025-09-21_205246:186:function s06_find_product_id_by_sku(string $sku): ?int {
06-products.php.BAK.2025-09-21_205246:192:/** Create or update product shell */
06-products.php.BAK.2025-09-21_205246:193:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.BAK.2025-09-21_205246:194:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave']));
06-products.php.BAK.2025-09-21_205246:198:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.BAK.2025-09-21_205246:201:    $termId = (int) s06_first($row, ['term_id','termId','woo_term_id','category_id'], 0);
06-products.php.BAK.2025-09-21_205246:207:    $name = s06_build_name($row);
06-products.php.BAK.2025-09-21_205246:208:    $short= s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], $name));
06-products.php.BAK.2025-09-21_205246:209:    $long = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_205246:218:        // Minimal product updates
06-products.php.BAK.2025-09-21_205246:237:        'post_type'   => 'product',
06-products.php.BAK.2025-09-21_205246:249:    // Set SKU & basic product type
06-products.php.BAK.2025-09-21_205246:251:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.BAK.2025-09-21_205246:260:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.BAK.2025-09-21_205246:273:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave'], ''));
06-products.php.BAK.2025-09-21_205246:276:    $out = ['sku'=>$sku, 'product_id'=>'', 'action'=>'', 'reason'=>''];
06-products.php.BAK.2025-09-21_205246:278:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $out);
06-products.php.BAK.2025-09-21_205246:285:    // Write to compu_products (minimal fields)
06-products.php.BAK.2025-09-21_205246:286:    s06_upsert_compu_product($sku, $row, (int)$pid, $DRY);
06-products.php.BAK.2025-09-21_205246:289:    $out['product_id'] = (string) $pid;
06-products.php.BAK.2025-09-21_205246:319:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_205246:323:    'stage'     => '06-products-minimal',
06-products.php.orig:3: * Stage 06 - products (stable writer)
06-products.php.orig:28:function s06_log($msg) {
06-products.php.orig:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.orig:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.orig:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.orig:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.orig:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.orig:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.orig:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.orig:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-10-01-183919:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-183919:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-183919:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-183919:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-183919:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-183919:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-183919:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-183919:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-183919:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-183919:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-10-01-180948:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-180948:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-180948:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-180948:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-180948:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-180948:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-180948:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-180948:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-180948:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-180948:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
09-pricing.php.bak:6: * - Selecciona la mejor oferta (precio más bajo con stock) desde wp_compu_offers por producto.
09-pricing.php.bak:24:    if ($onlyExisting && !function_exists('wc_get_product_id_by_sku')) {
09-pricing.php.bak:25:      \WP_CLI::error('WooCommerce no disponible (wc_get_product_id_by_sku).');
09-pricing.php.bak:31:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
09-pricing.php.bak:32:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
09-pricing.php.bak:47:      $pid = wc_get_product_id_by_sku($sku);
09-pricing.php.bak.2025-09-23_201032:14:if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }
09-pricing.php.bak.2025-09-23_201032:44:  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
09-pricing.php.bak.2025-09-23_201032:45:  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}
08-offers.php.bak.2025-09-23_202949:17:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_202949:28:  $pid = (function_exists('wc_get_product_id_by_sku')?wc_get_product_id_by_sku($sku):0); if(!$pid){/*permitimos upsert sin PID*/}
06-products.php.BAK.2025-10-01-182051:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-182051:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-182051:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-182051:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-182051:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-182051:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-182051:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-182051:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-182051:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-182051:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-10-01-183022:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-183022:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-183022:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-183022:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-183022:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-183022:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-183022:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-183022:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-183022:196:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-183022:233:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
08-offers.php.bak.20250920_222916:18:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
08-offers.php.bak.20250920_222916:43:      // Mapeo: si viene "unmapped" o sin lvl3_id, podemos saltar (cuando el archivo es validated.jsonl puede no traer mapeo)
08-offers.php.bak.20250920_222916:48:      if (function_exists('wc_get_product_id_by_sku')) { $post_id = intval(wc_get_product_id_by_sku($sku)); }
08-offers.php.bak.20250920_222916:49:      if (!$post_id && function_exists('compu_import_find_product_id_by_sku')) { $post_id = intval(compu_import_find_product_id_by_sku($sku)); }
08-offers.php.bak.20250920_222916:53:      if (!$post_id) { $skipped_mode++; continue; } // por simplicidad, solo upsert a productos existentes
06-products.php.OFF:3: * Stage 06 — Products (minimal scope)
06-products.php.OFF:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.OFF:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.OFF:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.OFF:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.OFF:26: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.OFF:30: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.OFF:69:function s06_log(string $msg) {
06-products.php.OFF:76:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.OFF:80:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.OFF:81:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.OFF:83:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.OFF:91:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.OFF:97:function s06_first(array $row, array $keys, $default=null) {
06-products.php.OFF:106:function s06_build_name(array $row): string {
06-products.php.OFF:107:    $brand = trim((string) s06_first($row, ['brand','marca','Marca'], ''));
06-products.php.OFF:108:    $model = trim((string) s06_first($row, ['model','modelo','Modelo','sku','SKU'], ''));
06-products.php.OFF:109:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.OFF:112:    return $name !== '' ? $name : ($model ?: $title ?: 'Producto sin nombre');
06-products.php.OFF:115:function s06_clip(?string $text, int $len=240): string {
06-products.php.OFF:122:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.OFF:123:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.OFF:125:    $table = $wpdb->prefix . 'compu_products';
06-products.php.OFF:126:    $name  = s06_build_name($row);
06-products.php.OFF:127:    $short = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.OFF:128:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.OFF:129:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.OFF:133:        'wp_post_id'        => $product_id,
06-products.php.OFF:141:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.OFF:154:function s06_apply_inventory_and_weight(int $product_id, array $row, int $DRY): void {
06-products.php.OFF:155:    $stock  = (int) s06_first($row, ['stock','existencias','Existencias'], 0);
06-products.php.OFF:156:    $weight = s06_first($row, ['weight','peso_kg','Peso Kg'], null);
06-products.php.OFF:159:        update_post_meta($product_id, '_manage_stock', 'yes');
06-products.php.OFF:160:        update_post_meta($product_id, '_stock', max(0, $stock));
06-products.php.OFF:161:        update_post_meta($product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
06-products.php.OFF:163:            update_post_meta($product_id, '_weight', (string) $weight);
06-products.php.OFF:168:/** Find product by SKU */
06-products.php.OFF:169:function s06_find_product_id_by_sku(string $sku): int {
06-products.php.OFF:170:    if (function_exists('wc_get_product_id_by_sku')) {
06-products.php.OFF:171:        $pid = (int) wc_get_product_id_by_sku($sku);
06-products.php.OFF:182:/** Create or update the Woo product shell */
06-products.php.OFF:183:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.OFF:184:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.OFF:187:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.OFF:190:    $termId = s06_first($row, ['term_id','category_term_id','termId','term'], null);
06-products.php.OFF:192:        $out['reason'] = 'no_term_mapping_for_new_product';
06-products.php.OFF:196:    $name   = s06_build_name($row);
06-products.php.OFF:197:    $long   = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.OFF:198:    $short  = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen'], $long));
06-products.php.OFF:221:        'post_type'   => 'product',
06-products.php.OFF:233:    // Set SKU & basic product type
06-products.php.OFF:235:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.OFF:244:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.OFF:257:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.OFF:258:    $report = ['sku' => $sku, 'product_id' => '', 'action' => '', 'reason' => ''];
06-products.php.OFF:260:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $report);
06-products.php.OFF:264:        s06_log("SKIP sku=$sku reason=" . ($report['reason'] ?: 'unknown'));
06-products.php.OFF:270:        s06_apply_inventory_and_weight($pid, $row, $DRY);
06-products.php.OFF:273:        s06_upsert_compu_product($sku, $row, $pid, $DRY);
06-products.php.OFF:275:        $report['product_id'] = (string) $pid;
06-products.php.OFF:278:            s06_log("UPDATED sku=$sku pid=$pid");
06-products.php.OFF:281:            s06_log("IMPORTED sku=$sku pid=$pid");
06-products.php.OFF:287:        s06_log("DRY-IMPORTED (simulated) sku=$sku");
06-products.php.OFF:297:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.OFF:301:    'stage'     => '06-products-minimal',
logs/stage06.log:2:[2025-09-30 04:56:29] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:5:[2025-10-01 02:08:45] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:8:[2025-10-01 02:12:10] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:11:[2025-10-01 02:15:14] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:14:[2025-10-01 02:51:22] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:17:[2025-10-01 03:56:16] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:20:[2025-10-01 04:00:36] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:23:[2025-10-01 04:04:44] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:26:[2025-10-01 04:10:54] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:29:[2025-10-01 04:16:25] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:32:[2025-10-01 04:29:01] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
logs/stage06.log:35:[2025-10-01 19:11:38] stage06: OPENED_CSV final/imported.csv, updated.csv, skipped.csv
06-products.php.BAK.2025-09-21_210502:3: * Stage 06 — Products (minimal scope)
06-products.php.BAK.2025-09-21_210502:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.BAK.2025-09-21_210502:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.BAK.2025-09-21_210502:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.BAK.2025-09-21_210502:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.BAK.2025-09-21_210502:24: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.BAK.2025-09-21_210502:28: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.BAK.2025-09-21_210502:88:function s06_log(string $msg) {
06-products.php.BAK.2025-09-21_210502:95:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.BAK.2025-09-21_210502:99:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.BAK.2025-09-21_210502:100:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.BAK.2025-09-21_210502:102:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.BAK.2025-09-21_210502:120:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-21_210502:126:function s06_first(array $row, array $keys, $default=null) {
06-products.php.BAK.2025-09-21_210502:135:function s06_clip(string $text, int $len=300): string {
06-products.php.BAK.2025-09-21_210502:142:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.BAK.2025-09-21_210502:143:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.BAK.2025-09-21_210502:145:    $table = $wpdb->prefix . 'compu_products';
06-products.php.BAK.2025-09-21_210502:146:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_210502:147:    $short = s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.BAK.2025-09-21_210502:148:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_210502:149:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.BAK.2025-09-21_210502:153:        'wp_post_id'        => $product_id,
06-products.php.BAK.2025-09-21_210502:161:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.BAK.2025-09-21_210502:174:function s06_build_name(array $row): string {
06-products.php.BAK.2025-09-21_210502:176:    $brand = trim((string) s06_first($row, ['brand','marca'], ''));
06-products.php.BAK.2025-09-21_210502:177:    $model = trim((string) s06_first($row, ['model','modelo'], ''));
06-products.php.BAK.2025-09-21_210502:178:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.BAK.2025-09-21_210502:185:/** Find product by SKU */
06-products.php.BAK.2025-09-21_210502:186:function s06_find_product_id_by_sku(string $sku): ?int {
06-products.php.BAK.2025-09-21_210502:192:/** Create or update product shell */
06-products.php.BAK.2025-09-21_210502:193:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.BAK.2025-09-21_210502:194:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave']));
06-products.php.BAK.2025-09-21_210502:198:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.BAK.2025-09-21_210502:201:    $termId = (int) s06_first($row, ['term_id','termId','woo_term_id','category_id'], 0);
06-products.php.BAK.2025-09-21_210502:207:    $name = s06_build_name($row);
06-products.php.BAK.2025-09-21_210502:208:    $short= s06_clip(s06_first($row, ['short_description','short','resumen','excerpt'], $name));
06-products.php.BAK.2025-09-21_210502:209:    $long = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_210502:218:        // Minimal product updates
06-products.php.BAK.2025-09-21_210502:237:        'post_type'   => 'product',
06-products.php.BAK.2025-09-21_210502:249:    // Set SKU & basic product type
06-products.php.BAK.2025-09-21_210502:251:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.BAK.2025-09-21_210502:260:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.BAK.2025-09-21_210502:273:    $sku = trim((string) s06_first($row, ['sku','SKU','Sku','SkuID','clave'], ''));
06-products.php.BAK.2025-09-21_210502:276:    $out = ['sku'=>$sku, 'product_id'=>'', 'action'=>'', 'reason'=>''];
06-products.php.BAK.2025-09-21_210502:278:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $out);
06-products.php.BAK.2025-09-21_210502:285:    // Write to compu_products (minimal fields)
06-products.php.BAK.2025-09-21_210502:286:    s06_upsert_compu_product($sku, $row, (int)$pid, $DRY);
06-products.php.BAK.2025-09-21_210502:289:    $out['product_id'] = (string) $pid;
06-products.php.BAK.2025-09-21_210502:319:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_210502:323:    'stage'     => '06-products-minimal',
07-media.php.bak.20250920_222916:14:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
07-media.php.bak.20250920_222916:15:    if (!file_exists($src)) \WP_CLI::error('Falta resolved/validated.jsonl');
07-media.php.bak.20250920_222916:25:      $pid = wc_get_product_id_by_sku($sku);
06-products.php.BAK.2025-10-01-022119:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-022119:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-022119:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-022119:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-022119:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-022119:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-022119:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-022119:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-022119:126:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-022119:163:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-22_012110:3: * Stage 06 — Products (safe writer, web-safe)
06-products.php.BAK.2025-09-22_012110:4: * - Reads RUN_DIR/resolved.jsonl (or validated.jsonl as fallback)
06-products.php.BAK.2025-09-22_012110:25:    if (function_exists("s06_log")) {
06-products.php.BAK.2025-09-22_012110:26:      s06_log("FATAL shutdown type={$e["type"]} file={$e["file"]}:{} msg={$e["message"]}");
06-products.php.BAK.2025-09-22_012110:42:function s06_log($msg) {
06-products.php.BAK.2025-09-22_012110:58:    $cand2 = $RUN_DIR . '/validated.jsonl';
06-products.php.BAK.2025-09-22_012110:68:s06_log("START RUN_ID={$RUN_ID} RUN_DIR={$RUN_DIR} DRY_RUN={$DRY_RUN} LIMIT={$LIMIT} OFFSET={$OFFSET} INPUT={$INPUT}");
06-products.php.BAK.2025-09-22_012110:71:// Not strictly needed for the safe-writer, but harmless if present.
06-products.php.BAK.2025-09-22_012110:73:    // Derive /wp-load.php from plugin path depth: .../wp-content/plugins/.../includes/stages/06-products.php
06-products.php.BAK.2025-09-22_012110:77:        s06_log('WP bootstrap OK');
06-products.php.BAK.2025-09-22_012110:79:        s06_log("WP bootstrap SKIPPED (not readable: {$wpLoad})");
06-products.php.BAK.2025-09-22_012110:84:$csvHeader  = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-22_012110:90:    s06_log("FATAL could not open CSVs in {$FINAL_DIR}");
06-products.php.BAK.2025-09-22_012110:100:s06_log('OPENED_CSV final/imported.csv, updated.csv, skipped.csv');
06-products.php.BAK.2025-09-22_012110:104:    s06_log("FATAL input not readable: {$INPUT}");
06-products.php.BAK.2025-09-22_012110:114:    s06_log("FATAL failed fopen: {$INPUT}");
06-products.php.BAK.2025-09-22_012110:174:s06_log("DONE processed={$processed} created={$created} updated={$updated} skipped={$skipped}");
06-products.php.BAK.2025-09-22_012110:178:    'stage'     => '06-products-minimal',
06-products.php.BAK.2025-09-21_233106:3: * Stage 06 — Products (minimal scope)
06-products.php.BAK.2025-09-21_233106:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.BAK.2025-09-21_233106:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.BAK.2025-09-21_233106:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.BAK.2025-09-21_233106:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.BAK.2025-09-21_233106:26: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.BAK.2025-09-21_233106:30: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.BAK.2025-09-21_233106:75:function s06_log(string $msg) {
06-products.php.BAK.2025-09-21_233106:82:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.BAK.2025-09-21_233106:86:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.BAK.2025-09-21_233106:87:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.BAK.2025-09-21_233106:89:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.BAK.2025-09-21_233106:107:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-21_233106:113:function s06_first(array $row, array $keys, $default=null) {
06-products.php.BAK.2025-09-21_233106:122:function s06_build_name(array $row): string {
06-products.php.BAK.2025-09-21_233106:123:    $brand = trim((string) s06_first($row, ['brand','marca','Marca'], ''));
06-products.php.BAK.2025-09-21_233106:124:    $model = trim((string) s06_first($row, ['model','modelo','Modelo','sku','SKU'], ''));
06-products.php.BAK.2025-09-21_233106:125:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.BAK.2025-09-21_233106:128:    return $name !== '' ? $name : ($model ?: $title ?: 'Producto sin nombre');
06-products.php.BAK.2025-09-21_233106:131:function s06_clip(?string $text, int $len=240): string {
06-products.php.BAK.2025-09-21_233106:138:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.BAK.2025-09-21_233106:139:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.BAK.2025-09-21_233106:141:    $table = $wpdb->prefix . 'compu_products';
06-products.php.BAK.2025-09-21_233106:142:    $name  = s06_build_name($row);
06-products.php.BAK.2025-09-21_233106:143:    $short = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.BAK.2025-09-21_233106:144:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_233106:145:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.BAK.2025-09-21_233106:149:        'wp_post_id'        => $product_id,
06-products.php.BAK.2025-09-21_233106:157:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.BAK.2025-09-21_233106:170:function s06_apply_inventory_and_weight(int $product_id, array $row, int $DRY): void {
06-products.php.BAK.2025-09-21_233106:171:    $stock  = (int) s06_first($row, ['stock','existencias','Existencias'], 0);
06-products.php.BAK.2025-09-21_233106:172:    $weight = s06_first($row, ['weight','peso_kg','Peso Kg'], null);
06-products.php.BAK.2025-09-21_233106:175:        update_post_meta($product_id, '_manage_stock', 'yes');
06-products.php.BAK.2025-09-21_233106:176:        update_post_meta($product_id, '_stock', max(0, $stock));
06-products.php.BAK.2025-09-21_233106:177:        update_post_meta($product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
06-products.php.BAK.2025-09-21_233106:179:            update_post_meta($product_id, '_weight', (string) $weight);
06-products.php.BAK.2025-09-21_233106:184:/** Find product by SKU */
06-products.php.BAK.2025-09-21_233106:185:function s06_find_product_id_by_sku(string $sku): int {
06-products.php.BAK.2025-09-21_233106:186:    if (function_exists('wc_get_product_id_by_sku')) {
06-products.php.BAK.2025-09-21_233106:187:        $pid = (int) wc_get_product_id_by_sku($sku);
06-products.php.BAK.2025-09-21_233106:198:/** Create or update the Woo product shell */
06-products.php.BAK.2025-09-21_233106:199:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.BAK.2025-09-21_233106:200:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.BAK.2025-09-21_233106:203:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.BAK.2025-09-21_233106:206:    $termId = s06_first($row, ['term_id','category_term_id','termId','term'], null);
06-products.php.BAK.2025-09-21_233106:208:        $out['reason'] = 'no_term_mapping_for_new_product';
06-products.php.BAK.2025-09-21_233106:212:    $name   = s06_build_name($row);
06-products.php.BAK.2025-09-21_233106:213:    $long   = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.BAK.2025-09-21_233106:214:    $short  = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen'], $long));
06-products.php.BAK.2025-09-21_233106:237:        'post_type'   => 'product',
06-products.php.BAK.2025-09-21_233106:249:    // Set SKU & basic product type
06-products.php.BAK.2025-09-21_233106:251:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.BAK.2025-09-21_233106:260:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.BAK.2025-09-21_233106:273:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.BAK.2025-09-21_233106:274:    $report = ['sku' => $sku, 'product_id' => '', 'action' => '', 'reason' => ''];
06-products.php.BAK.2025-09-21_233106:276:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $report);
06-products.php.BAK.2025-09-21_233106:280:        s06_log("SKIP sku=$sku reason=" . ($report['reason'] ?: 'unknown'));
06-products.php.BAK.2025-09-21_233106:286:        s06_apply_inventory_and_weight($pid, $row, $DRY);
06-products.php.BAK.2025-09-21_233106:289:        s06_upsert_compu_product($sku, $row, $pid, $DRY);
06-products.php.BAK.2025-09-21_233106:291:        $report['product_id'] = (string) $pid;
06-products.php.BAK.2025-09-21_233106:294:            s06_log("UPDATED sku=$sku pid=$pid");
06-products.php.BAK.2025-09-21_233106:297:            s06_log("IMPORTED sku=$sku pid=$pid");
06-products.php.BAK.2025-09-21_233106:303:        s06_log("DRY-IMPORTED (simulated) sku=$sku");
06-products.php.BAK.2025-09-21_233106:315:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-21_233106:319:    'stage'     => '06-products-minimal',
10-publish.php.bak.2025-09-23_201032:6:if (!function_exists('wc_get_products')) { fwrite(STDERR,"[10] WooCommerce no cargado\n"); return; }
10-publish.php.bak.2025-09-23_201032:14:$ids = wc_get_products($args);
10-publish.php.bak.2025-09-23_201032:17:  $p=wc_get_product($pid); if(!$p)continue;
10-publish.php.bak.2025-09-23_205112:7:if (!function_exists('wc_get_products')) { fwrite(STDERR,"[10] WooCommerce no cargado\n"); return; }
10-publish.php.bak.2025-09-23_205112:15:$ids = wc_get_products($args);
10-publish.php.bak.2025-09-23_205112:18:  $p=wc_get_product($pid); if(!$p)continue;
09-pricing.php.bak.2025-09-23_205112:15:if (!function_exists('wc_get_product_id_by_sku')) { SLOG09("WooCommerce no cargado; abortando stage 09"); return; }
09-pricing.php.bak.2025-09-23_205112:45:  $pid=wc_get_product_id_by_sku($sku); if(!$pid){fputcsv($csve,[$sku,"product_not_found"]);$err++;continue;}
09-pricing.php.bak.2025-09-23_205112:46:  $p=wc_get_product($pid); if(!$p){fputcsv($csve,[$sku,"wc_get_product_failed"]);$err++;continue;}
03-validate.php:4:class Compu_Stage_Validate {
03-validate.php:12:    $out  = $dir . '/validated.jsonl';
03-validate.php:22:        compu_import_log($run_id,'validate','error','Fila inválida: '.implode('; ', $errors), $r, isset($r['row_key'])?$r['row_key']:null);
03-validate.php:28:    compu_import_log($run_id,'validate','info','Validación completa', ['ok'=>$ok,'err'=>$err]);
07-media.php:31:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
07-media.php:33:if (empty($rows)) { SLOG07("No hay datos en resolved/validated"); return; }
07-media.php:35:if (!function_exists('wc_get_product_id_by_sku')) { SLOG07("WooCommerce no cargado; abortando stage 07"); return; }
07-media.php:51:  $pid = wc_get_product_id_by_sku($sku);
07-media.php:52:  if(!$pid) { fputcsv($er,[$sku,$url,"product_not_found"]); $fail++; continue; }
11-report.php.bak:7: * No modifica productos ni tablas de ofertas/precios.
08-offers.php.bak.2025-09-23_201032:16:$src = file_exists("$RUN_DIR/resolved.jsonl") ? "$RUN_DIR/resolved.jsonl" : "$RUN_DIR/validated.jsonl";
08-offers.php.bak.2025-09-23_201032:27:  $pid = (function_exists('wc_get_product_id_by_sku')?wc_get_product_id_by_sku($sku):0); if(!$pid){/*permitimos upsert sin PID*/}
06-products.php.BAK.2025-10-01-035515:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-035515:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-035515:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-035515:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-035515:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-035515:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-035515:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-035515:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-035515:126:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-035515:163:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-30-205826:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-09-30-205826:28:function s06_log($msg) {
06-products.php.BAK.2025-09-30-205826:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-09-30-205826:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-30-205826:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-09-30-205826:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-09-30-205826:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-09-30-205826:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-09-30-205826:143:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.BAK.2025-09-22_001855:3: * Stage 06 - products (safe writer)
06-products.php.BAK.2025-09-22_001855:27:function s06_log($msg) {
06-products.php.BAK.2025-09-22_001855:34:    s06_log("FATAL_SHUTDOWN type={$e['type']} msg={$e['message']} file={$e['file']} line={$e['line']}");
06-products.php.BAK.2025-09-22_001855:43:s06_log("START RUN_ID=$runId RUN_DIR=$runDir DRY_RUN=$dryRun LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-09-22_001855:48:  if (is_readable($wpLoad)) { require_once $wpLoad; s06_log("WP bootstrap OK ($wpLoad)"); }
06-products.php.BAK.2025-09-22_001855:49:  else { s06_log("WP bootstrap SKIPPED (no legible: $wpLoad)"); }
06-products.php.BAK.2025-09-22_001855:53:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-09-22_001855:57:if (!$csvImported || !$csvUpdated || !$csvSkipped) { s06_log("FATAL_CSV_OPEN finalDir=$finalDir"); ob_end_clean(); exit(1); }
06-products.php.BAK.2025-09-22_001855:62:s06_log("OPENED_CSV imported,updated,skipped");
06-products.php.BAK.2025-09-22_001855:65:if (!is_readable($inJsonl)) { s06_log("FATAL_INPUT_NOT_READABLE $inJsonl"); fclose($csvImported); fclose($csvUpdated); fclose($csvSkipped); ob_end_clean(); exit(2); }
06-products.php.BAK.2025-09-22_001855:67:if (!$fh) { s06_log("FATAL_INPUT_OPEN $inJsonl"); fclose($csvImported); fclose($csvUpdated); fclose($csvSkipped); ob_end_clean(); exit(3); }
06-products.php.BAK.2025-09-22_001855:105:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");
06-products.php.bak:3: * Stage 06 — Products (minimal scope)
06-products.php.bak:6: *  - Read normalized rows from RUN_DIR/resolved.jsonl (fallback validated.jsonl)
06-products.php.bak:7: *  - Create/Update WooCommerce product shell (title, content, excerpt, SKU, slug, type)
06-products.php.bak:8: *  - Manage product-level stock (_manage_stock, _stock, _stock_status)
06-products.php.bak:10: *  - Upsert into custom table wp_compu_products (basic descriptive fields only)
06-products.php.bak:26: *  - REQUIRE_TERM (0|1) — default 1; if 1 we SKIP creating NEW products that do not have a mapped term/category
06-products.php.bak:30: *  - $RUN_DIR/final/imported.csv, updated.csv, skipped.csv
06-products.php.bak:69:function s06_log(string $msg) {
06-products.php.bak:76:s06_log("RUN_ID=$RUN_ID RUN_DIR=$RUN_DIR DRY_RUN=$DRY LIMIT=" . ($LIMIT ?? 'none') . " REQUIRE_TERM=$REQUIRE_TERM");
06-products.php.bak:80:$inValidated = $RUN_DIR . '/validated.jsonl';
06-products.php.bak:81:$inFile = file_exists($inResolved) ? $inResolved : (file_exists($inValidated) ? $inValidated : null);
06-products.php.bak:83:    s06_log('ERROR: No input file found (resolved.jsonl or validated.jsonl).');
06-products.php.bak:91:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.bak:97:function s06_first(array $row, array $keys, $default=null) {
06-products.php.bak:106:function s06_build_name(array $row): string {
06-products.php.bak:107:    $brand = trim((string) s06_first($row, ['brand','marca','Marca'], ''));
06-products.php.bak:108:    $model = trim((string) s06_first($row, ['model','modelo','Modelo','sku','SKU'], ''));
06-products.php.bak:109:    $title = trim((string) s06_first($row, ['title','titulo','Título'], ''));
06-products.php.bak:112:    return $name !== '' ? $name : ($model ?: $title ?: 'Producto sin nombre');
06-products.php.bak:115:function s06_clip(?string $text, int $len=240): string {
06-products.php.bak:122:/** Insert or update row in wp_compu_products (minimal fields) */
06-products.php.bak:123:function s06_upsert_compu_product(string $sku, array $row, int $product_id, int $DRY): void {
06-products.php.bak:125:    $table = $wpdb->prefix . 'compu_products';
06-products.php.bak:126:    $name  = s06_build_name($row);
06-products.php.bak:127:    $short = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen','excerpt'], s06_first($row, ['title','titulo','Título'], '')));
06-products.php.bak:128:    $long  = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.bak:129:    $images= (string) s06_first($row, ['image','Imagen Principal','img','imagen'], '');
06-products.php.bak:133:        'wp_post_id'        => $product_id,
06-products.php.bak:141:    if ($DRY) { s06_log("DRY: would upsert compu_products for SKU=$sku pid=$product_id"); return; }
06-products.php.bak:154:function s06_apply_inventory_and_weight(int $product_id, array $row, int $DRY): void {
06-products.php.bak:155:    $stock  = (int) s06_first($row, ['stock','existencias','Existencias'], 0);
06-products.php.bak:156:    $weight = s06_first($row, ['weight','peso_kg','Peso Kg'], null);
06-products.php.bak:159:        update_post_meta($product_id, '_manage_stock', 'yes');
06-products.php.bak:160:        update_post_meta($product_id, '_stock', max(0, $stock));
06-products.php.bak:161:        update_post_meta($product_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
06-products.php.bak:163:            update_post_meta($product_id, '_weight', (string) $weight);
06-products.php.bak:168:/** Find product by SKU */
06-products.php.bak:169:function s06_find_product_id_by_sku(string $sku): int {
06-products.php.bak:170:    if (function_exists('wc_get_product_id_by_sku')) {
06-products.php.bak:171:        $pid = (int) wc_get_product_id_by_sku($sku);
06-products.php.bak:182:/** Create or update the Woo product shell */
06-products.php.bak:183:function s06_upsert_wc_product(array $row, int $DRY, int $REQUIRE_TERM, array &$out): ?int {
06-products.php.bak:184:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.bak:187:    $pidExisting = s06_find_product_id_by_sku($sku);
06-products.php.bak:190:    $termId = s06_first($row, ['term_id','category_term_id','termId','term'], null);
06-products.php.bak:192:        $out['reason'] = 'no_term_mapping_for_new_product';
06-products.php.bak:196:    $name   = s06_build_name($row);
06-products.php.bak:197:    $long   = (string) s06_first($row, ['description','Descripción','descripcion'], '');
06-products.php.bak:198:    $short  = s06_clip(s06_first($row, ['short_description','short','Resumen','resumen'], $long));
06-products.php.bak:221:        'post_type'   => 'product',
06-products.php.bak:233:    // Set SKU & basic product type
06-products.php.bak:235:    wp_set_object_terms($pid, 'simple', 'product_type');
06-products.php.bak:244:if (!$fh) { s06_log('ERROR: cannot open input file: ' . $inFile); exit(4); }
06-products.php.bak:257:    $sku = trim((string) s06_first($row, ['sku','SKU','model','modelo','Modelo'], ''));
06-products.php.bak:258:    $report = ['sku' => $sku, 'product_id' => '', 'action' => '', 'reason' => ''];
06-products.php.bak:260:    $pid = s06_upsert_wc_product($row, $DRY, $REQUIRE_TERM, $report);
06-products.php.bak:264:        s06_log("SKIP sku=$sku reason=" . ($report['reason'] ?: 'unknown'));
06-products.php.bak:270:        s06_apply_inventory_and_weight($pid, $row, $DRY);
06-products.php.bak:273:        s06_upsert_compu_product($sku, $row, $pid, $DRY);
06-products.php.bak:275:        $report['product_id'] = (string) $pid;
06-products.php.bak:278:            s06_log("UPDATED sku=$sku pid=$pid");
06-products.php.bak:281:            s06_log("IMPORTED sku=$sku pid=$pid");
06-products.php.bak:287:        s06_log("DRY-IMPORTED (simulated) sku=$sku");
06-products.php.bak:297:s06_log("DONE processed=$processed created=$created updated=$updated skipped=$skipped");
06-products.php.bak:301:    'stage'     => '06-products-minimal',
final/skipped.csv:1:sku,product_id,action,reason
final/updated.csv:1:sku,product_id,action,reason
final/imported.csv:1:sku,product_id,action,reason
04-resolve-map.php.BAK.2025-09-30-205826:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php.BAK.2025-09-30-205826:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
05-terms.php.bak.2025-09-23_200425:32:    $in_validated = $run_dir . '/validated.jsonl';
05-terms.php.bak.2025-09-23_200425:35:    $input_file = file_exists($in_resolved) ? $in_resolved : (file_exists($in_validated) ? $in_validated : '');
05-terms.php.bak.2025-09-23_200425:37:      error_log("[05-terms] ERROR: No existe ni resolved.jsonl ni validated.jsonl en {$run_dir}");
05-terms.php.bak.2025-09-23_200425:101:        // Sin mapeo: 06 lo marcará como skipped (no_term_mapping_for_new_product)
08-offers.php.bak.20250921_002507:16:    $src  = file_exists($dir.'/resolved.jsonl') ? $dir.'/resolved.jsonl' : $dir.'/validated.jsonl';
08-offers.php.bak.20250921_002507:41:      // Mapeo: si viene "unmapped" o sin lvl3_id, podemos saltar (cuando el archivo es validated.jsonl puede no traer mapeo)
08-offers.php.bak.20250921_002507:46:      if (function_exists('wc_get_product_id_by_sku')) { $post_id = intval(wc_get_product_id_by_sku($sku)); }
08-offers.php.bak.20250921_002507:47:      if (!$post_id && function_exists('compu_import_find_product_id_by_sku')) { $post_id = intval(compu_import_find_product_id_by_sku($sku)); }
08-offers.php.bak.20250921_002507:51:      if (!$post_id) { $skipped_mode++; continue; } // por simplicidad, solo upsert a productos existentes
04-resolve-map.php:9:    $src  = $dir . '/validated.jsonl';
04-resolve-map.php:10:    if (!file_exists($src)) \WP_CLI::error('Falta validated.jsonl; corre validate.');
06-products.php.BAK.2025-10-01-022548:3: * Stage 06 - products (stable writer)
06-products.php.BAK.2025-10-01-022548:28:function s06_log($msg) {
06-products.php.BAK.2025-10-01-022548:54:s06_log("START RUN_ID=$runId RUN_DIR=$runDir LIMIT=$limit OFFSET=$offset INPUT=$inJsonl");
06-products.php.BAK.2025-10-01-022548:57:$csvHeader   = ['sku','product_id','action','reason'];
06-products.php.BAK.2025-10-01-022548:63:  s06_log("FATAL no se pudieron abrir CSVs en $finalDir");
06-products.php.BAK.2025-10-01-022548:75:s06_log("OPENED_CSV final/imported.csv, updated.csv, skipped.csv");
06-products.php.BAK.2025-10-01-022548:79:  s06_log("FATAL input no legible: $inJsonl");
06-products.php.BAK.2025-10-01-022548:86:  s06_log("FATAL no se pudo abrir $inJsonl");
06-products.php.BAK.2025-10-01-022548:126:      foreach (["title","titulo","título","nombre","producto","product name","name"] as $k) {
06-products.php.BAK.2025-10-01-022548:163:s06_log("DONE processed=$processed imported=$imported updated=$updated skipped=$skipped");

```

### SNAPSHOT · LAUNCHER MAP (eval-file / bridges relevantes)
```bash
     1  #!/usr/bin/env bash
     2  set -euo pipefail
     3  
     4  # =====================[ Vars por defecto (puedes sobreescribir por env) ]=====================
     5  WP="${WP:-/home/compustar/htdocs}"
     6  PLUG="$WP/wp-content/plugins/compu-import-lego"
     7  RUN_BASE="${RUN_BASE:-$WP/wp-content/uploads/compu-import}"
     8  WEBUSER="${WEBUSER:-compustar}"          # ← pon compustar en CloudPanel
     9  if [ "$(id -un)" = "${WEBUSER:-compustar}" ]; then
    10    RUNPREFIX=env
    11  else
    12    RUNPREFIX="sudo -u ${WEBUSER:-compustar} env"
    13  fi
    14  : "${bridge:=}"
    15  : "${file:=}"
    16  
    17  # Control general
    18  STAGES="${STAGES:-02 03 04 05 06 07 08 09 10 11}"  # iniciamos en 02 (como definiste)
    19  DRY_RUN="${DRY_RUN:-0}"
    20  LIMIT="${LIMIT:-0}"                    # 0 = sin límite (usamos el subset como control)
    21  REQUIRE_TERM="${REQUIRE_TERM:-1}"
    22  FORCE_CSV="${FORCE_CSV:-1}"
    23  PREVIEW_ONLY="${PREVIEW_ONLY:-0}"
    24  USE_BRIDGES="${USE_BRIDGES:-1}"       # 1 = usar bridges para 02–04
    25  
    26  # Fuente y subset
    27  SOURCE_MASTER="${SOURCE_MASTER:-/home/compustar/ProductosHora.csv}"
    28  SUBSET_FROM="${SUBSET_FROM:-1000}"    # primera línea de datos a tomar (tu caso: 1000)
    29  SUBSET_ROWS="${SUBSET_ROWS:-201}"     # cuántas filas tomar (tu caso: 201 -> 1000..1200)
    30  WITH_HEADER="${WITH_HEADER:-1}"       # 1 = preserva encabezado de SOURCE_MASTER
    31  
    32  # Run folder
    33  RUN_ID="${RUN_ID:-$(date +%s)}"
    34  RUN_DIR="$RUN_BASE/run-$RUN_ID"
    35  LOG_DIR="$RUN_DIR/logs"
    36  FINAL_DIR="$RUN_DIR/final"
    37  
    38  # Paths bridges (si USE_BRIDGES=1)
    39  BRIDGES_DIR="${BRIDGES_DIR:-/home/compustar/bridges}"
    40  BRIDGE_02="$BRIDGES_DIR/normalize_bridge_v2.php"
    41  BRIDGE_03="$BRIDGES_DIR/validate_bridge.php"
    42  BRIDGE_04="$BRIDGES_DIR/resolve_bridge.php"
    43  BRIDGE_06="$BRIDGES_DIR/run_stage06_bridge.php"
    44  
    45  # =====================[ Setup ]=====================
    46  mkdir -p "$LOG_DIR" "$FINAL_DIR"
    47  chown -R "$WEBUSER:$WEBUSER" "$RUN_DIR"
    48  find "$RUN_DIR" -type d -exec chmod 2775 {} \; >/dev/null 2>&1 || true
    49  find "$RUN_DIR" -type f -exec chmod 0664 {} \; >/dev/null 2>&1 || true
    50  
    51  echo "== Compustar | Cron Full v3.5 (stages: $STAGES) =="
    52  echo "RUN_ID: $RUN_ID"
    53  echo "RUN_DIR: $RUN_DIR"
    54  echo "WP: $WP"
    55  echo "WEBUSER=$WEBUSER DRY_RUN=$DRY_RUN LIMIT=$LIMIT REQUIRE_TERM=$REQUIRE_TERM"
    56  date
    57  
    58  # =====================[ CSV de trabajo (subset reproducible) ]=====================
    59  SRC_CLEAN="$RUN_DIR/source_clean.csv"
    60  if [[ ! -f "$SOURCE_MASTER" ]]; then
    61    echo "ERROR: SOURCE_MASTER no existe: $SOURCE_MASTER" >&2
    62    exit 1
    63  fi
    64  
    65  if [[ "$WITH_HEADER" == "1" ]]; then
    66    head -n 1 "$SOURCE_MASTER" > "$SRC_CLEAN"
    67    tail -n +"$SUBSET_FROM" "$SOURCE_MASTER" | head -n "$SUBSET_ROWS" >> "$SRC_CLEAN"
    68  else
    69    tail -n +"$SUBSET_FROM" "$SOURCE_MASTER" | head -n "$SUBSET_ROWS" > "$SRC_CLEAN"
    70  fi
    71  chown "$WEBUSER:$WEBUSER" "$SRC_CLEAN"
    72  echo "[info] subset => from=$SUBSET_FROM rows=$SUBSET_ROWS header=$WITH_HEADER -> $SRC_CLEAN"
    73  
    74  # Helpers
    75  php_stage () {
    76    local file="$1" name="$(basename "$file")"
    77    echo -e "\n---- $name ----"
    78    RUN_ID="$RUN_ID" RUN_DIR="$RUN_DIR" CSV_SRC="$SRC_CLEAN" DRY_RUN="$DRY_RUN" LIMIT="$LIMIT" \
    79    /usr/bin/php -d display_errors=1 -d error_reporting=E_ALL -d memory_limit=1024M "$file" \
    80    2>&1 | tee "$LOG_DIR/${name%.php}.log"
    81  }
    82  
    83  php_bridge () {
    84    local bridge="$1" name="$(basename "$bridge")"
    85    echo -e "\n---- $name ----"
    86    RUN_ID="$RUN_ID" RUN_DIR="$RUN_DIR" CSV_SRC="$SRC_CLEAN" DRY_RUN="$DRY_RUN" LIMIT="$LIMIT" \
    87    /usr/bin/php -d display_errors=1 -d error_reporting=E_ALL -d memory_limit=1024M "$bridge" \
    88    2>&1 | tee "$LOG_DIR/${name%.php}.log"
    89  }
    90  
    91  wp_eval () {
    92    local file="$1" name="$(basename "$file")"
    93    echo -e "\n---- $name ----"
    94    $RUNPREFIX RUN_ID="$RUN_ID" RUN_DIR="$RUN_DIR" CSV_SRC="$SRC_CLEAN" \
    95      DRY_RUN="$DRY_RUN" LIMIT="$LIMIT" REQUIRE_TERM="$REQUIRE_TERM" \
    96      FORCE_CSV="$FORCE_CSV" PREVIEW_ONLY="$PREVIEW_ONLY" \
    97      /usr/local/bin/wp --path="$WP" --skip-themes --skip-plugins eval-file "$file" \
    98      2>&1 | tee "$LOG_DIR/${name%.php}.log"
    99  }
   100  
   101  wp_eval_bridge () {
   102    local bridge="$1" name="$(basename "$bridge")"
   103    echo -e "\n---- $name ----"
   104    $RUNPREFIX RUN_ID="$RUN_ID" RUN_DIR="$RUN_DIR" CSV_SRC="$SRC_CLEAN" \
   105      DRY_RUN="$DRY_RUN" LIMIT="$LIMIT" REQUIRE_TERM="$REQUIRE_TERM" \
   106      FORCE_CSV="$FORCE_CSV" PREVIEW_ONLY="$PREVIEW_ONLY" \
   107      /usr/local/bin/wp --path="$WP" --skip-themes --skip-plugins eval-file "$bridge" \
   108      2>&1 | tee "$LOG_DIR/${name%.php}.log"
   109  }
   110  
   111  # =====================[ 02→04 ]=====================
   112  if [[ "$STAGES" == *"02"* ]]; then
   113    if [[ "$USE_BRIDGES" == "1" ]]; then php_bridge "$BRIDGE_02"; else php_stage "$PLUG/includes/stages/02-normalize.php"; fi
   114    ls -lh "$RUN_DIR/normalized.jsonl" "$RUN_DIR/header-map.json" 2>/dev/null || true
   115  fi
   116  
   117  if [[ "$STAGES" == *"03"* ]]; then
   118    if [[ "$USE_BRIDGES" == "1" ]]; then php_bridge "$BRIDGE_03"; else php_stage "$PLUG/includes/stages/03-validate.php"; fi
   119    ls -lh "$RUN_DIR/validated.jsonl" 2>/dev/null || true
   120  fi
   121  
   122  if [[ "$STAGES" == *"04"* ]]; then
   123    if [[ "$USE_BRIDGES" == "1" ]]; then php_bridge "$BRIDGE_04"; else php_stage "$PLUG/includes/stages/04-resolve-map.php"; fi
   124    ls -lh "$RUN_DIR/resolved.jsonl" 2>/dev/null || true
   125  fi
   126  
   127  # =====================[ 05 con WP-CLI ]=====================
   128  if [[ "$STAGES" == *"05"* ]]; then
   129    wp_eval "$PLUG/includes/stages/05-terms.php"
   130    ls -lh "$RUN_DIR/terms_resolved.jsonl" 2>/dev/null || true
   131  
   132    # Si se generó terms_resolved.jsonl, úsalo para 06+
   133    if [[ -f "$RUN_DIR/terms_resolved.jsonl" ]]; then
   134      cp -af "$RUN_DIR/terms_resolved.jsonl" "$RUN_DIR/resolved.jsonl"
   135      chown "$WEBUSER:$WEBUSER" "$RUN_DIR/resolved.jsonl"
   136      echo "[info] terms_resolved.jsonl copiado a resolved.jsonl"
   137    fi
   138  fi
   139  
   140  # =====================[ 06→11 con WP-CLI ]=====================
   141  if [[ "$STAGES" == *"06"* ]]; then
   142    # Forzamos input y escritura real
   143    export INPUT_JSONL="$RUN_DIR/resolved.jsonl"
   144    wp_eval "$PLUG/includes/stages/06-products.php"
   145  fi
   146  if [[ "$STAGES" == *"07"* ]]; then wp_eval "$PLUG/includes/stages/07-media.php"; fi
   147  if [[ "$STAGES" == *"08"* ]]; then wp_eval "$PLUG/includes/stages/08-offers.php"; fi
   148  if [[ "$STAGES" == *"09"* ]]; then wp_eval "$PLUG/includes/stages/09-pricing.php"; fi
   149  if [[ "$STAGES" == *"10"* ]]; then wp_eval "$PLUG/includes/stages/10-publish.php"; fi
   150  if [[ "$STAGES" == *"11"* ]]; then wp_eval "$PLUG/includes/stages/11-report.php"; fi
   151  
   152  # =====================[ Resumen final y árbol con timestamps ]=====================
   153  echo -e "\n--- FINAL CSVs ---"
   154  if ls "$FINAL_DIR"/*.csv >/dev/null 2>&1; then
   155    wc -l "$FINAL_DIR"/*.csv
   156    echo -e "\n--- TOP motivos skipped ---"
   157    if [[ -f "$FINAL_DIR/skipped.csv" ]]; then
   158      awk -F, 'NR>1{c[$4]++} END{for(k in c) printf "%6d %s\n", c[k], k}' "$FINAL_DIR/skipped.csv" | sort -nr | head
   159    else
   160      echo "(no hay skipped.csv)"
   161    fi
   162  else
   163    echo "(no hay CSVs en $FINAL_DIR aún)"
   164  fi
   165  
   166  echo -e "\n== Árbol con fechas =="
   167  find "$RUN_DIR" -printf '%TY-%Tm-%Td %TH:%TM:%TS\t%k KB\t%p\n' | sort
   168  
   169  echo -e "\n== FIN | RUN_ID=$RUN_ID =="
   170  export WP_CLI_PHP_ARGS="-d display_errors=1 -d error_reporting=22527"

```
### FIN SNAPSHOT
Alias note listo (usa ccx-journal.sh)
