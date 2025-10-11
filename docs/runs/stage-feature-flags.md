# Stage Feature Flags

Los siguientes flags de entorno controlan enriquecimientos agregados en el pipeline. Todos están **habilitados por defecto** (cualquier valor vacío, `1`, `true`, `yes` o `on` los activa).

| Variable | Stage | Descripción |
| --- | --- | --- |
| `ST2_ENRICH_NAME_STOCK` | Stage 02 (normalize) | Calcula `Nombre`, `Stock_Suma_Sin_Tijuana` y `Stock_Suma_Total` a partir de las columnas de inventario disponibles. |
| `ST4_ENRICH_MARGIN` | Stage 04 (resolve-map) | Adjunta `margin_pct` (y `margin_default` cuando aplica) consultando la vista `wp_compu_cats_map` o la tabla `compu_cats_map`. |
| `ST9_ENRICH_PRICES` | Stage 09 (pricing) | Genera los campos `price_mxn_iva16_rounded`, `price_mxn_iva8_rounded` y `price_invalid`, propagándolos a `resolved.jsonl` y `validated.jsonl`. |
| `ST10_GUARD_PRICE_ZERO` | Stage 10 (apply fast) | Evita publicar productos con precio final <= 0, con `price_invalid=true` o con ambos precios redondeados en cero, marcando la salida como `skipped_price_zero`. |

Deshabilita cualquier flag asignándolo a `0`, `false`, `no` u `off` en el entorno del proceso correspondiente.
