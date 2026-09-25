<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
// Ronda 21: cualquier usuario logueado puede abrir el panel de cocina
// (empleado o admin) — es una pantalla operativa, no un ABM, así que se
// usa requerirLogin() sin requerirPermiso() (mismo criterio ya aplicado a
// Salón/POS y Stock, que MEMORY.md documenta como "sin gate de permiso,
// decisión explícita" desde la ronda 17).
requerirLogin();

$tituloPagina = 'Cocina';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="mb-0">🍳 Panel de cocina</h2>
  <div class="d-flex align-items-center gap-2">
    <span id="estadoConexion" class="text-muted small"></span>
    <button id="btnActivarSonido" type="button" class="btn btn-outline-secondary btn-sm">🔔 Activar sonido</button>
  </div>
</div>

<p class="text-muted">Se actualiza solo cada pocos segundos. Dejala abierta en la tablet de cocina.</p>

<div id="listaPedidosCocina" class="row g-3">
  <div class="col-12 text-muted" id="mensajeVacio">Cargando...</div>
</div>

<audio id="sonidoAlerta" src="../assets/sonidos/alerta_cocina.wav" preload="auto"></audio>

<script>
const CSRF_TOKEN = '<?= h(generarTokenCsrf()) ?>';
let sonidoActivado = false;
// itemsVistos: Set de item_id que ya se mostraron en algún ciclo anterior
// con estado 'enviado' — sirve para detectar cuáles son NUEVOS en el
// ciclo actual (recién enviados desde el POS) y sonar una sola vez por
// ciclo aunque hayan llegado varios ítems juntos, en vez de una cascada
// de sonidos por cada uno.
let itemsEnviadosVistos = new Set();
let primerCiclo = true;

document.getElementById('btnActivarSonido').addEventListener('click', function () {
  const audio = document.getElementById('sonidoAlerta');
  // Reproducir y pausar de inmediato, disparado por un click real del
  // usuario: esto "desbloquea" el autoplay para las reproducciones
  // futuras que dispare el JS sin interacción directa (política de
  // autoplay de los navegadores modernos).
  audio.play().then(() => {
    audio.pause();
    audio.currentTime = 0;
    sonidoActivado = true;
    this.textContent = '🔔 Sonido activado';
    this.classList.add('btn-success');
    this.classList.remove('btn-outline-secondary');
  }).catch(() => {
    mostrarErrorConexion('No se pudo activar el sonido. Probá tocar el botón de nuevo.');
  });
});

function reproducirAlerta() {
  if (!sonidoActivado) return;
  const audio = document.getElementById('sonidoAlerta');
  audio.currentTime = 0;
  audio.play().catch(() => {});
}

function mostrarErrorConexion(msg) {
  document.getElementById('estadoConexion').textContent = msg;
}

function renderPedidos(pedidos) {
  const cont = document.getElementById('listaPedidosCocina');
  cont.innerHTML = '';

  if (pedidos.length === 0) {
    const div = document.createElement('div');
    div.className = 'col-12 text-muted';
    div.id = 'mensajeVacio';
    div.textContent = 'No hay nada pendiente en cocina. 🎉';
    cont.appendChild(div);
    return;
  }

  pedidos.forEach(p => {
    const col = document.createElement('div');
    col.className = 'col-12 col-md-6 col-lg-4';

    const hayListo = p.items.some(it => it.estado_cocina === 'listo');
    const card = document.createElement('div');
    card.className = 'card shadow-sm h-100' + (hayListo ? ' border-success' : ' border-primary');

    const body = document.createElement('div');
    body.className = 'card-body';

    const titulo = document.createElement('h5');
    titulo.className = 'card-title d-flex justify-content-between align-items-center';
    titulo.innerHTML = `<span>${p.mesa_nombre}</span>`;
    body.appendChild(titulo);

    const lista = document.createElement('ul');
    lista.className = 'list-group list-group-flush mb-2';
    p.items.forEach(it => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-center px-0';
      const esListo = it.estado_cocina === 'listo';
      li.innerHTML = `
        <div>
          <span class="badge ${esListo ? 'bg-success' : 'bg-primary'} me-2">${esListo ? 'LISTO' : 'EN COCINA'}</span>
          <strong>${it.cantidad_texto} × ${it.producto_nombre}</strong>
          <div class="text-muted small">hace ${it.hace}</div>
        </div>
        ${esListo ? '' : `<button type="button" class="btn btn-sm btn-outline-success" onclick="marcarListo(${it.item_id})">Marcar listo</button>`}
      `;
      lista.appendChild(li);
    });
    body.appendChild(lista);

    const hayEnviados = p.items.some(it => it.estado_cocina === 'enviado');
    if (hayEnviados) {
      const btnGrupo = document.createElement('button');
      btnGrupo.type = 'button';
      btnGrupo.className = 'btn btn-success w-100';
      btnGrupo.textContent = '✅ Marcar todo listo';
      btnGrupo.onclick = () => marcarListoPedido(p.pedido_id);
      body.appendChild(btnGrupo);
    }

    card.appendChild(body);
    col.appendChild(card);
    cont.appendChild(col);
  });
}

function marcarListo(itemId) {
  fetch('marcar_listo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `item_id=${itemId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  }).then(() => cargarPedidos()).catch(() => mostrarErrorConexion('No se pudo marcar como listo.'));
}

function marcarListoPedido(pedidoId) {
  fetch('marcar_listo.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `pedido_id=${pedidoId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
  }).then(() => cargarPedidos()).catch(() => mostrarErrorConexion('No se pudo marcar como listo.'));
}

function cargarPedidos() {
  fetch('api.php')
    .then(r => r.json())
    .then(data => {
      if (!data.ok) {
        mostrarErrorConexion('Error al consultar cocina.');
        return;
      }
      mostrarErrorConexion('');

      // Detectar ítems 'enviado' nuevos (no vistos en el ciclo anterior)
      // para sonar UNA sola vez por ciclo, aunque hayan llegado varios
      // ítems nuevos juntos.
      const idsEnviadosAhora = new Set();
      let hayNuevos = false;
      data.pedidos.forEach(p => {
        p.items.forEach(it => {
          if (it.estado_cocina === 'enviado') {
            idsEnviadosAhora.add(it.item_id);
            if (!itemsEnviadosVistos.has(it.item_id)) {
              hayNuevos = true;
            }
          }
        });
      });

      if (hayNuevos && !primerCiclo) {
        reproducirAlerta();
      }
      itemsEnviadosVistos = idsEnviadosAhora;
      primerCiclo = false;

      renderPedidos(data.pedidos);
    })
    .catch(() => mostrarErrorConexion('Sin conexión con el servidor. Reintentando...'));
}

cargarPedidos();
setInterval(cargarPedidos, 6000);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
