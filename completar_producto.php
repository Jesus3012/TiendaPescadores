<?php
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id']; // este es el ID del renglón en pedidos

$conn->begin_transaction();

try {

    // 1️⃣ Obtener datos del producto del pedido
    $pedido = $conn->query("
        SELECT id_producto, faltante
        FROM pedidos
        WHERE id = $id
    ")->fetch_assoc();

    $id_producto = $pedido['id_producto'];
    $faltante = $pedido['faltante'];

    // 2️⃣ Sumar al inventario lo que llegó
    $conn->query("
        UPDATE productos
        SET cantidad = cantidad + $faltante
        WHERE id = $id_producto
    ");

    // 3️⃣ Marcar SOLO ese producto como completado
    $conn->query("
        UPDATE pedidos
        SET estado = 'completado',
            cantidad_pedida = 0,
            faltante = 0
        WHERE id = $id
    ");

    $conn->commit();

    echo json_encode(['ok'=>true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['ok'=>false]);
}
