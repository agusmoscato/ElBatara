-- =====================================================================
-- MIGRACIÓN RONDA 17 — Perfiles de acceso configurables (reemplaza el
-- ENUM fijo admin/empleado por permisos que el dueño arma a mano).
--
-- Ejecutar esto UNA VEZ en tu base de datos de producción (la misma que
-- ya tenés en Hostinger), desde phpMyAdmin -> pestaña "SQL" (pegar y
-- ejecutar completo, de arriba a abajo, en una sola pasada).
--
-- Qué hace, en orden:
--   1) Crea la tabla `perfiles` (un booleano por permiso) y la carga con
--      dos perfiles iniciales que reproducen exactamente el alcance que
--      ya tenían "admin" y "empleado": "Administrador" con los 9
--      permisos activados, "Empleado" sin ninguno.
--   2) Agrega `usuarios.perfil_id` (sin la FK todavía) y asigna a cada
--      usuario existente el perfil que corresponde según su `rol` actual.
--   3) Recién ahí agrega la FK `usuarios.perfil_id -> perfiles.id`, una
--      vez que todas las filas ya están pobladas (si se agregara antes,
--      fallaría con las filas que todavía tienen perfil_id NULL... en
--      este caso no debería pasar porque el paso 2 cubre los dos únicos
--      valores de `rol` posibles, pero se deja el orden así por las
--      dudas de que la base tenga algún usuario con `rol` distinto a los
--      dos esperados).
--   La columna `usuarios.rol` (ENUM) NO se borra — queda como dato
--   histórico sin uso, el código ya no la lee desde esta ronda.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Tabla perfiles + seed
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS perfiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ver_caja TINYINT(1) NOT NULL DEFAULT 0,
    ver_reportes TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_productos TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_categorias TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_mesas TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_medios_pago TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_egresos_categorias TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_usuarios TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_perfiles TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO perfiles (nombre, activo, ver_caja, ver_reportes, gestionar_productos, gestionar_categorias, gestionar_mesas, gestionar_medios_pago, gestionar_egresos_categorias, gestionar_usuarios, gestionar_perfiles)
SELECT 'Administrador', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM perfiles WHERE nombre = 'Administrador');
INSERT INTO perfiles (nombre, activo, ver_caja, ver_reportes, gestionar_productos, gestionar_categorias, gestionar_mesas, gestionar_medios_pago, gestionar_egresos_categorias, gestionar_usuarios, gestionar_perfiles)
SELECT 'Empleado', 1, 0, 0, 0, 0, 0, 0, 0, 0, 0 WHERE NOT EXISTS (SELECT 1 FROM perfiles WHERE nombre = 'Empleado');

-- ---------------------------------------------------------------------
-- 2) usuarios.perfil_id (sin FK todavía) + asignación según rol actual
-- ---------------------------------------------------------------------
ALTER TABLE usuarios ADD COLUMN perfil_id INT UNSIGNED NULL AFTER rol;

UPDATE usuarios u JOIN perfiles p ON p.nombre = 'Administrador'
    SET u.perfil_id = p.id WHERE u.rol = 'admin';
UPDATE usuarios u JOIN perfiles p ON p.nombre = 'Empleado'
    SET u.perfil_id = p.id WHERE u.rol = 'empleado';

-- ---------------------------------------------------------------------
-- 3) FK, recién ahora que las filas ya están pobladas
-- ---------------------------------------------------------------------
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_perfil FOREIGN KEY (perfil_id) REFERENCES perfiles(id);
