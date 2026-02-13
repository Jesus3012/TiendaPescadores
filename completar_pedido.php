<?php
include 'includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$folio = $data['folio'];

$conn->query("
    UPDATE pedidos
    SET estado = 'completado',
        cantidad_pedida = 0,
        faltante = 0
    WHERE id_orden = $folio
");

echo json_encode(['ok'=>true]);
