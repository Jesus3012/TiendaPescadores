<?php
include '../includes/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$folio = $input['folio'] ?? null;

if (!$folio) {
    echo json_encode(['success' => false, 'message' => 'Se requiere folio']);
    exit;
}

$conn->begin_transaction();

try {

    // 1. Obtener ventas del folio
    $q = $conn->prepare("
        SELECT v.id, v.id_producto, v.cantidad_vendida,
               p.cantidad AS stock_actual,
               p.proveedor
        FROM ventas v
        JOIN productos p ON v.id_producto = p.id
        WHERE v.folio_ticket = ?
    ");
    $q->bind_param("s", $folio);
    $q->execute();
    $res = $q->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('No se encontraron ventas');
    }

    $ventas = $res->fetch_all(MYSQLI_ASSOC);

    // 2. Evitar doble cancelación
    $ver = $conn->prepare("SELECT id FROM ventas_canceladas WHERE folio_ticket = ?");
    $ver->bind_param("s", $folio);
    $ver->execute();
    if ($ver->get_result()->num_rows > 0) {
        throw new Exception('Venta ya cancelada');
    }

    foreach ($ventas as $v) {

        // 3. Restaurar stock
        $nuevoStock = $v['stock_actual'] + $v['cantidad_vendida'];
        $upStock = $conn->prepare("UPDATE productos SET cantidad = ? WHERE id = ?");
        $upStock->bind_param("ii", $nuevoStock, $v['id_producto']);
        $upStock->execute();

        // 4. Ajustar reporte_proveedor (RESTAR ventas)
        $rep = $conn->prepare("
            UPDATE reporte_proveedor
            SET ventas = GREATEST(ventas - ?, 0)
            WHERE producto_id = ?
              AND proveedor = ?
              AND DATE(fecha_conteo) = CURDATE()
        ");
        $rep->bind_param(
            "iis",
            $v['cantidad_vendida'],
            $v['id_producto'],
            $v['proveedor']
        );
        $rep->execute();

        //  4.1 RECONSTRUIR PEDIDOS SEGÚN REPORTE_PROVEEDOR REAL DEL DÍA
        $rp = $conn->prepare("
            SELECT ventas
            FROM reporte_proveedor
            WHERE producto_id = ?
            AND proveedor = ?
            AND DATE(fecha_conteo) = CURDATE()
        ");
        $rp->bind_param("is", $v['id_producto'], $v['proveedor']);
        $rp->execute();
        $resRp = $rp->get_result()->fetch_assoc();

        $ventasRealesHoy = $resRp['ventas'] ?? 0;

        // Buscar último pedido del producto
        $pedido = $conn->prepare("
            SELECT id
            FROM pedidos
            WHERE id_producto = ?
            ORDER BY fecha DESC
            LIMIT 1
        ");
        $pedido->bind_param("i", $v['id_producto']);
        $pedido->execute();
        $resPedido = $pedido->get_result();

        if ($resPedido->num_rows > 0) {
            $p = $resPedido->fetch_assoc();

            $updPedido = $conn->prepare("
                UPDATE pedidos
                SET cantidad_pedida = ?, faltante = ?
                WHERE id = ?
            ");
            $updPedido->bind_param(
                "iii",
                $ventasRealesHoy,
                $ventasRealesHoy,
                $p['id']
            );
            $updPedido->execute();
        }

        // 5. Registrar cancelación
        $motivo = "Cancelación total del ticket";
        $ins = $conn->prepare("
            INSERT INTO ventas_canceladas
            (folio_ticket, id_venta, cantidad_devuelta, motivo, fecha_cancelacion)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $ins->bind_param(
            "siis",
            $folio,
            $v['id'],
            $v['cantidad_vendida'],
            $motivo
        );
        $ins->execute();

        // 6. Eliminar venta
        $del = $conn->prepare("DELETE FROM ventas WHERE id = ?");
        $del->bind_param("i", $v['id']);
        $del->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Venta cancelada correctamente y stock restaurado.'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
