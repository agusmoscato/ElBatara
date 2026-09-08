-- =====================================================================
-- MIGRACIÓN RONDA 6 — Estado "Entregado" para pedidos
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma
-- que ya tenés en Hostinger, con tus mesas/usuarios/historial reales),
-- desde phpMyAdmin -> pestaña "Importar" o pestaña "SQL" (pegar y
-- ejecutar). No borra ni modifica pedidos existentes: solo agrega el
-- nuevo estado "entregado" a la lista de estados posibles y dos
-- columnas nuevas para auditoría (quién y cuándo lo marcó entregado).
-- =====================================================================

ALTER TABLE pedidos
    MODIFY COLUMN estado ENUM('abierto', 'en_preparacion', 'entregado', 'cerrado', 'cancelado') NOT NULL DEFAULT 'abierto';

ALTER TABLE pedidos ADD COLUMN entregado_en DATETIME NULL AFTER creado_en;
ALTER TABLE pedidos ADD COLUMN entregado_por_id INT UNSIGNED NULL AFTER entregado_en;
ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_entregado_por FOREIGN KEY (entregado_por_id) REFERENCES usuarios(id);
