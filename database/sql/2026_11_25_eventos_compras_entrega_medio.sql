-- Medio por el que se entregaron los boletos de la compra desde taquilla o
-- dashboard: impreso, digital (PDF), whatsapp o correo. Guarda el último medio.
-- MariaDB 10.3+. Ejecutar una sola vez en cada ambiente; es idempotente.

ALTER TABLE `eventos_compras`
  ADD COLUMN IF NOT EXISTS `entrega_medio` varchar(10) DEFAULT NULL COMMENT 'impreso, digital, whatsapp o correo',
  ADD COLUMN IF NOT EXISTS `entrega_fecha` datetime DEFAULT NULL COMMENT 'Fecha de la última entrega',
  ADD COLUMN IF NOT EXISTS `entrega_pk_usuario` int(10) unsigned DEFAULT NULL COMMENT 'Usuario que entregó';
