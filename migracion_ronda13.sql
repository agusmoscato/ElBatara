-- =====================================================================
-- MIGRACIÓN RONDA 13 — Medio de pago "Efectivo" por columna fija y
-- motivo obligatorio al cancelar un pedido.
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger), desde phpMyAdmin -> pestaña "SQL" (pegar y
-- ejecutar completo, de arriba a abajo, en una sola pasada).
--
-- Qué hace, en orden:
--   1) Agrega `medios_pago.es_efectivo` (antes el sistema identificaba el
--      medio "Efectivo" comparando el texto del nombre, que el ABM
--      permite editar libremente -> si alguien lo renombraba, el cálculo
--      de "efectivo esperado" en el cierre de caja se rompía en
--      silencio). Marca como efectivo físico el medio que hoy se llama
--      "Efectivo" (o el que más se le parezca), para no perder el estado
--      actual. Si tu base no tiene ningún medio llamado así, revisá a
--      mano cuál corresponde y marcalo desde Configuración -> Medios de
--      pago después de correr esta migración.
--   2) Agrega `pedidos.motivo_cancelacion`, para que cancelar un pedido
--      pida siempre un motivo (auditoría).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) medios_pago.es_efectivo
-- ---------------------------------------------------------------------
ALTER TABLE medios_pago ADD COLUMN es_efectivo TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

UPDATE medios_pago SET es_efectivo = 1 WHERE LOWER(nombre) = 'efectivo' LIMIT 1;

-- ---------------------------------------------------------------------
-- 2) pedidos.motivo_cancelacion
-- ---------------------------------------------------------------------
ALTER TABLE pedidos ADD COLUMN motivo_cancelacion VARCHAR(255) NULL AFTER cancelado_por_id;
