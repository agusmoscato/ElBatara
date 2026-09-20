-- =====================================================================
-- MIGRACIÓN RONDA 7 — Integridad de datos (stock y caja concurrentes,
-- precios a revisar)
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger, con tus mesas/usuarios/historial reales), desde
-- phpMyAdmin -> pestaña "SQL" (pegar y ejecutar). No borra ni modifica
-- pedidos ni productos existentes: solo agrega una columna nueva y un
-- índice de integridad.
--
-- IMPORTANTE antes de correr esto: si por algún motivo tenés más de una
-- fila en caja_sesiones con estado='abierta' al mismo tiempo (no debería
-- pasar en uso normal, pero podría haber ocurrido antes de esta
-- migración por la condición de carrera que corrige), el ALTER TABLE del
-- índice único va a fallar con "Duplicate entry". En ese caso, primero
-- cerrá manualmente las cajas abiertas de más desde Caja -> Historial (o
-- con un UPDATE puntual) y después corré esta migración.
-- =====================================================================

-- Productos: marca de "precio a revisar" (badge de advertencia en la
-- pantalla de Productos), y la aplica a los 3 productos ya identificados
-- con precio dudoso en la carga inicial de la carta real.
ALTER TABLE productos ADD COLUMN precio_a_revisar TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;

UPDATE productos SET precio_a_revisar = 1
WHERE nombre IN (
    'Costeletas de ternera con papas fritas (2 unidades)',
    'Papas fritas (bastón) c/cheddar',
    'Cerro Callejero tinto'
);

-- Caja: impide a nivel de base de datos que existan dos sesiones de caja
-- abiertas a la vez, incluso si dos aperturas llegan al mismo tiempo
-- desde dos dispositivos distintos (el chequeo en PHP, por sí solo, no
-- alcanza para evitar esa condición de carrera).
ALTER TABLE caja_sesiones
    ADD COLUMN unica_abierta TINYINT GENERATED ALWAYS AS (IF(estado = 'abierta', 1, NULL)) STORED;
ALTER TABLE caja_sesiones ADD UNIQUE KEY ux_caja_una_abierta (unica_abierta);

-- Pedidos: índice compuesto para reportes/ventas.php y caja/cerrar.php, que
-- siempre filtran por estado='cerrado' Y un rango de cerrado_en a la vez.
CREATE INDEX idx_pedidos_estado_cerrado ON pedidos(estado, cerrado_en);

-- Usuarios: bloqueo temporal por fuerza bruta (ver includes/auth.php).
ALTER TABLE usuarios ADD COLUMN intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER activo;
ALTER TABLE usuarios ADD COLUMN bloqueado_hasta DATETIME NULL AFTER intentos_fallidos;
