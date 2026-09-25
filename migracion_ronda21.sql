-- =====================================================================
-- MIGRACIÓN RONDA 21 — Mesas adentro/afuera, pre-cuenta imprimible y
-- circuito de cocina por ítem (con panel de cocina en tiempo real).
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger), desde phpMyAdmin -> pestaña "SQL" (pegar y
-- ejecutar completo, de arriba a abajo, en una sola pasada).
--
-- Qué hace, en orden:
--   1) Agrega `mesas.ubicacion` ('adentro'/'afuera', default 'adentro'
--      para no dejar ninguna mesa existente sin clasificar).
--   2) Agrega `pedido_items.estado_cocina`
--      ('pendiente','enviado','listo','entregado', default 'pendiente').
--   3) Migra el estado_cocina de los ítems YA EXISTENTES a un valor
--      razonable según el estado del pedido al que pertenecen (ver nota
--      abajo): los de pedidos 'cerrado' o 'cancelado' pasan a 'entregado'
--      (ya se sirvieron o ya no importan), los de pedidos 'abierto' o
--      'cuenta_pedida' quedan en 'pendiente' (el default de la columna
--      nueva, no hace falta UPDATE) porque no hay forma de saber si ya se
--      habían enviado a cocina en el modelo viejo (no existía esa
--      columna) — asumimos que en el momento de correr esta migración no
--      quedan pedidos abiertos "a mitad de cocinar" (se recomienda migrar
--      con el local cerrado o entre turnos, no con mesas en curso).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) mesas.ubicacion
-- ---------------------------------------------------------------------
ALTER TABLE mesas ADD COLUMN ubicacion ENUM('adentro', 'afuera') NOT NULL DEFAULT 'adentro' AFTER capacidad;

-- ---------------------------------------------------------------------
-- 2) pedido_items.estado_cocina
-- ---------------------------------------------------------------------
ALTER TABLE pedido_items ADD COLUMN estado_cocina ENUM('pendiente', 'enviado', 'listo', 'entregado') NOT NULL DEFAULT 'pendiente' AFTER subtotal;
ALTER TABLE pedido_items ADD COLUMN enviado_cocina_en DATETIME NULL AFTER estado_cocina;

-- ---------------------------------------------------------------------
-- 3) Migración de datos: ítems de pedidos ya cerrados/cancelados se dan
--    por entregados (no tiene sentido que el panel de cocina los muestre
--    como pendientes de un pedido que ya terminó su ciclo).
-- ---------------------------------------------------------------------
UPDATE pedido_items pi
  JOIN pedidos p ON p.id = pi.pedido_id
   SET pi.estado_cocina = 'entregado'
 WHERE p.estado IN ('cerrado', 'cancelado');
