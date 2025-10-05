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
