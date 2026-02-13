<?php
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$solicitado_por = $data['solicitado_por'];
$pedidos = $data['pedidos'];

$conn->begin_transaction();

try {

    // 1️⃣ Crear ORDEN
    $stmtOrden = $conn->prepare("
        INSERT INTO ordenes_pedido (solicitado_por)
        VALUES (?)
    ");
    $stmtOrden->bind_param("s", $solicitado_por);
    $stmtOrden->execute();

    $id_orden = $stmtOrden->insert_id;

    // Generar FOLIO
    $folio_ticket = 'PEDIDO-' . time();

    // Preparar consultas
    $stmtPedido = $conn->prepare("
        INSERT INTO pedidos 
        (id_orden, id_producto, nombre_producto, stock_actual, cantidad_pedida, faltante, solicitado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtVenta = $conn->prepare("
        INSERT INTO ventas
        (folio_ticket, id_producto, cantidad_vendida, metodo_pago)
        VALUES (?, ?, ?, 'pedido')
    ");

    $stmtStock = $conn->prepare("
        UPDATE productos
        SET cantidad = cantidad - ?
        WHERE id = ? AND cantidad >= ?
    ");

    foreach ($pedidos as $p) {

        // Registrar PEDIDO (solo informativo)
        $stmtPedido->bind_param(
            "iisiiis",
            $id_orden,
            $p['id'],
            $p['nombre'],
            $p['stock'],
            $p['pedido'],
            $p['faltante'],
            $solicitado_por
        );
        $stmtPedido->execute();

        // Registrar VENTA
        $stmtVenta->bind_param(
            "sii",
            $folio_ticket,
            $p['id'],
            $p['pedido']
        );
        $stmtVenta->execute();

        // Descontar STOCK
        $stmtStock->bind_param(
            "iii",
            $p['pedido'],
            $p['id'],
            $p['pedido']
        );
        $stmtStock->execute();

        if ($stmtStock->affected_rows === 0) {
            throw new Exception("Stock insuficiente para {$p['nombre']}");
        }
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "id_orden" => $id_orden,
        "folio_ticket" => $folio_ticket
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
