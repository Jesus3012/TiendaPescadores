<?php
include 'includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_POST['ventas'], $_POST['stock_final'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Datos incompletos'
    ]);
    exit;
}

$conn->begin_transaction();

try {

    /* ================= FOLIO ================= */
    $folioTicket = 'VENTA_' . uniqid();
    $idVendedor = $_SESSION['id_usuario'] ?? null;

    foreach ($_POST['ventas'] as $idProducto => $cantidadVendida) {

        $idProducto      = (int)$idProducto;
        $cantidadVendida = (int)$cantidadVendida;
        $stockFinal      = (int)$_POST['stock_final'][$idProducto];

        /* ===== PRODUCTO ===== */
        $q = $conn->prepare("
            SELECT proveedor, cantidad 
            FROM productos 
            WHERE id = ?
        ");
        $q->bind_param("i", $idProducto);
        $q->execute();
        $prod = $q->get_result()->fetch_assoc();

        if (!$prod) {
            throw new Exception('Producto no encontrado');
        }

        $proveedor    = $prod['proveedor'];
        $stockInicial = (int)$prod['cantidad'];

        /* ===== REGISTRAR VENTA ===== */
        if ($cantidadVendida > 0) {

            $stmt = $conn->prepare("
                INSERT INTO ventas
                (folio_ticket, id_producto, id_vendedor, cantidad_vendida, fecha_venta)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "siii",
                $folioTicket,
                $idProducto,
                $idVendedor,
                $cantidadVendida
            );

            $stmt->execute();
        }

        /* ===== REPORTE POR PROVEEDOR (POR DÍA) ===== */
        $rep = $conn->prepare("
            INSERT INTO reporte_proveedor
            (proveedor, producto_id, stock_inicial, stock_contado, ventas, fecha_conteo)
            VALUES (?, ?, ?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE
                stock_contado = VALUES(stock_contado),
                ventas        = IFNULL(ventas, 0) + VALUES(ventas),
                stock_inicial = VALUES(stock_inicial)
        ");

        $rep->bind_param(
            "siiii",
            $proveedor,
            $idProducto,
            $stockInicial,
            $stockFinal,
            $cantidadVendida
        );

        $rep->execute();

        /* ===== ACTUALIZAR STOCK ===== */
        $up = $conn->prepare("
            UPDATE productos
            SET cantidad = ?
            WHERE id = ?
        ");
        $up->bind_param("ii", $stockFinal, $idProducto);
        $up->execute();
    }

    $conn->commit();

    echo json_encode([
        'status' => 'ok',
        'folio'  => $folioTicket
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}
