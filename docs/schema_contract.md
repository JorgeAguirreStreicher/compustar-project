# Contrato de Esquema — Compustar

Este documento resume tablas detectadas en el dump y servirá como contrato de referencia para desarrollo.

## Tablas `wp_compu_*`

### wp_compu_brands

| Columna | Tipo |
|---|---|

| `brand_id` | `bigint` |
| `name` | `varchar(160)` |
| `slug` | `varchar(180)` |
| `ext_syscom_id` | `varchar(64)` |
| `ext_ct_id` | `varchar(64)` |
| `ext_cva_id` | `varchar(64)` |
| `ext_exel_id` | `varchar(64)` |
| `ext_icecat_id` | `varchar(64)` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |

### wp_compu_categories

| Columna | Tipo |
|---|---|

| `category_id` | `bigint` |
| `name` | `varchar(120)` |
| `slug` | `varchar(140)` |
| `icon` | `varchar(255)` |
| `sort_order` | `int` |
| `is_active` | `tinyint(1)` |
| `wc_term_id` | `bigint` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |

### wp_compu_categories_bak

| Columna | Tipo |
|---|---|

| `category_id` | `bigint` |
| `name` | `varchar(120)` |
| `slug` | `varchar(140)` |
| `icon` | `varchar(255)` |
| `sort_order` | `int` |
| `is_active` | `tinyint(1)` |
| `wc_term_id` | `bigint` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |
| `id` | `bigint` |
| `proveedor` | `varchar(50)` |
| `cat_proveedor` | `varchar(255)` |
| `subcat_proveedor` | `varchar(255)` |
| `categoria_slug` | `varchar(120)` |
| `subcategoria_slug` | `varchar(120)` |
| `id` | `bigint` |
| `term_id` | `bigint` |
| `parent_term_id` | `bigint` |
| `margin_percent` | `decimal(5` |
| `syscom_menu_ids` | `text` |
| `exclude` | `tinyint(1)` |
| `active` | `tinyint(1)` |
| `pending_mapping` | `tinyint(1)` |
| `defaulted_margin` | `tinyint(1)` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |
| `updated_by` | `bigint` |
| `log_id` | `bigint` |
| `source_id` | `int` |
| `sku` | `varchar(120)` |
| `action` | `enum(` |
| `message` | `text` |
| `run_id` | `varchar(64)` |
| `created_at` | `datetime` |
| `id` | `bigint` |
| `run_id` | `bigint` |
| `sku` | `varchar(191)` |
| `action` | `enum(` |
| `reason` | `varchar(191)` |
| `prev_price_net` | `decimal(12` |
| `new_price_net` | `decimal(12` |
| `prev_stock` | `int` |
| `new_stock` | `int` |
| `prev_weight_kg` | `decimal(10` |
| `new_weight_kg` | `decimal(10` |
| `term_id` | `bigint` |
| `syscom_menu_id` | `int` |
| `almacen_id` | `int` |
| `ts` | `datetime` |
| `id` | `bigint` |
| `provider` | `varchar(32)` |
| `run_type` | `enum(` |
| `started_at` | `datetime` |
| `finished_at` | `datetime` |
| `rows_total` | `int` |
| `created_count` | `int` |
| `updated_count` | `int` |
| `excluded_count` | `int` |
| `pending_count` | `int` |
| `errors_count` | `int` |
| `flags` | `text` |
| `started_by` | `bigint` |
| `id` | `bigint` |
| `product_id` | `bigint` |
| `supplier` | `varchar(50)` |
| `supplier_sku` | `varchar(100)` |
| `price_cost` | `decimal(18` |
| `currency` | `varchar(10)` |
| `stock` | `int` |
| `last_synced_at` | `datetime` |
| `warehouse_code` | `varchar(50)` |
| `lead_time_days` | `int` |
| `is_refurb` | `tinyint(1)` |
| `is_oem` | `tinyint(1)` |
| `is_bundle` | `tinyint(1)` |

### wp_compu_products

| Columna | Tipo |
|---|---|

| `id` | `bigint` |
| `brand` | `varchar(255)` |
| `model` | `varchar(100)` |
| `title` | `varchar(255)` |
| `spec_json` | `longtext` |
| `gtin` | `varchar(50)` |
| `mpn` | `varchar(50)` |
| `category` | `varchar(100)` |
| `subcategory` | `varchar(100)` |
| `status` | `varchar(20)` |
| `wc_product_id` | `bigint` |
| `brand_id` | `bigint` |

### wp_compu_sources

| Columna | Tipo |
|---|---|

| `source_id` | `int` |
| `name` | `varchar(120)` |
| `alias` | `varchar(120)` |
| `sae_warehouse_no` | `int` |
| `slug` | `varchar(120)` |
| `currency` | `char(3)` |
| `enabled` | `tinyint(1)` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |

### wp_compu_subcategories

| Columna | Tipo |
|---|---|

| `subcategory_id` | `bigint` |
| `category_id` | `bigint` |
| `name` | `varchar(140)` |
| `slug` | `varchar(160)` |
| `sort_order` | `int` |
| `is_active` | `tinyint(1)` |
| `wc_term_id` | `bigint` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |

### wp_compu_subcategories_bak

| Columna | Tipo |
|---|---|

| `subcategory_id` | `bigint` |
| `category_id` | `bigint` |
| `name` | `varchar(140)` |
| `slug` | `varchar(160)` |
| `sort_order` | `int` |
| `is_active` | `tinyint(1)` |
| `wc_term_id` | `bigint` |
| `created_at` | `datetime` |
| `updated_at` | `datetime` |
| `id` | `bigint` |
| `event_data` | `text` |
| `created_at` | `datetime` |
| `link_id` | `bigint` |
| `link_url` | `varchar(255)` |
| `link_name` | `varchar(255)` |
| `link_image` | `varchar(255)` |
| `link_target` | `varchar(25)` |
| `link_description` | `varchar(255)` |
| `link_visible` | `varchar(20)` |
| `link_owner` | `bigint` |
| `link_rating` | `int` |
| `link_updated` | `datetime` |
| `link_rel` | `varchar(255)` |
| `link_notes` | `mediumtext` |
| `link_rss` | `varchar(255)` |
| `option_id` | `bigint` |
| `option_name` | `varchar(191)` |
| `option_value` | `longtext` |
| `autoload` | `varchar(20)` |


## Índice de tablas `wp_*`

- wp_actionscheduler_actions (14 columnas)
- wp_actionscheduler_claims (4 columnas)
- wp_actionscheduler_logs (5 columnas)
- wp_commentmeta (19 columnas)
- wp_compu_brands (10 columnas)
- wp_compu_categories (9 columnas)
- wp_compu_categories_bak (75 columnas)
- wp_compu_products (12 columnas)
- wp_compu_sources (9 columnas)
- wp_compu_subcategories (9 columnas)
- wp_compu_subcategories_bak (29 columnas)
- wp_postmeta (4 columnas)
- wp_posts (23 columnas)
- wp_revslider_css (6 columnas)
- wp_revslider_layer_animations (45 columnas)
- wp_term_relationships (3 columnas)
- wp_term_taxonomy (6 columnas)
- wp_terms (4 columnas)
- wp_usermeta (4 columnas)
- wp_users (10 columnas)
- wp_wc_admin_note_actions (9 columnas)
- wp_wc_admin_notes (17 columnas)
- wp_wc_category_lookup (2 columnas)
- wp_wc_customer_lookup (116 columnas)
- wp_wc_product_meta_lookup (15 columnas)
- wp_wc_rate_limits (12 columnas)
- wp_wc_webhooks (29 columnas)
- wp_woocommerce_downloadable_product_permissions (39 columnas)
- wp_woocommerce_shipping_zones (30 columnas)
- wp_woodmart_wishlist_products (5 columnas)
