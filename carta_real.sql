-- =====================================================================
-- CARTA REAL — El Batará — importación independiente
--
-- Este archivo tiene el MISMO contenido que el bloque "CARTA REAL" de
-- database.sql, separado para poder importarlo directo en una base de
-- datos que YA está en producción (por ejemplo, la que ya tenés
-- funcionando en Hostinger), sin necesidad de volver a correr todo
-- database.sql desde cero.
--
-- CÓMO IMPORTARLO: phpMyAdmin -> seleccionar tu base -> pestaña
-- "Importar" -> elegir este archivo -> Continuar. Es seguro: solo
-- agrega categorías y productos nuevos, no toca ni borra nada de lo
-- que ya tenés cargado (mesas, usuarios, pedidos, historial de caja).
-- Las categorías se insertan solo si no existen todavía por nombre,
-- así que también es seguro importarlo dos veces por error.
--
-- OJO: si tu base ya tenía las categorías de EJEMPLO activas
-- (Fiambres, Quesos, Platos, Bebidas, Panadería con productos como
-- "Jamón Crudo" o "Gaseosa 500ml"), la categoría "Bebidas" de ejemplo
-- se va a REUSAR para las bebidas reales (no se duplica), así que vas
-- a ver mezclados ahí "Gaseosa 500ml" (de ejemplo) junto con "Pepsi",
-- "7up", etc. (reales). Para limpiar eso, andá a la pantalla
-- "Productos" del sistema y desactivá o editá los productos de
-- ejemplo que ya no necesites (Jamón Crudo, Salame Milán, Queso
-- Cremoso, Queso de Rallar, Milanesa con Papas Fritas, Empanada de
-- Carne, Gaseosa 500ml, Agua Mineral 500ml, Pan Casero) uno por uno,
-- o desactivá directamente las categorías de ejemplo que no se
-- reusaron (Fiambres, Quesos, Platos, Panadería) desde "Categorías".
--
-- Si preferís borrar del todo los datos de ejemplo (ya no vas a
-- necesitarlos ni de referencia), descomentá y ejecutá esto ANTES de
-- importar el resto de este archivo (es irreversible, borra también
-- cualquier venta o movimiento de stock de esos productos si llegaste
-- a probarlos):
--
-- DELETE FROM movimientos_stock WHERE producto_id IN (SELECT id FROM productos WHERE categoria_id IN (SELECT id FROM categorias WHERE nombre IN ('Fiambres','Quesos','Platos','Bebidas','Panadería')));
-- DELETE FROM pedido_items WHERE producto_id IN (SELECT id FROM productos WHERE categoria_id IN (SELECT id FROM categorias WHERE nombre IN ('Fiambres','Quesos','Platos','Bebidas','Panadería')));
-- DELETE FROM productos WHERE categoria_id IN (SELECT id FROM categorias WHERE nombre IN ('Fiambres','Quesos','Platos','Bebidas','Panadería'));
-- DELETE FROM categorias WHERE nombre IN ('Fiambres','Quesos','Platos','Panadería');
-- =====================================================================

-- Necesario: la Picada "El Batará" tiene un nombre de 203 caracteres,
-- más largo que el límite original de la columna (150). Este ALTER es
-- seguro de correr aunque ya esté aplicado (no borra datos).
ALTER TABLE productos MODIFY COLUMN nombre VARCHAR(255) NOT NULL;

-- También de esta ronda: permite agregar una nota manual al cerrar caja.
-- Si ya corriste este archivo una vez, comentá la siguiente línea antes
-- de volver a importarlo (si no, da error de columna duplicada).
ALTER TABLE caja_sesiones ADD COLUMN nota VARCHAR(500) NULL AFTER cerrada_en;

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

