-- Revierte 2026_11_21_eventos_reserva_boleto_indices.sql.

ALTER TABLE `eventos_reserva_boleto`
  DROP INDEX IF EXISTS `idx_reserva_funcion_seccion`,
  DROP INDEX IF EXISTS `idx_reserva_usuario`,
  DROP INDEX IF EXISTS `idx_reserva_creacion`;
