<?php
/**
 * Paginación y filtro por fecha reutilizables para listados largos.
 * Mismo parámetro GET ("pagina") y mismo HTML en toda pantalla que liste
 * muchas filas (ver MEMORY.md) — no resolver cada pantalla con un enfoque
 * distinto.
 */

const FILAS_POR_PAGINA = 30;

/** Lee y valida el número de página actual desde $_GET['pagina']. */
function obtenerPaginaActual(): int
{
    $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $pagina === false ? 1 : $pagina;
}

/** Offset SQL correspondiente a la página actual. */
function calcularOffset(int $pagina, int $porPagina = FILAS_POR_PAGINA): int
{
    return ($pagina - 1) * $porPagina;
}

/**
 * Genera los links "‹ Anterior" / "Siguiente ›" preservando cualquier otro
 * parámetro GET ya presente en la URL (filtros de fecha, texto, etc.), para
 * no perder el filtro aplicado al cambiar de página. Devuelve '' si todo
 * entra en una sola página (no hace falta mostrar nada).
 */
function renderPaginacion(int $pagina, int $totalFilas, int $porPagina = FILAS_POR_PAGINA): string
{
    $totalPaginas = max(1, (int)ceil($totalFilas / $porPagina));
    if ($totalPaginas <= 1) {
        return '';
    }

    $construirUrl = function (int $p): string {
        $params = $_GET;
        $params['pagina'] = $p;
        return '?' . http_build_query($params);
    };

    $html = '<nav class="d-flex justify-content-between align-items-center mt-3 no-imprimir">';
    $html .= '<div class="text-muted">Página ' . $pagina . ' de ' . $totalPaginas . ' (' . $totalFilas . ' resultados)</div>';
    $html .= '<div class="btn-group">';
    $html .= $pagina > 1
        ? '<a class="btn btn-outline-secondary" href="' . h($construirUrl($pagina - 1)) . '">‹ Anterior</a>'
        : '<button class="btn btn-outline-secondary" disabled>‹ Anterior</button>';
    $html .= $pagina < $totalPaginas
        ? '<a class="btn btn-outline-secondary" href="' . h($construirUrl($pagina + 1)) . '">Siguiente ›</a>'
        : '<button class="btn btn-outline-secondary" disabled>Siguiente ›</button>';
    $html .= '</div></nav>';
    return $html;
}

/**
 * Lee un parámetro GET de fecha (formato YYYY-MM-DD) y lo valida con una
 * expresión regular simple antes de usarlo en una query (capa extra de
 * sanidad — las queries ya usan prepared statements, esto solo evita pasar
 * basura como filtro). Si falta o no matchea el formato, devuelve $fallback.
 */
function obtenerFechaGet(string $clave, string $fallback): string
{
    $valor = $_GET[$clave] ?? $fallback;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : $fallback;
}
