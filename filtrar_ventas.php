<?php
include('includes/db.php');

$inicio = $_GET['inicio'] ?? null;
$fin = $_GET['fin'] ?? null;

if (!$inicio || !$fin) {
    echo json_encode(['success' => false, 'message' => 'Fechas no válidas']);
    exit;
}

// Consulta de ventas recientes
$q = $conn->query("
    SELECT v.id, p.nombre AS producto, v.cantidad_vendida, v.fecha_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) BETWEEN '$inicio' AND '$fin'
    ORDER BY v.fecha_venta DESC
    LIMIT 10
");

$ventas = [];
while ($row = $q->fetch_assoc()) {
    $ventas[] = $row;
}

// Datos para la gráfica
$g = $conn->query("
    SELECT p.nombre AS producto, SUM(v.cantidad_vendida) AS total_vendida
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) BETWEEN '$inicio' AND '$fin'
    GROUP BY p.nombre
    ORDER BY total_vendida DESC
    LIMIT 6
");

$grafica = [];
while ($r = $g->fetch_assoc()) {
    $grafica[] = $r;
}

echo json_encode([
    'success' => true,
    'ventas' => $ventas,
    'grafica' => $grafica
]);
?>
