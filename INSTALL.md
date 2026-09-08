# Instalación en Hostinger

## Estructura del proyecto

```
ElBataraSistema/
├── DEPLOY_HOSTINGER/    <- ESTO es lo que se sube a Hostinger (ver LEEME_PRIMERO.txt adentro)
├── database.sql         <- se importa aparte desde phpMyAdmo (no se sube al hosting)
├── INSTALL.md           <- este archivo (referencia detallada)
└── _dev_no_subir/       <- código fuente de desarrollo, NO subir nunca a Hostinger
```

**La guía rápida y oficial para subir el sistema es [`DEPLOY_HOSTINGER/LEEME_PRIMERO.txt`](DEPLOY_HOSTINGER/LEEME_PRIMERO.txt).** Seguila paso a paso: ahí está todo lo necesario para copiar el contenido de `DEPLOY_HOSTINGER/` dentro de `public_html` y dejar el sistema funcionando.

Este archivo (`INSTALL.md`) queda como referencia más detallada para diagnóstico de problemas y para el backup de la base de datos (ver más abajo). Los pasos 1 a 4 de acá abajo están pensados para el caso ideal (config/includes fuera de `public_html`, con FTP o acceso a nivel superior); si tu plan de hosting **no** te da ese acceso (como es el caso más común en Hostinger con el Administrador de Archivos), usá directamente `DEPLOY_HOSTINGER/`, que ya viene resuelto para subir todo dentro de `public_html` con `config/` e `includes/` protegidos por `.htaccess`.

## Paso 1: Crear la base de datos

1. Entrá a **hPanel** de Hostinger.
2. Andá a **Bases de datos → Bases de datos MySQL**.
3. Creá una nueva base de datos. Anotá:
   - Nombre de la base de datos
   - Usuario
   - Contraseña
   - Host (generalmente `localhost`)

## Paso 2: Importar el script SQL

1. En hPanel, abrí **phpMyAdmin** (desde la sección de Bases de datos).
2. Seleccioná la base de datos que creaste.
3. Andá a la pestaña **Importar**.
4. Seleccioná el archivo `database.sql` de este proyecto y ejecutá la importación.
5. Verificá que se hayan creado las tablas: `usuarios`, `categorias`, `productos`, `mesas`, `pedidos`, `pedido_items`, `movimientos_stock`, `caja_sesiones`.

El script ya incluye dos usuarios de ejemplo:

| Usuario    | Contraseña | Rol       |
|------------|------------|-----------|
| `admin`    | `123456`   | admin     |
| `empleado` | `123456`   | empleado  |

**Importante:** cambiá estas contraseñas apenas puedas ingresar al sistema, usando el botón "Cambiar contraseña" que aparece arriba a la derecha una vez logueado.

## Paso 3: Configurar `config/config.php`

Abrí `config/config.php` y completá con los datos reales de tu base de datos:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tu_base_de_datos');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

También podés cambiar `NOMBRE_NEGOCIO` por el nombre real de tu local.

Antes de subir a producción, asegurate de que `MODO_DESARROLLO` esté en `false` (así no se muestran errores técnicos a los clientes).

## Paso 4: Subir los archivos

### Por Administrador de Archivos de hPanel (más simple)

1. Andá a **Archivos → Administrador de archivos**.
2. Entrá a la carpeta raíz de tu hosting (normalmente vas a ver una carpeta llamada `public_html`).
3. **Subí el contenido de la carpeta `public_html/` de este proyecto DENTRO de `public_html/` del hosting** (no la carpeta en sí, sino todo lo que está adentro: `login.php`, `dashboard.php`, `assets/`, `productos/`, etc.).
4. Subí las carpetas `config/` e `includes/` de este proyecto **al mismo nivel que `public_html`** (es decir, un nivel arriba, fuera de la carpeta pública). En la mayoría de los planes de Hostinger esto es posible ya que `public_html` es una subcarpeta dentro del directorio del usuario.

### Por FTP (FileZilla u otro cliente)

1. Conectate con los datos FTP que te da Hostinger (hPanel → Avanzado → FTP).
2. En el servidor vas a ver algo como `/home/tu_usuario/public_html`.
3. Subí el contenido de `public_html/` del proyecto dentro de `/home/tu_usuario/public_html/`.
4. Subí las carpetas `config/` e `includes/` dentro de `/home/tu_usuario/` (al lado de `public_html`, no adentro).

**Si tu plan de hosting no te permite crear carpetas fuera de `public_html`:** subí `config/` e `includes/` también dentro de `public_html/` (al mismo nivel que los demás archivos). El sistema va a funcionar igual, y quedan protegidas por los archivos `.htaccess` que ya incluyen ambas carpetas (bloquean el acceso directo por navegador). Avisame si necesitás esta variante y te ayudo a ajustar las rutas si hiciera falta.

## Paso 5: Verificar la versión de PHP

1. En hPanel, andá a **Avanzado → PHP Configuration** (o "Selector de PHP").
2. Seleccioná una versión **PHP 7.4 o superior** (idealmente PHP 8.1+).
3. Guardá los cambios.

## Paso 6: Probar el sistema

1. Entrá a tu dominio (ej: `https://tudominio.com`).
2. Deberías ver la pantalla de login.
3. Ingresá con el usuario `admin` / contraseña `123456`.
4. Revisá que las categorías y productos de ejemplo aparezcan correctamente.
5. Probá abrir la caja, tomar un pedido de prueba en una mesa, y cerrarlo.

## Flujo de estados de un pedido

```
   [Nuevo pedido]
        |
        v
    ABIERTO  ---------------------------+
        |                               |
        | "Enviar a cocina"             |
        v                               |
  EN_PREPARACION  --------------------+ |
        |                             | |
        | "Marcar entregado"          | |
        | (tocando el boton celeste)  | |
        v                             | |
    ENTREGADO                         | |
        |                             | |
        +-----------------------------+-+
        |
        | "Cobrar / Cerrar" (se puede cobrar
        |  desde CUALQUIERA de los 3 estados
        |  de arriba, no hace falta pasar por
        |  "Entregado")
        v
     CERRADO  (venta registrada, aparece en reportes)


  CANCELADO: puede pasar desde ABIERTO, EN_PREPARACION o
  ENTREGADO en cualquier momento (botón "Cancelar pedido").
  Devuelve el stock de los productos cargados. Una vez CERRADO
  ya no se puede cancelar.
```

Cada cambio de estado relevante para auditoría (quién cobró, quién
canceló, quién marcó como entregado, y cuándo) queda registrado en la
tabla `pedidos` y es visible en **Reportes → Auditoría de pedidos**
(solo admin).

## Problemas comunes

- **Pantalla en blanco:** activá temporalmente `MODO_DESARROLLO` en `true` dentro de `config/config.php` para ver el error real, y volvé a ponerlo en `false` cuando lo resuelvas.
- **"No se pudo conectar a la base de datos":** revisá que `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` sean exactamente los que te dio Hostinger (a veces el host no es `localhost` sino algo como `srv123.hostinger.com`, revisalo en el panel de la base de datos).
- **Los estilos no cargan:** verificá que la carpeta `assets/` se haya subido completa dentro de `public_html/`.

## Backup de la base de datos

### Backup manual (recomendado hacerlo al menos una vez por semana)

1. Entrá a **hPanel → Bases de datos → phpMyAdmin**.
2. Seleccioná tu base de datos en el panel izquierdo.
3. Andá a la pestaña **Exportar**.
4. Dejá el método "Rápido" y el formato "SQL", y hacé clic en **Continuar**.
5. Se descarga un archivo `.sql` con toda la base de datos (productos, pedidos, ventas, usuarios, etc.). Guardalo en un lugar seguro (Google Drive, disco externo, etc.) con la fecha en el nombre, por ejemplo `backup_2026-09-07.sql`.

Para restaurar un backup, es el proceso inverso: phpMyAdmin → pestaña **Importar** → seleccionar el archivo `.sql` guardado.

### Backup automático con cron job (si tu plan de Hostinger lo permite)

Los planes de Hostinger que incluyen **Cron Jobs** (en hPanel → Avanzado → Cron Jobs) permiten programar una tarea que exporte la base de datos periódicamente usando `mysqldump` por línea de comandos. Esto requiere acceso SSH o que el plan permita ejecutar comandos desde el cron job de hPanel. Un ejemplo de comando a programar (una vez por día, de madrugada):

```bash
mysqldump -h localhost -u TU_USUARIO -pTU_PASSWORD TU_BASE_DE_DATOS > /home/TU_USUARIO/backups/backup_$(date +\%Y-\%m-\%d).sql
```

Notas importantes si configurás esto:

- Reemplazá `TU_USUARIO`, `TU_PASSWORD` y `TU_BASE_DE_DATOS` por los datos reales de tu base.
- Guardá los backups en una carpeta **fuera de `public_html`** (por ejemplo `/home/TU_USUARIO/backups/`) para que no queden accesibles por navegador.
- Sumá una limpieza periódica (por ejemplo, borrar backups de más de 30 días) para no llenar el espacio en disco del hosting.
- No todos los planes de Hostinger dan acceso a `mysqldump` por cron sin SSH habilitado; si tu plan no lo permite, el backup manual desde phpMyAdmin es la alternativa más simple y confiable.
