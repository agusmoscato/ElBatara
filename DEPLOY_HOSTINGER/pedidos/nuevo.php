<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

$pedidoId = intPositivoONull($_GET['pedido_id'] ?? null);
$mesaId = intPositivoONull($_GET['mesa_id'] ?? null);
$paraLlevar = isset($_GET['para_llevar']);

// --- Determinar o crear el pedido sobre el que vamos a trabajar ---
if ($pedidoId) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND estado IN ('abierto', 'en_preparacion', 'entregado')");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    if (!$pedido) {
        redirigir('mesas/salon.php');
    }
} elseif ($mesaId) {
    // ¿Ya hay un pedido abierto para esta mesa?
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE mesa_id = ? AND estado IN ('abierto', 'en_preparacion', 'entregado') LIMIT 1");
    $stmt->execute([$mesaId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $stmtMesa = $pdo->prepare('SELECT * FROM mesas WHERE id = ? AND activo = 1');
        $stmtMesa->execute([$mesaId]);
        $mesa = $stmtMesa->fetch();
        if (!$mesa) {
            redirigir('mesas/salon.php');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO pedidos (mesa_id, canal, usuario_id, estado) VALUES (?, 'mesa', ?, ?)");
        $stmt->execute([$mesaId, $_SESSION['usuario_id'], 'abierto']);
        $pedidoId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?")->execute([$mesaId]);
        $pdo->commit();

        $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch();
    }
} elseif ($paraLlevar) {
    $stmt = $pdo->prepare("INSERT INTO pedidos (mesa_id, canal, usuario_id, estado) VALUES (NULL, 'mostrador', ?, ?)");
    $stmt->execute([$_SESSION['usuario_id'], 'abierto']);
    $pedidoId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
} else {
    redirigir('mesas/salon.php');
}

$pedidoId = (int)$pedido['id'];

// Datos de la mesa (si corresponde)
$mesa = null;
if ($pedido['mesa_id']) {
    $stmt = $pdo->prepare('SELECT * FROM mesas WHERE id = ?');
    $stmt->execute([$pedido['mesa_id']]);
    $mesa = $stmt->fetch();
}

// Categorías y productos activos para el grid de selección
$categorias = $pdo->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();
$productos = $pdo->query('SELECT * FROM productos WHERE activo = 1 ORDER BY nombre')->fetchAll();

// Productos más vendidos en los últimos 30 días, para accesos rápidos
// arriba de todo (evita scrollear toda la carta para lo que más se pide).
$masVendidos = $pdo->query("SELECT p.id, p.nombre, p.tipo_venta, p.precio, SUM(pi.cantidad) AS total_vendido
                             FROM pedido_items pi
                             JOIN pedidos ped ON ped.id = pi.pedido_id
                             JOIN productos p ON p.id = pi.producto_id
                             WHERE ped.estado = 'cerrado'
                               AND ped.cerrado_en >= (NOW() - INTERVAL 30 DAY)
                               AND p.activo = 1
                             GROUP BY p.id, p.nombre, p.tipo_venta, p.precio
                             ORDER BY total_vendido DESC
                             LIMIT 8")->fetchAll();

// Items actuales del pedido
$stmt = $pdo->prepare("SELECT pi.*, p.nombre AS producto_nombre, p.tipo_venta
                        FROM pedido_items pi
                        JOIN productos p ON p.id = pi.producto_id
                        WHERE pi.pedido_id = ?
                        ORDER BY pi.id");
$stmt->execute([$pedidoId]);
$items = $stmt->fetchAll();

$tituloPagina = 'Pedido';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">
    <?= $mesa ? h($mesa['nombre']) : 'Para llevar' ?>
    <span class="badge <?= $pedido['canal'] === 'mesa' ? 'bg-primary' : 'bg-secondary' ?> align-middle">
      <?= $pedido['canal'] === 'mesa' ? 'Mesa' : 'Mostrador' ?>
    </span>
  </h2>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($pedido['estado'] === 'abierto'): ?>
      <button class="btn btn-info btn-lg-touch" onclick="enviarCocina()">Enviar a cocina</button>
    <?php elseif ($pedido['estado'] === 'en_preparacion'): ?>
      <button class="btn btn-info btn-lg-touch" onclick="marcarEntregado()" title="Tocar cuando se lleve el pedido a la mesa">
        En preparación (tocar al entregar)
      </button>
    <?php elseif ($pedido['estado'] === 'entregado'): ?>
      <span class="badge bg-success align-self-center fs-6">✓ Entregado</span>
    <?php endif; ?>
    <a href="cerrar.php?pedido_id=<?= $pedidoId ?>" class="btn btn-success btn-lg-touch">Cobrar / Cerrar</a>
    <button class="btn btn-outline-danger btn-lg-touch" onclick="cancelarPedido()">Cancelar pedido</button>
    <a href="../mesas/salon.php" class="btn btn-outline-secondary btn-lg-touch">Volver al salón</a>
  </div>
</div>

<div class="row">
  <!-- Selección de productos -->
  <div class="col-md-7">
    <div class="input-group mb-3">
      <span class="input-group-text">🔎</span>
      <input type="text" id="buscadorProducto" class="form-control"
             placeholder="Buscar producto por nombre..." autocomplete="off">
      <button type="button" class="btn btn-outline-secondary" id="btnLimpiarBusqueda" title="Limpiar búsqueda">✕</button>
    </div>

    <?php if (!empty($masVendidos)): ?>
      <div class="mb-3" id="bloqueMasVendidos">
        <div class="fw-bold mb-2" style="color: var(--marca-principal-oscuro);">⭐ Más pedidos (últimos 30 días)</div>
        <div class="row g-2">
          <?php foreach ($masVendidos as $p): ?>
            <div class="col-6 col-lg-3">
              <button type="button" class="btn producto-btn"
                      onclick="agregarProducto(<?= (int)$p['id'] ?>, '<?= h(addslashes($p['nombre'])) ?>', '<?= $p['tipo_venta'] ?>', <?= (float)$p['precio'] ?>)">
                <div class="fw-bold"><?= h($p['nombre']) ?></div>
                <div class="producto-precio"><?= formatearMoneda((float)$p['precio']) ?> <?= $p['tipo_venta'] === 'peso' ? '/kg' : '' ?></div>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3" id="tabsCategorias">
      <li class="nav-item">
        <button class="nav-link" data-cat="todos" onclick="mostrarCategoria('todos', this)">
          Todos
        </button>
      </li>
      <?php foreach ($categorias as $cat): ?>
        <li class="nav-item">
          <button class="nav-link" data-cat="<?= (int)$cat['id'] ?>" onclick="mostrarCategoria(<?= (int)$cat['id'] ?>, this)">
            <?= h($cat['nombre']) ?>
          </button>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Ninguna categoría viene seleccionada por defecto: así "Más pedidos"
         nunca conviene con la grilla de una categoría al mismo tiempo, que
         era lo que generaba la confusión de "el filtro no funciona". -->
    <p id="avisoElegirCategoria" class="text-muted">👆 Elegí una categoría de arriba (o "Todos"), o buscá un producto por nombre.</p>
    <p id="estadoFiltro" class="fw-bold" style="color: var(--marca-principal-oscuro); display:none;"></p>

    <div class="row g-2" id="grillaProductos">
      <?php foreach ($categorias as $cat): ?>
        <div class="col-12 categoria-heading producto-oculto-busqueda" data-cat-heading="<?= (int)$cat['id'] ?>">
          <?= h($cat['nombre']) ?>
        </div>
        <?php foreach ($productos as $p): if ((int)$p['categoria_id'] !== (int)$cat['id']) continue; ?>
          <div class="col-6 col-lg-4 producto-cat producto-oculto-busqueda"
               data-cat="<?= (int)$cat['id'] ?>" data-nombre="<?= h(mb_strtolower($p['nombre'])) ?>">
            <button type="button" class="btn producto-btn"
                    onclick="agregarProducto(<?= (int)$p['id'] ?>, '<?= h(addslashes($p['nombre'])) ?>', '<?= $p['tipo_venta'] ?>', <?= (float)$p['precio'] ?>)">
              <div class="fw-bold"><?= h($p['nombre']) ?></div>
              <div class="producto-precio"><?= formatearMoneda((float)$p['precio']) ?> <?= $p['tipo_venta'] === 'peso' ? '/kg' : '' ?></div>
            </button>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Detalle del pedido -->
  <div class="col-md-5">
    <div class="card shadow-sm panel-pedido">
      <div class="card-body">
        <h5 class="card-title">Pedido actual</h5>
        <table class="table table-sm tabla-items-pedido">
          <thead>
            <tr>
              <th class="col-producto">Producto</th>
              <th class="col-cantidad">Cant.</th>
              <th class="col-subtotal">Subtotal</th>
              <th class="col-quitar"></th>
            </tr>
          </thead>
          <tbody id="itemsBody">
            <?php foreach ($items as $it): ?>
              <tr data-item-id="<?= (int)$it['id'] ?>">
                <td class="col-producto">
                  <span class="nombre-producto-item" title="<?= h($it['producto_nombre']) ?>"><?= h($it['producto_nombre']) ?></span>
                </td>
                <td class="col-cantidad"><?= formatearCantidad((float)$it['cantidad'], $it['tipo_venta']) ?></td>
                <td class="col-subtotal"><?= formatearMoneda((float)$it['subtotal']) ?></td>
                <td class="col-quitar">
                  <button class="btn btn-sm btn-outline-danger" onclick="quitarItem(<?= (int)$it['id'] ?>)">×</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <h4 class="text-end">Total: <span id="totalPedido"><?= formatearMoneda((float)$pedido['total']) ?></span></h4>
      </div>
    </div>
  </div>
</div>

<!-- Modal para pedir cantidad en productos por peso -->
<div class="modal fade" id="modalCantidad" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cantidad (kg)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="modalCantidadNombre" class="fw-bold"></p>
        <input type="number" id="modalCantidadValor" class="form-control" step="0.001" min="0.001" value="0.500">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="confirmarCantidad()">Agregar</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast de confirmación al agregar un producto -->
<div id="toastAgregado"></div>

<script>
const PEDIDO_ID = <?= $pedidoId ?>;
const CSRF_TOKEN = '<?= h(generarTokenCsrf()) ?>';
let productoPendiente = null;
// Al abrir el pedido NO hay ninguna categoría elegida todavía (null).
// Antes se preseleccionaba la primera categoría alfabética ("Agua") sin
// que el mozo la tocara, y como "Más pedidos" solo se ocultaba al hacer
// click en un chip, quedaban las dos cosas visibles a la vez (el
// accesos-directos de arriba con productos de cualquier categoría, y la
// grilla de Agua debajo) y parecía que el filtro por categoría no
// funcionaba. Ahora no se muestra NINGÚN producto de la grilla hasta
// que se elige una categoría o se busca por nombre, y "Más pedidos" se
// esconde apenas eso pasa.
let categoriaActivaId = null;
let modalCantidad = null;
if (window.bootstrap) {
  modalCantidad = new bootstrap.Modal(document.getElementById('modalCantidad'));
}

function mostrarCategoria(catId, btn) {
  categoriaActivaId = catId;
  document.querySelectorAll('#tabsCategorias .nav-link').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('buscadorProducto').value = '';
  aplicarFiltroProductos();
}

function aplicarFiltroProductos() {
  const termino = document.getElementById('buscadorProducto').value.trim().toLowerCase();
  const bloqueMasVendidos = document.getElementById('bloqueMasVendidos');
  const aviso = document.getElementById('avisoElegirCategoria');
  const estado = document.getElementById('estadoFiltro');
  const hayEleccion = !!(termino || categoriaActivaId);
  const viendoTodos = !termino && categoriaActivaId === 'todos';

  let cantidadVisible = 0;
  document.querySelectorAll('.producto-cat').forEach(el => {
    let visible;
    if (termino) {
      visible = el.dataset.nombre.includes(termino);
    } else if (categoriaActivaId === 'todos') {
      visible = true;
    } else if (categoriaActivaId) {
      visible = (String(el.dataset.cat) === String(categoriaActivaId));
    } else {
      visible = false;
    }
    if (visible) cantidadVisible++;
    el.classList.toggle('producto-oculto-busqueda', !visible);
  });

  // Los encabezados de categoría ("Fiambres", "Bebidas", etc.) solo se
  // muestran en la vista "Todos", para agrupar visualmente sin repetir
  // el nombre de categoría cuando ya se eligió una sola.
  document.querySelectorAll('.categoria-heading').forEach(el => {
    el.classList.toggle('producto-oculto-busqueda', !viendoTodos);
  });

  if (bloqueMasVendidos) {
    bloqueMasVendidos.style.display = hayEleccion ? 'none' : '';
  }
  if (aviso) {
    aviso.style.display = hayEleccion ? 'none' : '';
  }
  if (estado) {
    if (hayEleccion) {
      const etiqueta = termino ? `Resultados para "${termino}"` : (categoriaActivaId === 'todos' ? 'Todos los productos' : document.querySelector('#tabsCategorias .nav-link.active')?.textContent.trim());
      estado.textContent = `${etiqueta} · ${cantidadVisible} producto${cantidadVisible === 1 ? '' : 's'}`;
      estado.style.display = '';
    } else {
      estado.style.display = 'none';
    }
  }
}

document.getElementById('buscadorProducto').addEventListener('input', function () {
  if (this.value.trim()) {
    document.querySelectorAll('#tabsCategorias .nav-link').forEach(b => b.classList.remove('active'));
  }
  aplicarFiltroProductos();
});

document.getElementById('btnLimpiarBusqueda').addEventListener('click', function () {
  const input = document.getElementById('buscadorProducto');
  input.value = '';
  input.focus();
  aplicarFiltroProductos();
});

function mostrarToast(mensaje) {
  const toast = document.getElementById('toastAgregado');
  toast.textContent = mensaje;
  toast.classList.add('mostrar');
  clearTimeout(window._toastTimeout);
  window._toastTimeout = setTimeout(() => toast.classList.remove('mostrar'), 1400);
}

function agregarProducto(id, nombre, tipoVenta, precio) {
  if (tipoVenta === 'peso') {
    if (!modalCantidad) {
      alert('No se pudo abrir el selector de cantidad. Recargá la página e intentá de nuevo.');
      return;
    }
    productoPendiente = { id, nombre };
    document.getElementById('modalCantidadNombre').textContent = nombre;
    document.getElementById('modalCantidadValor').value = '0.500';
    modalCantidad.show();
  } else {
    enviarAgregarItem(id, 1, nombre);
  }
}

function confirmarCantidad() {
  const cantidad = parseFloat(document.getElementById('modalCantidadValor').value);
  if (!productoPendiente || isNaN(cantidad) || cantidad <= 0) return;
  enviarAgregarItem(productoPendiente.id, cantidad, productoPendiente.nombre);
  modalCantidad.hide();
}

function enviarAgregarItem(productoId, cantidad, nombre) {
  fetch('agregar_item.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `pedido_id=${PEDIDO_ID}&producto_id=${productoId}&cantidad=${cantidad}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  })
  .then(r => r.json())
  .then(data => {
    actualizarPedido(data);
    if (!data.error) {
      mostrarToast('✓ ' + nombre + ' agregado');
    }
  })
  .catch(() => alert('No se pudo agregar el producto.'));
}

function quitarItem(itemId) {
  fetch('quitar_item.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `pedido_id=${PEDIDO_ID}&item_id=${itemId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  })
  .then(r => r.json())
  .then(actualizarPedido)
  .catch(() => alert('No se pudo quitar el producto.'));
}

function cancelarPedido() {
  if (!confirm('¿Seguro que querés cancelar este pedido? Se va a devolver el stock de los productos cargados.')) {
    return;
  }
  const form = document.createElement('form');
  form.method = 'post';
  form.action = 'cancelar.php';
  form.innerHTML = `
    <input type="hidden" name="pedido_id" value="${PEDIDO_ID}">
    <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
  `;
  document.body.appendChild(form);
  form.submit();
}

function enviarCocina() {
  fetch('enviar_cocina.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `pedido_id=${PEDIDO_ID}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  })
  .then(() => location.reload());
}

function marcarEntregado() {
  fetch('marcar_entregado.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `pedido_id=${PEDIDO_ID}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  })
  .then(() => location.reload());
}

function actualizarPedido(data) {
  if (data.error) {
    alert(data.error);
    return;
  }
  const tbody = document.getElementById('itemsBody');
  tbody.innerHTML = '';
  data.items.forEach(it => {
    const tr = document.createElement('tr');
    tr.dataset.itemId = it.id;
    tr.innerHTML = `
      <td class="col-producto"><span class="nombre-producto-item"></span></td>
      <td class="col-cantidad"></td>
      <td class="col-subtotal"></td>
      <td class="col-quitar"><button class="btn btn-sm btn-outline-danger">×</button></td>
    `;
    const spanNombre = tr.querySelector('.nombre-producto-item');
    spanNombre.textContent = it.producto_nombre;
    spanNombre.title = it.producto_nombre;
    tr.querySelector('.col-cantidad').textContent = it.cantidad_texto;
    tr.querySelector('.col-subtotal').textContent = it.subtotal_texto;
    tr.querySelector('button').onclick = () => quitarItem(it.id);
    tbody.appendChild(tr);
  });
  document.getElementById('totalPedido').textContent = data.total_texto;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
