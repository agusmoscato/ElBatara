<?php
/**
 * Endpoint JSON consultado por polling desde cocina/panel.php (cada
 * 5-8 segundos) para refrescar la pantalla sin recargar la página.
 * Cualquier usuario logueado puede consultarlo (mismo criterio de acceso
 * que panel.php, ver comentario ahí) — no requiere ningún permiso
 * puntual, a diferencia de las pantallas de ABM.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirLogin();
header('Content-Type: application/json; charset=utf-8');

$pdo = obtenerConexion();

$stmt = $pdo->query("SELECT pi.id, pi.pedido_id, pi.producto_id, pi.cantidad, pi.estado_cocina, pi.enviado_cocina_en,
                             p.nombre AS producto_nombre, p.tipo_venta,
                             ped.mesa_id, ped.canal, ped.creado_en AS pedido_creado_en,
                             m.nombre AS mesa_nombre
                      FROM pedido_items pi
                      JOIN productos p ON p.id = pi.producto_id
                      JOIN pedidos ped ON ped.id = pi.pedido_id
                      LEFT JOIN mesas m ON m.id = ped.mesa_id
                      WHERE pi.estado_cocina IN ('enviado', 'listo')
                        AND ped.estado IN ('abierto', 'cuenta_pedida')
                      ORDER BY ped.id, pi.id");
$filas = $stmt->fetchAll();

$pedidosSalida = [];
foreach ($filas as $f) {
    $pedidoId = (int)$f['pedido_id'];
    if (!isset($pedidosSalida[$pedidoId])) {
        $pedidosSalida[$pedidoId] = [
            'pedido_id' => $pedidoId,
            'mesa_nombre' => $f['mesa_nombre'] ?? 'Para llevar',
            'canal' => $f['canal'],
            'items' => [],
        ];
    }
    $pedidosSalida[$pedidoId]['items'][] = [
        'item_id' => (int)$f['id'],
        'producto_nombre' => $f['producto_nombre'],
        'cantidad_texto' => formatearCantidad((float)$f['cantidad'], $f['tipo_venta']),
        'estado_cocina' => $f['estado_cocina'],
        'hace' => formatearDuracionDesde($f['enviado_cocina_en'] ?? $f['pedido_creado_en']),
    ];
}

echo json_encode([
    'ok' => true,
    'servidor_hora' => date('c'),
    'pedidos' => array_values($pedidosSalida),
]);
