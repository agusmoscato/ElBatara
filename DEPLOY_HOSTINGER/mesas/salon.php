<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();

$pdo = obtenerConexion();

// capacidad = 0 identifica la mesa placeholder histórica "Para Llevar": se
// excluye de la grilla porque hoy el botón "Para llevar" de arriba ya cubre
// ese flujo y arma el pedido con canal = 'mostrador' correctamente. Dejar
// visible esa mesa duplicaba el camino y, si alguien la tocaba, el pedido
// quedaba creado con mesa_id (canal = 'mesa') aunque en los hechos era para
// llevar, ensuciando el reporte de canal de la ronda 8.
$mesas = $pdo->query('SELECT * FROM mesas WHERE activo = 1 AND capacidad > 0 ORDER BY nombre')->fetchAll();

// Para cada mesa ocupada, buscamos el pedido abierto (id, hora de
// apertura y estado) para poder linkear directo, mostrar hace cuánto
// está abierta, y distinguir visualmente "todavía en cocina" de
// "ya entregado, falta cobrar".
$pedidosAbiertosPorMesa = [];
$stmt = $pdo->query("SELECT id, mesa_id, creado_en, estado FROM pedidos WHERE estado IN ('abierto', 'en_preparacion', 'entregado')");
foreach ($stmt->fetchAll() as $fila) {
    if ($fila['mesa_id']) {
        $pedidosAbiertosPorMesa[$fila['mesa_id']] = $fila;
    }
}

$tituloPagina = 'Salón';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Salón</h2>
  <a href="../pedidos/nuevo.php?para_llevar=1" class="btn btn-dark btn-lg-touch">Para llevar</a>
</div>

<div class="row g-3">
  <?php foreach ($mesas as $m): ?>
    <?php
      $pedidoAbierto = $pedidosAbiertosPorMesa[$m['id']] ?? null;
      if ($pedidoAbierto) {
          $href = '../pedidos/nuevo.php?pedido_id=' . (int)$pedidoAbierto['id'];
          // La mesa se pinta según el estado del pedido, no del campo
          // "estado" de la mesa: rojo mientras el pedido está en curso
          // (abierto/en preparación), y el mismo tono ámbar que ya se
          // usaba para "cuenta pedida" cuando el pedido ya fue
          // entregado y solo falta cobrarlo.
          if ($pedidoAbierto['estado'] === 'entregado') {
              $claseEstado = 'mesa-cuenta_pedida';
              $textoEstado = 'Entregado';
          } else {
              $claseEstado = 'mesa-ocupada';
              $textoEstado = 'Ocupada';
          }
      } else {
          $href = '../pedidos/nuevo.php?mesa_id=' . (int)$m['id'];
          $claseEstado = 'mesa-' . $m['estado'];
          $textoEstado = ucfirst(str_replace('_', ' ', $m['estado']));
      }
    ?>
    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
      <a href="<?= h($href) ?>" class="mesa-card <?= $claseEstado ?>">
        <?= h($m['nombre']) ?>
        <span class="mesa-estado-texto"><?= h($textoEstado) ?></span>
        <?php if ($pedidoAbierto): ?>
          <span class="mesa-tiempo">⏱ <?= h(formatearDuracionDesde($pedidoAbierto['creado_en'])) ?></span>
        <?php elseif ((int)$m['capacidad'] > 0): ?>
          <span class="mesa-tiempo"><?= (int)$m['capacidad'] ?> personas</span>
        <?php endif; ?>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
