# Compustar Project 🚀

Repositorio central para el proyecto **Compustar**, integraciones y scripts relacionados con WooCommerce + Syscom.

## 📂 Estructura

- `data/` → Archivos de datos (CSV, SQL, logs)
- `scripts/` → Scripts en Python o PHP
- `docs/` → Documentación técnica
- `backups/` → Copias de seguridad

## 🚀 Flujo de trabajo

1. Subir archivos crudos (CSV, SQL) a `data/imports/`
2. Procesarlos con los scripts de `scripts/`
3. Guardar resultados en `data/exports/`
4. Documentar cambios relevantes en `docs/`

## 🔒 Notas
- Este repo está pensado para usarse con GitHub y AICodex.
- Recuerda mantener fuera de Git cualquier credencial sensible (`.env` ya está en .gitignore).
