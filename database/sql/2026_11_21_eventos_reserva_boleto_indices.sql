-- Índices para los apartados de asientos (eventos_reserva_boleto).
-- La tabla no tenía índices: cada consulta del mapa la recorría completa.
-- MariaDB 10.1.4+. Ejecutar una sola vez en cada ambiente; es idempotente.

ALTER TABLE `eventos_reserva_boleto`
  ADD INDEX IF NOT EXISTS `idx_reserva_funcion_seccion` (`pk_evento_funcion`, `pk_seccion`, `creacion_fecha`),
  ADD INDEX IF NOT EXISTS `idx_reserva_usuario` (`pk_usuario`),
  ADD INDEX IF NOT EXISTS `idx_reserva_creacion` (`creacion_fecha`);
