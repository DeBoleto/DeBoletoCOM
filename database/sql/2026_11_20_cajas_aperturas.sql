-- Aperturas y cortes de caja de taquilla (boxoffice).
-- MariaDB 10.3+. Ejecutar una sola vez en cada ambiente; es idempotente.

CREATE TABLE IF NOT EXISTS `cajas_aperturas` (
  `pk_caja_apertura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pk_usuario` int(10) unsigned NOT NULL COMMENT 'Cajero que abre la caja',
  `caja` varchar(50) DEFAULT NULL COMMENT 'Nombre o número de la caja física',
  `estatus` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1 abierta, 2 cerrada',
  `apertura_fecha` datetime NOT NULL,
  `monto_apertura` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Fondo inicial en efectivo',
  `comentario_apertura` varchar(500) DEFAULT NULL,
  `cierre_fecha` datetime DEFAULT NULL,
  `cierre_pk_usuario_autoriza` int(10) unsigned DEFAULT NULL COMMENT 'Supervisor que autoriza el cierre',
  `total_compras` int(10) unsigned NOT NULL DEFAULT 0,
  `total_boletos` int(10) unsigned NOT NULL DEFAULT 0,
  `total_efectivo` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_terminal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_transferencia` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_ventas` decimal(14,2) NOT NULL DEFAULT 0.00,
  `efectivo_esperado` decimal(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Fondo inicial + ventas en efectivo',
  `efectivo_contado` decimal(14,2) DEFAULT NULL COMMENT 'Efectivo físico declarado al cerrar',
  `diferencia` decimal(14,2) DEFAULT NULL COMMENT 'Contado - esperado',
  `comentario_cierre` varchar(500) DEFAULT NULL,
  `abierta_pk_usuario` int(10) unsigned AS (IF(`estatus` = 1, `pk_usuario`, NULL)) PERSISTENT COMMENT 'Solo una caja abierta por cajero',
  `creacion_pk_usuario` int(10) unsigned NOT NULL,
  `creacion_fecha` datetime NOT NULL,
  `modificacion_pk_usuario` int(10) unsigned NOT NULL,
  `modificacion_fecha` datetime NOT NULL,
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`pk_caja_apertura`),
  UNIQUE KEY `cajas_aperturas_abierta_unique` (`abierta_pk_usuario`),
  KEY `cajas_aperturas_usuario_estatus_index` (`pk_usuario`, `estatus`),
  KEY `cajas_aperturas_apertura_fecha_index` (`apertura_fecha`),
  KEY `cajas_aperturas_autoriza_foreign` (`cierre_pk_usuario_autoriza`),
  CONSTRAINT `cajas_aperturas_pk_usuario_foreign` FOREIGN KEY (`pk_usuario`) REFERENCES `usuarios` (`pk_usuario`),
  CONSTRAINT `cajas_aperturas_autoriza_foreign` FOREIGN KEY (`cierre_pk_usuario_autoriza`) REFERENCES `usuarios` (`pk_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cajas_aperturas_compras` (
  `pk_caja_apertura_compra` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pk_caja_apertura` int(10) unsigned NOT NULL,
  `pk_evento_compra` int(10) unsigned NOT NULL,
  `creacion_pk_usuario` int(10) unsigned NOT NULL,
  `creacion_fecha` datetime NOT NULL,
  PRIMARY KEY (`pk_caja_apertura_compra`),
  UNIQUE KEY `cajas_aperturas_compras_compra_unique` (`pk_evento_compra`),
  KEY `cajas_aperturas_compras_apertura_index` (`pk_caja_apertura`),
  CONSTRAINT `cajas_aperturas_compras_apertura_foreign` FOREIGN KEY (`pk_caja_apertura`) REFERENCES `cajas_aperturas` (`pk_caja_apertura`),
  CONSTRAINT `cajas_aperturas_compras_compra_foreign` FOREIGN KEY (`pk_evento_compra`) REFERENCES `eventos_compras` (`pk_evento_compra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
