-- =====================================================================
-- Base de datos: Sistema de gestión para restaurante / almacén de campo
-- Compatible con MySQL 5.7 / 8.0 (Hostinger)
-- Importar este archivo completo desde phpMyAdmin en el hosting.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tabla: usuarios
-- Usuarios que ingresan al sistema (administradores y empleados)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'empleado') NOT NULL DEFAULT 'empleado',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    -- Bloqueo temporal por fuerza bruta: se cuentan los intentos fallidos
    -- consecutivos y, al llegar al límite, se guarda hasta cuándo queda
    -- bloqueado el usuario (ver includes/auth.php). Se resetean ambos en
    -- cada login exitoso.
    intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tabla: categorias
-- Categorías del menú (Fiambres, Platos, Bebidas, Panadería, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tabla: productos
-- Productos vendibles. tipo_venta define si se carga por unidad o por kg.
-- El stock se guarda con decimales para poder representar kilos (ej 0.350).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    tipo_venta ENUM('unidad', 'peso') NOT NULL DEFAULT 'unidad',
    precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_actual DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    stock_minimo DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    -- Marca productos con precio dudoso cargado a partir de fotos de la
    -- carta (ver comentarios "revisar" en la sección CARTA REAL de este
    -- archivo). Se muestra como badge de advertencia en Productos hasta que
    -- el dueño confirme el precio real y lo desmarque a mano.
    precio_a_revisar TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_productos_categoria ON productos(categoria_id);
CREATE INDEX idx_productos_activo ON productos(activo);

-- ---------------------------------------------------------------------
-- Tabla: mesas
-- Mesas del salón. También se usa una mesa especial "Para llevar".
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    capacidad INT UNSIGNED NOT NULL DEFAULT 4,
    estado ENUM('libre', 'ocupada', 'cuenta_pedida') NOT NULL DEFAULT 'libre',
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tabla: pedidos
-- Un pedido (comanda) asociado a una mesa (o "para llevar" si mesa_id es NULL)
-- ---------------------------------------------------------------------
-- Flujo de estados de un pedido:
--   abierto -> en_preparacion -> entregado -> cerrado
--   (cancelado puede pasar desde abierto, en_preparacion o entregado,
--    en cualquier momento antes de cerrado)
-- Pasar por "entregado" es opcional: se puede cobrar directamente desde
-- "abierto" o "en_preparacion" sin marcarlo como entregado antes.
CREATE TABLE IF NOT EXISTS pedidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mesa_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    estado ENUM('abierto', 'en_preparacion', 'entregado', 'cerrado', 'cancelado') NOT NULL DEFAULT 'abierto',
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    medio_pago ENUM('efectivo', 'tarjeta', 'transferencia') NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregado_en DATETIME NULL,
    entregado_por_id INT UNSIGNED NULL,
    cerrado_en DATETIME NULL,
    cerrado_por_id INT UNSIGNED NULL,
    cancelado_en DATETIME NULL,
    cancelado_por_id INT UNSIGNED NULL,
    CONSTRAINT fk_pedidos_mesa FOREIGN KEY (mesa_id) REFERENCES mesas(id),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_pedidos_entregado_por FOREIGN KEY (entregado_por_id) REFERENCES usuarios(id),
    CONSTRAINT fk_pedidos_cerrado_por FOREIGN KEY (cerrado_por_id) REFERENCES usuarios(id),
    CONSTRAINT fk_pedidos_cancelado_por FOREIGN KEY (cancelado_por_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_pedidos_estado ON pedidos(estado);
CREATE INDEX idx_pedidos_cerrado_en ON pedidos(cerrado_en);
-- Compuesto: reportes/ventas.php y caja/cerrar.php siempre filtran por
-- estado='cerrado' Y un rango de cerrado_en al mismo tiempo.
CREATE INDEX idx_pedidos_estado_cerrado ON pedidos(estado, cerrado_en);

-- ---------------------------------------------------------------------
-- Tabla: pedido_items
-- Detalle de productos dentro de un pedido.
-- cantidad: unidades enteras o kg con decimales según tipo_venta del producto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedido_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    CONSTRAINT fk_items_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_items_pedido ON pedido_items(pedido_id);

-- ---------------------------------------------------------------------
-- Tabla: movimientos_stock
-- Historial de todos los movimientos de stock (ventas, ingresos, ajustes)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    tipo ENUM('venta', 'ingreso', 'ajuste') NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL,
    referencia_pedido_id INT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    nota VARCHAR(255) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_mov_pedido FOREIGN KEY (referencia_pedido_id) REFERENCES pedidos(id),
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_mov_producto ON movimientos_stock(producto_id);
CREATE INDEX idx_mov_creado ON movimientos_stock(creado_en);

-- ---------------------------------------------------------------------
-- Tabla: caja_sesiones
-- Aperturas y cierres de caja
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS caja_sesiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    monto_inicial DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_final_declarado DECIMAL(10,2) NULL,
    total_efectivo DECIMAL(10,2) NULL,
    total_tarjeta DECIMAL(10,2) NULL,
    total_transferencia DECIMAL(10,2) NULL,
    diferencia DECIMAL(10,2) NULL,
    estado ENUM('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
    abierta_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrada_en DATETIME NULL,
    nota VARCHAR(500) NULL,
    -- Columna generada: vale 1 solo cuando estado='abierta', NULL en cualquier
    -- otro caso. MySQL permite múltiples NULL en un índice UNIQUE pero nunca
    -- más de un mismo valor no-NULL, así que este índice hace imposible tener
    -- dos filas con estado='abierta' a la vez, incluso si dos aperturas de
    -- caja llegan al mismo tiempo desde dos dispositivos (condición de
    -- carrera que el chequeo en PHP, por sí solo, no puede evitar).
    unica_abierta TINYINT GENERATED ALWAYS AS (IF(estado = 'abierta', 1, NULL)) STORED,
    CONSTRAINT fk_caja_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY ux_caja_una_abierta (unica_abierta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DATOS DE EJEMPLO
-- =====================================================================

-- Usuarios de ejemplo
-- Contraseña para ambos: "123456" (hash generado con password_hash de PHP, bcrypt)
INSERT INTO usuarios (nombre, usuario, password_hash, rol) VALUES
('Administrador', 'admin', '$2y$10$Fk.B64MAzBYM89jgStVeW.FubxIPI0WOhxCo8CryPmNu37Jsbm0Ve', 'admin'),
('Empleado Demo', 'empleado', '$2y$10$Fk.B64MAzBYM89jgStVeW.FubxIPI0WOhxCo8CryPmNu37Jsbm0Ve', 'empleado');

-- Categorías y productos de ejemplo (genéricos) usados en las rondas 1-3
-- de desarrollo. Se dejan comentados como referencia de formato; el menú
-- real de "El Batará" se carga más abajo, en la sección "CARTA REAL".
-- Si preferís arrancar con datos genéricos en vez del menú real (por
-- ejemplo para un ambiente de pruebas), descomentá este bloque y comentá
-- el de "CARTA REAL".
--
-- INSERT INTO categorias (nombre) VALUES
-- ('Fiambres'), ('Quesos'), ('Platos'), ('Bebidas'), ('Panadería');
--
-- INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo) VALUES
-- (1, 'Jamón Crudo', 'peso', 8500.00, 5.000, 1.000),
-- (1, 'Salame Milán', 'peso', 6200.00, 3.500, 1.000),
-- (2, 'Queso Cremoso', 'peso', 5400.00, 4.000, 1.000),
-- (2, 'Queso de Rallar', 'peso', 6800.00, 2.000, 0.500),
-- (3, 'Milanesa con Papas Fritas', 'unidad', 4500.00, 50.000, 5.000),
-- (3, 'Empanada de Carne', 'unidad', 900.00, 100.000, 10.000),
-- (4, 'Gaseosa 500ml', 'unidad', 1500.00, 48.000, 12.000),
-- (4, 'Agua Mineral 500ml', 'unidad', 1200.00, 36.000, 12.000),
-- (5, 'Pan Casero (kg)', 'peso', 3200.00, 10.000, 2.000);

-- Mesas de ejemplo
INSERT INTO mesas (nombre, capacidad) VALUES
('Mesa 1', 4), ('Mesa 2', 4), ('Mesa 3', 2), ('Mesa 4', 6), ('Para Llevar', 0);

-- =====================================================================
-- CARTA REAL — El Batará (ronda 4)
-- Cargada a partir de fotos de la carta física. Notas de criterio:
--
-- - STOCK: los platos de cocina (pastas, sandwiches, comidas al plato,
--   cazuelas, picadas, postres) se cargan con stock_actual = 9999 y
--   stock_minimo = 0, es decir "sin control de stock real" (se preparan
--   al momento, no tiene sentido llevar un conteo de unidades). Las
--   bebidas con envase (gaseosas, aguas, cervezas, vinos) se cargan con
--   un stock inicial estimado (24 u. la mayoría, algo menos vinos) para
--   que el control de stock tenga sentido ahí. AJUSTÁ estos números
--   desde la pantalla "Stock -> Reponer stock" apenas tengas el conteo
--   real de tu depósito/heladera.
--
-- - PRECIOS MARCADOS "revisar" en un comentario: se cargaron con el
--   precio que diste, pero la carta física los mostraba con alguna
--   inconsistencia o dato parcialmente tapado. Quedan ACTIVOS (se
--   pueden vender) pero convendría que los confirmes pronto.
--
-- - PRECIOS "PENDIENTE" (latas de cerveza sin precio visible): se
--   cargan con precio 0.00 y activo = 0 (NO aparecen en el POS) para
--   no inventar un precio. Activalos desde Productos apenas tengas el
--   precio real.
--
-- - Falta la categoría "Embutidos curados en grasa de cerdo" (foto no
--   legible todavía) — no se cargó, queda pendiente para otra ronda.
--
-- Si tu base de datos YA estaba en producción con las categorías de
-- ejemplo de arriba activas, revisá también el archivo carta_real.sql
-- (mismo contenido que este bloque) para importarlo por separado sin
-- volver a correr todo este script, y considerá desactivar o borrar
-- las categorías de ejemplo desde la pantalla de Categorías.
-- =====================================================================

-- INICIO_CARTA_COMPARABLE (marcador para scripts/comparar_arboles.sh —
-- no borrar; delimita el bloque que debe ser idéntico en ambos archivos)
-- Cada categoría se inserta solo si no existe ya una con ese nombre
-- (INSERT ... SELECT ... WHERE NOT EXISTS). Esto evita duplicar la
-- categoría "Bebidas" si tu base ya tenía una con ese nombre de los
-- datos de ejemplo, y de paso hace que este bloque se pueda volver a
-- correr sin romper nada si ya se había ejecutado antes.
INSERT INTO categorias (nombre) SELECT 'Sugerencia del día' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Sugerencia del día');
INSERT INTO categorias (nombre) SELECT 'Pastas' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Pastas');
INSERT INTO categorias (nombre) SELECT 'Menú Vegetariano' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Menú Vegetariano');
INSERT INTO categorias (nombre) SELECT 'Ensaladas' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Ensaladas');
INSERT INTO categorias (nombre) SELECT 'Postres' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Postres');
INSERT INTO categorias (nombre) SELECT 'Sandwiches Fríos' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Sandwiches Fríos');
INSERT INTO categorias (nombre) SELECT 'Sandwiches Calientes' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Sandwiches Calientes');
INSERT INTO categorias (nombre) SELECT 'Comidas al Plato' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Comidas al Plato');
INSERT INTO categorias (nombre) SELECT 'Cazuelas de Fiambres' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Cazuelas de Fiambres');
INSERT INTO categorias (nombre) SELECT 'Cazuelas de Copetín' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Cazuelas de Copetín');
INSERT INTO categorias (nombre) SELECT 'Picadas' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Picadas');
INSERT INTO categorias (nombre) SELECT 'Bebidas' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Bebidas');
INSERT INTO categorias (nombre) SELECT 'Agua' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Agua');
INSERT INTO categorias (nombre) SELECT 'Vinos' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Vinos');
INSERT INTO categorias (nombre) SELECT 'Aperitivos' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Aperitivos');
INSERT INTO categorias (nombre) SELECT 'Cervezas' WHERE NOT EXISTS (SELECT 1 FROM categorias WHERE nombre = 'Cervezas');

-- Sugerencia del día
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Sugerencia del día' LIMIT 1), 'Costeletas de ternera con papas fritas (2 unidades)', 'unidad', 14000.00, 9999.000, 0.000, 1); -- revisar: precio de version anterior de la carta

-- Pastas (se sirven con salsa a eleccion -crema, mixta o tuco- sin cargo)
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Pastas' LIMIT 1), 'Sorrentinos jamón y queso', 'unidad', 20000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Pastas' LIMIT 1), 'Ravioles de verdura', 'unidad', 20000.00, 9999.000, 0.000, 1);

-- Menú Vegetariano
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Menú Vegetariano' LIMIT 1), 'Sandwich de queso con salsa de cebolla y morrón', 'unidad', 15000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Menú Vegetariano' LIMIT 1), 'Sandwich de queso con tomate y lechuga', 'unidad', 15000.00, 9999.000, 0.000, 1);

-- Ensaladas
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Ensaladas' LIMIT 1), 'Lechuga y tomate', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Ensaladas' LIMIT 1), 'Rúcula, jamón crudo, champignones, cherry y parmesano', 'unidad', 20000.00, 9999.000, 0.000, 1);

-- Postres
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Helado', 'unidad', 4500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Dulce de membrillo y queso', 'unidad', 4500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Dulce de batata y queso', 'unidad', 4500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Pastelito (batata o membrillo)', 'unidad', 9000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Higos en almíbar y queso', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Postres' LIMIT 1), 'Zapallo en almíbar y queso', 'unidad', 12000.00, 9999.000, 0.000, 1);

-- Sandwiches Fríos (se sirven acompañados con papas baston)
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Salame y queso', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Longaniza y queso', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Bondiola y queso', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Jamón crudo y queso', 'unidad', 18000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Lomito y queso', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Fríos' LIMIT 1), 'Panceta arrollada y queso', 'unidad', 18000.00, 9999.000, 0.000, 1);

-- Sandwiches Calientes
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Bife de chorizo con salsa de cebolla y morrón (con papas fritas)', 'unidad', 20000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Milanesas con papas fritas', 'unidad', 18000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Milanesa completa (jamón, queso y huevo, con papas fritas)', 'unidad', 22000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Hamburguesa gigante con papas fritas', 'unidad', 14000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Hamburguesa gigante completa (jamón, queso y huevo, con papas fritas)', 'unidad', 18000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Agregado de queso cheddar', 'unidad', 1500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Agregado de panes', 'unidad', 600.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Sandwiches Calientes' LIMIT 1), 'Agregado de lechuga y tomate', 'unidad', 1500.00, 9999.000, 0.000, 1);

-- Comidas al Plato
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Comidas al Plato' LIMIT 1), 'Bife de chorizo c/salsa de cebolla y morrón c/papas fritas', 'unidad', 35000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Comidas al Plato' LIMIT 1), 'Costeleta de ternera c/papas fritas (2 unidades)', 'unidad', 30000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Comidas al Plato' LIMIT 1), 'Milanesa de carne con papas fritas', 'unidad', 22000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Comidas al Plato' LIMIT 1), 'Milanesa de carne completa c/papas fritas (queso, jamón y huevo)', 'unidad', 27000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Comidas al Plato' LIMIT 1), 'Milanesa de carne a la napolitana c/papas fritas', 'unidad', 27000.00, 9999.000, 0.000, 1);

-- Cazuelas de Fiambres (por unidad de tabla/cazuela)
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Salame', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Longaniza', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Jamón crudo', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Bondiola', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Lomito curado en sal', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Lomito ahumado', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Queso', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Fiambres' LIMIT 1), 'Canasta de pan', 'unidad', 8000.00, 9999.000, 0.000, 1);

-- Cazuelas de Copetín
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Aceitunas', 'unidad', 5000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Papas fritas (bastón)', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Maní', 'unidad', 3500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Papas fritas (bastón) c/cheddar', 'unidad', 3000.00, 9999.000, 0.000, 1), -- revisar: precio parece inconsistente (menor que la version sin cheddar)
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Berenjenas', 'unidad', 12000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cazuelas de Copetín' LIMIT 1), 'Canastita de papas fritas de copetín', 'unidad', 3500.00, 9999.000, 0.000, 1);

-- Picadas
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Picadas' LIMIT 1), 'Picada El Batará: salame picado fino y grueso, longaniza, lomito de cerdo curado en sal, lomito ahumado, bondiola, panceta arrollada, jamón cocido, jamón crudo, tres variedades de queso (come 2, pican 4)', 'unidad', 45000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Picadas' LIMIT 1), 'Tabla de salame y queso', 'unidad', 28000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Picadas' LIMIT 1), 'Tabla de quesos', 'unidad', 25000.00, 9999.000, 0.000, 1);

-- Bebidas (linea Pepsi)
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Pepsi', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Pepsi Light', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Agua tónica', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Mirinda', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), '7up', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), '7up Free', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Paso de los Toros pomelo', 'unidad', 2800.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Bebidas' LIMIT 1), 'Exprimido de naranja', 'unidad', 5500.00, 9999.000, 0.000, 1);

-- Agua
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Agua' LIMIT 1), 'Con gas', 'unidad', 2200.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Agua' LIMIT 1), 'Sin gas', 'unidad', 2200.00, 24.000, 6.000, 1);

-- Vinos
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Vinos' LIMIT 1), 'Finca "Las Moras"', 'unidad', 8000.00, 12.000, 3.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Vinos' LIMIT 1), 'Copa de vino', 'unidad', 3000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Vinos' LIMIT 1), 'Cerro Callejero tinto', 'unidad', 12000.00, 12.000, 3.000, 1), -- revisar: nombre parcialmente tapado en la foto
((SELECT id FROM categorias WHERE nombre = 'Vinos' LIMIT 1), 'Alma Mora tinto', 'unidad', 10000.00, 12.000, 3.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Vinos' LIMIT 1), 'Trumpeter Rutini tinto', 'unidad', 17000.00, 12.000, 3.000, 1);

-- Aperitivos
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Fernet con Pepsi', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Gancia con limón', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Gin Tonic', 'unidad', 9000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Whisky', 'unidad', 9000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Medida de Fernet', 'unidad', 4500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Cinzano', 'unidad', 8000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Cinzano con Fernet y soda', 'unidad', 9000.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Aperitivos' LIMIT 1), 'Campari con naranja o tónica', 'unidad', 9000.00, 9999.000, 0.000, 1);

-- Cervezas
INSERT INTO productos (categoria_id, nombre, tipo_venta, precio, stock_actual, stock_minimo, activo) VALUES
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Cerveza artesanal', 'unidad', 4500.00, 9999.000, 0.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Lata Quilmes', 'unidad', 4000.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Lata Andes Roja', 'unidad', 0.00, 0.000, 0.000, 0), -- PENDIENTE: falta precio, inactivo hasta confirmar
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Lata Stella Artois', 'unidad', 0.00, 0.000, 0.000, 0), -- PENDIENTE: falta precio, inactivo hasta confirmar
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Lata Stella Artois Negra', 'unidad', 0.00, 0.000, 0.000, 0), -- PENDIENTE: falta precio, inactivo hasta confirmar
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Porrón Corona', 'unidad', 5000.00, 24.000, 6.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Stella Artois de Litro', 'unidad', 9000.00, 12.000, 3.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Heineken de Litro', 'unidad', 9000.00, 12.000, 3.000, 1),
((SELECT id FROM categorias WHERE nombre = 'Cervezas' LIMIT 1), 'Andes Roja de Litro', 'unidad', 0.00, 0.000, 0.000, 0); -- PENDIENTE: falta precio, inactivo hasta confirmar
-- FIN_CARTA_COMPARABLE

-- =====================================================================
-- MIGRACIÓN (solo necesaria si ya habías importado este archivo antes
-- de que existieran las columnas de auditoría de abajo). Si estás
-- importando este archivo por primera vez, las columnas ya se crean
-- en el CREATE TABLE de "pedidos" más arriba y podés ignorar esto.
-- =====================================================================
-- ALTER TABLE pedidos ADD COLUMN cerrado_por_id INT UNSIGNED NULL AFTER cerrado_en;
-- ALTER TABLE pedidos ADD COLUMN cancelado_en DATETIME NULL AFTER cerrado_por_id;
-- ALTER TABLE pedidos ADD COLUMN cancelado_por_id INT UNSIGNED NULL AFTER cancelado_en;
-- ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cerrado_por FOREIGN KEY (cerrado_por_id) REFERENCES usuarios(id);
-- ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cancelado_por FOREIGN KEY (cancelado_por_id) REFERENCES usuarios(id);

-- Necesaria si tu base ya existía antes de la ronda 4 (algunos nombres
-- de la carta real, como la Picada El Batará, superan los 150
-- caracteres que soportaba la columna originalmente). Si vas a importar
-- carta_real.sql en una base existente, ese archivo ya la incluye y no
-- hace falta correrla a mano.
-- ALTER TABLE productos MODIFY COLUMN nombre VARCHAR(255) NOT NULL;

-- Necesaria si tu base es anterior a la ronda 4 (nota manual al cerrar caja).
-- ALTER TABLE caja_sesiones ADD COLUMN nota VARCHAR(500) NULL AFTER cerrada_en;

-- Necesaria si tu base es anterior a la ronda 6 (estado "entregado").
-- ALTER TABLE pedidos MODIFY COLUMN estado ENUM('abierto', 'en_preparacion', 'entregado', 'cerrado', 'cancelado') NOT NULL DEFAULT 'abierto';
-- ALTER TABLE pedidos ADD COLUMN entregado_en DATETIME NULL AFTER creado_en;
-- ALTER TABLE pedidos ADD COLUMN entregado_por_id INT UNSIGNED NULL AFTER entregado_en;
-- ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_entregado_por FOREIGN KEY (entregado_por_id) REFERENCES usuarios(id);

-- Necesaria si tu base es anterior a la ronda 7 (ver migracion_ronda7.sql
-- para el detalle completo: precio_a_revisar en productos + índice único
-- que impide dos cajas abiertas a la vez).
-- ALTER TABLE productos ADD COLUMN precio_a_revisar TINYINT(1) NOT NULL DEFAULT 0 AFTER activo;
-- ALTER TABLE caja_sesiones ADD COLUMN unica_abierta TINYINT GENERATED ALWAYS AS (IF(estado = 'abierta', 1, NULL)) STORED;
-- ALTER TABLE caja_sesiones ADD UNIQUE KEY ux_caja_una_abierta (unica_abierta);
-- CREATE INDEX idx_pedidos_estado_cerrado ON pedidos(estado, cerrado_en);
-- ALTER TABLE usuarios ADD COLUMN intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER activo;
-- ALTER TABLE usuarios ADD COLUMN bloqueado_hasta DATETIME NULL AFTER intentos_fallidos;

-- =====================================================================
-- RONDA 7: marca de "precio a revisar" en los 3 productos cargados con
-- precio dudoso desde fotos de la carta (ver comentarios "revisar" más
-- arriba en la sección CARTA REAL). Se muestra como badge de advertencia
-- en Productos hasta que el dueño confirme el precio real y lo desmarque
-- a mano desde esa misma pantalla.
-- =====================================================================
UPDATE productos SET precio_a_revisar = 1
WHERE nombre IN (
    'Costeletas de ternera con papas fritas (2 unidades)',
    'Papas fritas (bastón) c/cheddar',
    'Cerro Callejero tinto'
);
