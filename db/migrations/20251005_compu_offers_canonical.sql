-- Actualiza wp_compu_offers al esquema canónico.
-- Ejecutar en la base de datos de WordPress antes de desplegar los stages.

ALTER TABLE `wp_compu_offers`
  ADD COLUMN IF NOT EXISTS `cost_usd` DECIMAL(12,4) NULL AFTER `supplier_sku`,
  ADD COLUMN IF NOT EXISTS `exchange_rate` DECIMAL(10,4) NULL AFTER `cost_usd`,
  ADD COLUMN IF NOT EXISTS `stock_total` INT NULL AFTER `exchange_rate`,
  ADD COLUMN IF NOT EXISTS `stock_main` INT NULL AFTER `stock_total`,
  ADD COLUMN IF NOT EXISTS `stock_tijuana` INT NULL AFTER `stock_main`,
  ADD COLUMN IF NOT EXISTS `stock_by_branch_json` LONGTEXT NULL AFTER `stock_tijuana`;

-- Migra datos antiguos si existían columnas legacy
UPDATE `wp_compu_offers`
  SET `cost_usd` = COALESCE(`cost_usd`, `price_cost`, `price_usd`);
UPDATE `wp_compu_offers`
  SET `exchange_rate` = COALESCE(`exchange_rate`, `tipo_cambio`);
UPDATE `wp_compu_offers`
  SET `stock_total` = COALESCE(`stock_total`, `stock`);

ALTER TABLE `wp_compu_offers`
  DROP COLUMN IF EXISTS `price_cost`,
  DROP COLUMN IF EXISTS `price_usd`,
  DROP COLUMN IF EXISTS `stock`;

ALTER TABLE `wp_compu_offers`
  DROP INDEX IF EXISTS `uniq_offer`,
  ADD UNIQUE KEY IF NOT EXISTS `uniq_supplier_source` (`supplier_sku`,`source`),
  ADD KEY IF NOT EXISTS `product_source` (`product_id`,`source`);

CREATE OR REPLACE VIEW `wp_compu_offers_legacy` AS
  SELECT
    id,
    product_id,
    source,
    supplier_sku,
    cost_usd AS price_cost,
    exchange_rate,
    stock_total AS stock,
    stock_main,
    stock_tijuana,
    stock_by_branch_json,
    currency,
    offer_hash,
    valid_from,
    created_at,
    updated_at
  FROM `wp_compu_offers`;
