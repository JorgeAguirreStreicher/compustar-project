# Compu Import (LEGO)
Importador modular por etapas para Syscom → WooCommerce.
## Instalación
1. Copia `compu-import-lego` a `/home/compustar/htdocs/wp-content/plugins/`.
2. Activa el plugin. (O muévelo a `mu-plugins`).
3. Asegúrate de tener WP-CLI.
## Comandos
wp compu import run --file=/home/compustar/ProductosHora.csv --from=normalize --to=offers --allow-root
wp compu import publish --run-id=last --allow-root
wp compu import report  --run-id=last --allow-root
