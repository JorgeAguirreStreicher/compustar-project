# Compustar Project 🚀

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
Repositorio central para el proyecto **Compustar**, integraciones y scripts relacionados con WooCommerce + Syscom.

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
## 📂 Estructura

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
- `data/` → Archivos de datos (CSV, SQL, logs)
- `scripts/` → Scripts en Python o PHP
- `docs/` → Documentación técnica
- `backups/` → Copias de seguridad

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
## 🚀 Flujo de trabajo

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
1. Subir archivos crudos (CSV, SQL) a `data/imports/`
2. Procesarlos con los scripts de `scripts/`
3. Guardar resultados en `data/exports/`
4. Documentar cambios relevantes en `docs/`

### Diccionario canónico LEGO

- Las etapas 02 ➜ 09 trabajan ahora con el diccionario canónico (`cost_usd`, `exchange_rate`, `stock_total`, etc.).
- Consulta `docs/schema_contract.md` para el detalle de claves y el esquema actualizado de `wp_compu_offers`.

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
## 🔒 Notas
- Este repo está pensado para usarse con GitHub y AICodex.
- Recuerda mantener fuera de Git cualquier credencial sensible (`.env` ya está en .gitignore).

## Stage 01–02 (repo)

### Requisitos
- PHP 8+ con extensiones estándar (json, iconv o intl).
- Python 3.8+ y `jq` para las verificaciones.

### Comandos de ejemplo
```bash
bash tests/run_stage01_02.sh \
  --csv data/ProductosHora_subset_1000_5000.csv \
  --run_dir tests/tmp/run-$$

make stage01_02
```

### Checks obligatorios
- `source.csv` se crea en el RUN_DIR, mantiene el encabezado original y tiene más de una fila.
- `normalized.jsonl` y `normalized.csv` existen, comparten las mismas columnas normalizadas y cada objeto/registro incluye `SKU` (copiado desde `Modelo`).
- Los nombres de columna se normalizan quitando acentos y reemplazando espacios por guiones bajos.
- Los conteos de filas entre `source.csv`, `normalized.jsonl` y `normalized.csv` son consistentes.

### Contrato de nombres normalizados
- Regla de nombres: los campos normalizados de Stage 02 son contrato para Stage 03..11. Nunca se renombran; sólo se agregan campos nuevos.

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->
> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->

> Roles/RACI/DoD: ver documento “Equipo y responsabilidades” y la sección de operación del diario.
<!-- END:CCX_REPOS_SUMMARY -->

## Stage 04–06 (repo)

### Requisitos
- PHP 8+ con soporte para JSON y ext/mbstring.
- `jq` y Python 3.8+ para verificaciones adicionales.

### Comandos de ejemplo
```bash
bash tests/run_stage04_06.sh \
  --csv /home/compustar/htdocs/ProductosHora.csv \
  --from=1 --rows=500 \
  --run-dir=tests/tmp/run-$(date +%s)

# Verificar consistencia de llaves
bash tests/check_schema_consistency.sh \
  --run_dir=tests/tmp/run-<id>
```

### Artefactos esperados
- `resolved.jsonl` con las mismas llaves que `validated.jsonl` más campos derivados (`cat_lvl*_id`, `stock_total`, `resolve_status`, `resolve_reason`).
- `final/import-ready.csv`, `final/skipped.csv` y `final/summary.json` en el `RUN_DIR`.
- Directorios de documentación en `docs/runs/<RUN_ID>/step-04/` y `docs/runs/<RUN_ID>/step-06/` con los CSV/JSON y logs correspondientes.

### Checks obligatorios
- `tests/check_schema_consistency.sh --run_dir=<RUN_DIR>` debe terminar con código 0.
- Los CSV finales deben incluir encabezados en español normalizado y respetar el orden definido.
- Los logs `logs/stage-04.log` y `logs/stage-06.log` deben existir y contener el resumen de cada etapa.
- `final/summary.json` debe listar totales, importables, omitidos y razones de omisión.

### Notas
- El mapeo de menús se resuelve con `config/menu-map.json`. Agrega nuevas combinaciones ahí para habilitar categorías adicionales.
- Stage 06 aplica reglas de negocio básicas: SKU y Título obligatorios, precio o stock presentes y categorías resueltas.
- Los CSV finales pueden consumirse directamente por WooCommerce o revisarse manualmente antes de importar.

## Stage 10–11 (writer=wp)

### Requisitos

- WordPress productivo accesible en `/home/compustar/htdocs` con WP-CLI operativo.
- Variables de entorno `WP_PATH` y `WP_PATH_ARGS` configuradas (por defecto `wp` y `--path=/home/compustar/htdocs --no-color`).
- Salida de Stage 09 consolidada en `media.jsonl` (200 registros tomados a partir de la fila 1000 para ejercicios completos).

### Ejecución recomendada

```bash
RUN_DIR="/tmp/run-$(date +%s)"
mkdir -p "$RUN_DIR"/logs "$RUN_DIR"/final
cp /ruta/a/media.jsonl "$RUN_DIR"/

python3 python/stage10_import.py \
  --run-dir "$RUN_DIR" \
  --input "$RUN_DIR/media.jsonl" \
  --log "$RUN_DIR/logs/stage-10.log" \
  --report "$RUN_DIR/final/import-report.json" \
  --dry-run 0 \
  --writer wp \
  --wp-path "${WP_PATH:-wp}" \
  --wp-args "${WP_PATH_ARGS:---path=/home/compustar/htdocs --no-color}"

python3 python/stage11_postcheck.py \
  --run-dir "$RUN_DIR" \
  --import-report "$RUN_DIR/final/import-report.json" \
  --postcheck "$RUN_DIR/final/postcheck.json" \
  --log "$RUN_DIR/logs/stage-11.log" \
  --dry-run 0 \
  --writer wp \
  --wp-path "${WP_PATH:-wp}" \
  --wp-args "${WP_PATH_ARGS:---path=/home/compustar/htdocs --no-color}"
```

### Guardas y reglas clave

- No se crean productos nuevos si `stock_total_mayoristas == 0` o si `price_16_final == 0`.
- Si el precio objetivo es 0 y el producto ya existe, se fuerza `stock=0` y estado `outofstock` sin subir precio.
- Los precios sólo se actualizan si el nuevo valor no incrementa el precio ya publicado.
- Se asegura categoría (ID_Menu_Nvl_3), marca (`product_brand`) e imagen destacada si faltan.
- `_weight` se sincroniza desde `Peso_Kg` y `_stock` siempre refleja la suma total de mayoristas.
- Cuando existen tablas espejo `wp_compu_*`, se realiza un UPSERT con `run_id` y se reporta si alguna quedó parcial.

### Artefactos y auditoría

- `logs/stage-10.log`: detalle por SKU (acción, reutilización de términos, guardas aplicadas, estado de mirrors).
- `final/import-report.json`: lista por SKU con `action`, `reason` (cuando aplica) y `flags` (`category_assigned`, `brand_assigned`, `price_set`, `mirror_written`, etc.).
- `logs/stage-11.log`: resumen de la muestra auditada (20 SKUs) y diferencias detectadas.
- `final/postcheck.json`: `{ mode: "wp", writer: "wp", diffs: <n>, checks: { ... } }` con resultados agregados.

Revisar manualmente una muestra en WooCommerce (ID, categoría, marca, imagen y metas `_price`, `_stock`, `_weight`) para confirmar que Stage 11 cierre sin diferencias (`diffs: 0`).

#### Campos enriquecidos y guardias recientes
- Stage 02 agrega `Nombre`, `Stock_Suma_Sin_Tijuana` y `Stock_Suma_Total` cuando `ST2_ENRICH_NAME_STOCK=1` (por defecto) para facilitar el traspaso de inventarios a stages posteriores.
- Stage 04 propaga `margin_pct` (o `margin_default=true` con 0.15) bajo `ST4_ENRICH_MARGIN=1`, sincronizando `resolved.jsonl` y `validated.jsonl`.
- Stage 09 calcula `price_mxn_iva16_rounded`, `price_mxn_iva8_rounded` y `price_invalid` con `ST9_ENRICH_PRICES=1`, incluyendo la función de redondeo 0/5/9 hacia abajo.
- Stage 10 impide publicar precios en cero o marcados como inválidos con `ST10_GUARD_PRICE_ZERO=1`, registrando `skipped_price_zero` en la respuesta.

Consulta `docs/runs/stage-feature-flags.md` para detalles de activación/desactivación por entorno.
