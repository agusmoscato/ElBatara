-- =====================================================================
-- MIGRACIÓN RONDA 20 — Se saca el paso de cocina del flujo de pedidos
-- (abierto -> en_preparacion -> entregado -> cerrado) y se reemplaza por
-- un único paso "cuenta_pedida" (abierto -> cuenta_pedida -> cerrado).
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger), desde phpMyAdmin -> pestaña "SQL" (pegar y
-- ejecutar completo, de arriba a abajo, en una sola pasada).
--
-- Qué hace, en orden:
--   1) Agrega `pedidos.cuenta_pedida_en`/`cuenta_pedida_por_id` (todavía
--      sin la FK).
--   2) Para los pedidos que HOY estén en 'entregado' (los únicos que
--      podrían seguir "vivos" en ese estado intermedio — 'en_preparacion'
--      es más raro que quede colgado pero se cubre igual), copia
--      `entregado_en`/`entregado_por_id` a las columnas nuevas, como la
--      mejor aproximación disponible al momento en que "se pidió la
--      cuenta" (son conceptos distintos, pero no hay otro dato mejor para
--      no perder el historial).
--   3) Pasa esos mismos pedidos ('en_preparacion' o 'entregado') a
--      'cuenta_pedida', ANTES de angostar el ENUM (si no, el paso
--      siguiente fallaría con esas filas).
--   4) Angosta `pedidos.estado` al ENUM nuevo
--      ('abierto','cuenta_pedida','cerrado','cancelado').
--   5) Agrega la FK de `cuenta_pedida_por_id`.
--   Las columnas `entregado_en`/`entregado_por_id` NO se borran — quedan
--   como dato histórico sin uso, el código ya no las escribe desde esta
--   ronda (mismo criterio que `usuarios.rol` en la ronda 17).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Columnas nuevas (sin FK todavía)
-- ---------------------------------------------------------------------
ALTER TABLE pedidos ADD COLUMN cuenta_pedida_en DATETIME NULL AFTER entregado_por_id;
ALTER TABLE pedidos ADD COLUMN cuenta_pedida_por_id INT UNSIGNED NULL AFTER cuenta_pedida_en;

-- ---------------------------------------------------------------------
-- 2) Copiar el mejor dato disponible para los pedidos 'entregado' vivos
-- ---------------------------------------------------------------------
UPDATE pedidos
   SET cuenta_pedida_en = entregado_en,
       cuenta_pedida_por_id = entregado_por_id
 WHERE estado = 'entregado';

-- ---------------------------------------------------------------------
-- 3) Pasar los pedidos en los estados viejos a 'cuenta_pedida'
-- ---------------------------------------------------------------------
UPDATE pedidos SET estado = 'cuenta_pedida' WHERE estado IN ('en_preparacion', 'entregado');

-- ---------------------------------------------------------------------
-- 4) Angostar el ENUM
-- ---------------------------------------------------------------------
ALTER TABLE pedidos MODIFY COLUMN estado ENUM('abierto', 'cuenta_pedida', 'cerrado', 'cancelado') NOT NULL DEFAULT 'abierto';

-- ---------------------------------------------------------------------
-- 5) FK de cuenta_pedida_por_id
-- ---------------------------------------------------------------------
ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cuenta_pedida_por FOREIGN KEY (cuenta_pedida_por_id) REFERENCES usuarios(id);
