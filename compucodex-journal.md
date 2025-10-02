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
