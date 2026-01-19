<?php
include '../includes/session.php';
include '../includes/db.php';

// ID del usuario logueado
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

// === FILTROS DE BÚSQUEDA ===
$producto = $_GET['producto'] ?? '';
$cliente = $_GET['cliente'] ?? '';
$inicio = $_GET['inicio'] ?? '';
$fin = $_GET['fin'] ?? '';

$where = [];

// Solo filtra por vendedor si es vendedor
if ($rol === 'vendedor') {
    $where[] = "v.id_vendedor = " . intval($usuario_id);
}

if ($producto)
    $where[] = "p.nombre LIKE '%" . $conn->real_escape_string($producto) . "%'";

if ($cliente)
    $where[] = "v.correo_cliente LIKE '%" . $conn->real_escape_string($cliente) . "%'";

if ($inicio && $fin)
    $where[] = "DATE(v.fecha_venta) BETWEEN '" . $conn->real_escape_string($inicio) . "' AND '" . $conn->real_escape_string($fin) . "'";
    
$condicion = $where ? "WHERE " . implode(" AND ", $where) : "";

// === CONSULTA PRINCIPAL ===
$sql = "
    SELECT 
        v.folio_ticket,
        v.correo_cliente,
        v.fecha_venta,
        v.ticket_pdf,
        GROUP_CONCAT(p.nombre SEPARATOR '||') AS productos,
        GROUP_CONCAT(v.cantidad_vendida SEPARATOR '||') AS cantidades,
        GROUP_CONCAT((v.cantidad_vendida * p.precio_venta) SEPARATOR '||') AS totales,
        GROUP_CONCAT(p.id SEPARATOR '||') AS ids_productos,
        SUM(v.cantidad_vendida * p.precio_venta) AS total_general
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    $condicion
    GROUP BY v.folio_ticket, v.correo_cliente, v.fecha_venta, v.ticket_pdf
    ORDER BY v.fecha_venta DESC
";

$q = $conn->query($sql);
$data = [];

if ($q) {
    while ($r = $q->fetch_assoc()) {
        $productos = explode("||", $r['productos']);
        $cantidades = explode("||", $r['cantidades']);
        $totales = explode("||", $r['totales']);
        $ids = explode("||", $r['ids_productos']);

        $items = [];
        for ($i = 0; $i < count($productos); $i++) {
            $items[] = [
                'producto' => $productos[$i],
                'id_producto' => intval($ids[$i]),
                'cantidad' => intval($cantidades[$i]),
                'total' => '$' . number_format($totales[$i], 2)
            ];
        }

        $data[] = [
            'folio_ticket' => $r['folio_ticket'],
            'correo_cliente' => $r['correo_cliente'],

            'fecha_raw' => $r['fecha_venta'],
            'fecha_venta' => date('d/m/Y H:i', strtotime($r['fecha_venta'])),
            'ticket_pdf' => $r['ticket_pdf'],
            'total_general' => '$' . number_format($r['total_general'], 2),
            'items' => $items
        ];
    }
}

echo json_encode(['data' => $data]);

