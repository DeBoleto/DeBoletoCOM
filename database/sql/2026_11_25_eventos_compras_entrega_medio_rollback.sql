-- Revierte 2026_11_25_eventos_compras_entrega_medio.sql.

ALTER TABLE `eventos_compras`
  DROP COLUMN IF EXISTS `entrega_medio`,
  DROP COLUMN IF EXISTS `entrega_fecha`,
  DROP COLUMN IF EXISTS `entrega_pk_usuario`;
