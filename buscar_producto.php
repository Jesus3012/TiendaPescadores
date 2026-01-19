<?php
include('includes/db.php');

$codigo = $_GET['codigo'] ?? '';

if ($codigo === '') {
    echo json_encode(['success' => false, 'message' => 'Código vacío']);
    exit;
}

// Buscar producto por código de barras o ID
$result = $conn->query("
    SELECT p.id, p.nombre, p.precio_venta, p.cantidad AS stock, p.imagen
    FROM productos p
    JOIN codigos_barras c ON c.producto_id = p.id
    WHERE c.codigo = '$codigo' AND c.disponible = 1
");

if ($result && $result->num_rows > 0) {
    $producto = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'precio_venta' => $producto['precio_venta'],
        'stock' => $producto['stock'],
        'imagen' => $producto['imagen'] ?: 'uploads/noimage.png'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Producto no encontrado o sin stock.']);
}
?>
