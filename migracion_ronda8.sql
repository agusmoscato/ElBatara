-- =====================================================================
-- MIGRACIÓN RONDA 8 — Medios de pago dinámicos, canal (mostrador/mesa)
-- y módulo de Egresos.
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger, con tus mesas/usuarios/historial reales),
-- desde phpMyAdmin -> pestaña "SQL" (pegar y ejecutar completo, de
-- arriba a abajo, en una sola pasada).
--
-- Qué hace, en orden:
--   1) Crea la tabla `medios_pago` (editable desde Medios de pago -> ABM)
--      y la carga con los 6 medios iniciales.
--   2) Migra `pedidos.medio_pago` (ENUM fijo) a `pedidos.medio_pago_id`
--      (FK a la tabla nueva), sin perder el historial de pedidos ya
--      cerrados, y borra la columna ENUM vieja al final.
--      Mapeo del valor viejo 'tarjeta': se lleva a "Posnet Crédito".
--      El ENUM viejo no distinguía débito de crédito; se eligió Crédito
--      como destino porque en un almacén/resto la tarjeta genérica suele
--      ser mayoritariamente crédito. Si tenés forma de saber cuáles de
--      esos pedidos viejos fueron en realidad débito, podés recategorizar
--      esas filas puntuales a mano después con un UPDATE simple.
--   3) Agrega `pedidos.canal` ('mostrador' o 'mesa'), completado según
--      si el pedido tiene mesa_id o no (igual que el campo se completa
--      de acá en más desde el código).
--   4) Crea `categorias_egreso` (con categorías iniciales) y `egresos`.
--   5) Agrega el desglose dinámico de caja por medio de pago
--      (`caja_sesion_medios`) y una columna nueva en `caja_sesiones`
--      para el total de egresos en efectivo. Las columnas viejas
--      `total_tarjeta` / `total_transferencia` de `caja_sesiones` NO se
--      tocan (se conservan tal cual para no perder el historial de
--      cierres ya hechos); de acá en más, el desglose completo por
--      medio de pago de cada cierre nuevo se guarda en
--      `caja_sesion_medios`.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Medios de pago
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medios_pago (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO medios_pago (nombre) SELECT 'Efectivo' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'Efectivo');
INSERT INTO medios_pago (nombre) SELECT 'Transferencia' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'Transferencia');
INSERT INTO medios_pago (nombre) SELECT 'QR / Mercado Pago' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'QR / Mercado Pago');
INSERT INTO medios_pago (nombre) SELECT 'Posnet Débito' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'Posnet Débito');
INSERT INTO medios_pago (nombre) SELECT 'Posnet Crédito' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'Posnet Crédito');
INSERT INTO medios_pago (nombre) SELECT 'Otro' WHERE NOT EXISTS (SELECT 1 FROM medios_pago WHERE nombre = 'Otro');

-- ---------------------------------------------------------------------
-- 2) pedidos.medio_pago (ENUM) -> pedidos.medio_pago_id (FK)
-- ---------------------------------------------------------------------
ALTER TABLE pedidos ADD COLUMN medio_pago_id INT UNSIGNED NULL AFTER medio_pago;

UPDATE pedidos p JOIN medios_pago m ON m.nombre = 'Efectivo'
    SET p.medio_pago_id = m.id WHERE p.medio_pago = 'efectivo';
UPDATE pedidos p JOIN medios_pago m ON m.nombre = 'Transferencia'
    SET p.medio_pago_id = m.id WHERE p.medio_pago = 'transferencia';
UPDATE pedidos p JOIN medios_pago m ON m.nombre = 'Posnet Crédito'
    SET p.medio_pago_id = m.id WHERE p.medio_pago = 'tarjeta';

ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_medio_pago FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id);
ALTER TABLE pedidos DROP COLUMN medio_pago;

-- ---------------------------------------------------------------------
-- 3) Canal: mostrador / mesa
-- ---------------------------------------------------------------------
ALTER TABLE pedidos ADD COLUMN canal ENUM('mostrador', 'mesa') NOT NULL DEFAULT 'mostrador' AFTER mesa_id;
UPDATE pedidos SET canal = IF(mesa_id IS NOT NULL, 'mesa', 'mostrador');
CREATE INDEX idx_pedidos_canal ON pedidos(canal);

-- ---------------------------------------------------------------------
-- 4) Egresos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias_egreso (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categorias_egreso (nombre) SELECT 'Proveedores' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Proveedores');
INSERT INTO categorias_egreso (nombre) SELECT 'Sueldos' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Sueldos');
INSERT INTO categorias_egreso (nombre) SELECT 'Retiro de caja' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Retiro de caja');
INSERT INTO categorias_egreso (nombre) SELECT 'Servicios (luz/gas/internet)' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Servicios (luz/gas/internet)');
INSERT INTO categorias_egreso (nombre) SELECT 'Mantenimiento' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Mantenimiento');
INSERT INTO categorias_egreso (nombre) SELECT 'Otro' WHERE NOT EXISTS (SELECT 1 FROM categorias_egreso WHERE nombre = 'Otro');

CREATE TABLE IF NOT EXISTS egresos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    medio_pago_id INT UNSIGNED NOT NULL,
    caja_sesion_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nota VARCHAR(500) NULL,
    CONSTRAINT fk_egresos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_egreso(id),
    CONSTRAINT fk_egresos_medio_pago FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id),
    CONSTRAINT fk_egresos_caja FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones(id),
    CONSTRAINT fk_egresos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_egresos_creado ON egresos(creado_en);
CREATE INDEX idx_egresos_caja ON egresos(caja_sesion_id);

-- ---------------------------------------------------------------------
-- 5) Desglose dinámico de caja por medio de pago
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS caja_sesion_medios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caja_sesion_id INT UNSIGNED NOT NULL,
    medio_pago_id INT UNSIGNED NOT NULL,
    total_ventas DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_egresos DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_caja_medio (caja_sesion_id, medio_pago_id),
    CONSTRAINT fk_csm_caja FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones(id),
    CONSTRAINT fk_csm_medio FOREIGN KEY (medio_pago_id) REFERENCES medios_pago(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE caja_sesiones ADD COLUMN total_egresos_efectivo DECIMAL(10,2) NULL AFTER total_efectivo;
