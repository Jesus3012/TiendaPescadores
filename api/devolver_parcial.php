<?php
ob_clean();
include '../includes/db.php';
require_once('../includes/fpdf.php');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$folio = $conn->real_escape_string($input['folio'] ?? '');
$id_producto = intval($input['id_producto'] ?? 0);
$cantidad_devuelta = intval($input['cantidad'] ?? 0);
$motivo = $conn->real_escape_string($input['motivo'] ?? '');

if (!$folio || $id_producto <= 0 || $cantidad_devuelta <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos. Parámetros requeridos: folio, id_producto, cantidad.']);
    exit;
}

// === 1. Obtener venta específica ===
$stmt = $conn->prepare("
    SELECT id, cantidad_vendida, correo_cliente 
    FROM ventas 
    WHERE folio_ticket = ? AND id_producto = ?
");
$stmt->bind_param("si", $folio, $id_producto);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(['success'=>false, 'message'=>'Artículo no encontrado en este ticket']);
    exit;
}

$venta = $res->fetch_assoc();
$id_venta = $venta['id'];
$cantidad_vendida = $venta['cantidad_vendida'];
$correo = $venta['correo_cliente'];

// === 2. Validar cantidad ===
if ($cantidad_devuelta > $cantidad_vendida) {
    echo json_encode(['success'=>false, 'message'=>'La cantidad excede lo vendido.']);
    exit;
}

// === 3. Registrar devolución ===
$conn->query("
CREATE TABLE IF NOT EXISTS devoluciones_parciales(
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT,
    cantidad_devuelta INT,
    motivo VARCHAR(255),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
");

$stmt2 = $conn->prepare("
    INSERT INTO devoluciones_parciales (id_venta, cantidad_devuelta, motivo)
    VALUES (?, ?, ?)
");
$stmt2->bind_param("iis", $id_venta, $cantidad_devuelta, $motivo);
$stmt2->execute();

// === 4. Actualizar cantidad vendida ===
$nueva_cantidad = $cantidad_vendida - $cantidad_devuelta;

if ($nueva_cantidad > 0) {
    $stmt3 = $conn->prepare("UPDATE ventas SET cantidad_vendida = ? WHERE id = ?");
    $stmt3->bind_param("ii", $nueva_cantidad, $id_venta);
    $stmt3->execute();
} else {
    // si quedó en 0, eliminar fila de ventas
    $conn->query("DELETE FROM ventas WHERE id = $id_venta");
}

// === 5. Restaurar stock ===
$stmt4 = $conn->prepare("UPDATE productos SET cantidad = cantidad + ? WHERE id = ?");
$stmt4->bind_param("ii", $cantidad_devuelta, $id_producto);
$stmt4->execute();

// === 5.1 ACTUALIZAR PEDIDOS (LA PARTE QUE FALTABA) ===
// Buscar el pedido más reciente de este producto
$pedido = $conn->query("
    SELECT id, cantidad_pedida, faltante
    FROM pedidos
    WHERE id_producto = $id_producto
    ORDER BY fecha DESC
    LIMIT 1
")->fetch_assoc();

if($pedido){
    $nueva_cantidad_pedida = $pedido['cantidad_pedida'] - $cantidad_devuelta;
    if($nueva_cantidad_pedida < 0) $nueva_cantidad_pedida = 0;

    $nuevo_faltante = $pedido['faltante'] - $cantidad_devuelta;
    if($nuevo_faltante < 0) $nuevo_faltante = 0;

    $conn->query("
        UPDATE pedidos
        SET cantidad_pedida = $nueva_cantidad_pedida,
            faltante = $nuevo_faltante
        WHERE id = {$pedido['id']}
    ");
}


// === 6. Revisar si quedan artículos ===
$q2 = $conn->prepare("
    SELECT v.*, p.nombre, p.precio_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE folio_ticket = ?
");
$q2->bind_param("s", $folio);
$q2->execute();
$rest = $q2->get_result();

if ($rest->num_rows == 0) {
    echo json_encode(['success'=>true, 'message'=>'Devolución realizada. Ticket vacío.']);
    exit;
}

// === 7. Regenerar PDF (DISEÑO PREMIUM) ===
$carrito = [];
$total = 0;

while ($r = $rest->fetch_assoc()) {
    $carrito[] = $r;
    $total += $r['precio_venta'] * $r['cantidad_vendida'];
}

$subtotal = $total / 1.16;
$iva = $total - $subtotal;

if (!is_dir('../tickets')) mkdir('../tickets', 0777, true);
$ruta = "../tickets/ticket_$folio.pdf";

// Tamaño dinámico
$alto = 160 + (count($carrito) * 8);
$pdf = new FPDF('P','mm',array(80,$alto));
$pdf->AddPage();
$pdf->SetMargins(5,3,5);

// ====== ENCABEZADO ======
// tamaño del logo
$anchoLogo = 20;

// Cargar ancho de la página (80mm si usas ticket chico)
$anchoPagina = $pdf->GetPageWidth();

// Calcular posición centrada
$x = ($anchoPagina - $anchoLogo) / 2;

// Colocar logo centrado
$pdf->Image('../includes/logo.png', $x, 4, $anchoLogo);

// Mover hacia abajo después del logo
$pdf->Ln(18);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,utf8_decode('TIENDA PESCADORES'),0,1,'C');

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,5,'RFC: PESC123456789',0,1,'C');
$pdf->Cell(0,5,'Direccion: Calle Falsa 123, Puebla',0,1,'C');
$pdf->Cell(0,5,'Tel: 222-555-0000',0,1,'C');

$pdf->Ln(3);

// Línea divisora
$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ====== INFO DEL TICKET ======
$pdf->SetFont('Arial','B',9);
$pdf->Cell(0,5,'Folio: '.$folio,0,1,'L');

$pdf->SetFont('Arial','',9);
$pdf->Cell(0,5,'Fecha: '.date('d/m/Y H:i:s'),0,1,'L');
$pdf->Cell(0,5,'Cliente: '.$correo,0,1,'L');

$pdf->Ln(3);

// Línea divisora
$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ====== TABLA DE PRODUCTOS ======
$pdf->SetFont('Arial','B',9);
$pdf->Cell(40,5,'Producto',0,0);
$pdf->Cell(8,5,'Cant',0,0,'C');
$pdf->Cell(12,5,'P.U.',0,0,'R');
$pdf->Cell(15,5,'Importe',0,1,'R');

$pdf->SetFont('Arial','',9);

foreach ($carrito as $p) {
    $pdf->Cell(40,6,utf8_decode(substr($p['nombre'],0,20)),0,0);
    $pdf->Cell(8,6,$p['cantidad_vendida'],0,0,'C');
    $pdf->Cell(12,6,'$'.number_format($p['precio_venta'],2),0,0,'R');
    $pdf->Cell(15,6,'$'.number_format($p['precio_venta'] * $p['cantidad_vendida'],2),0,1,'R');
}

// Línea divisora
$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ====== TOTALES ======
$pdf->SetFont('Arial','',9);
$pdf->Cell(45,6,'Subtotal:',0,0,'R');
$pdf->Cell(20,6,'$'.number_format($subtotal,2),0,1,'R');

$pdf->Cell(45,6,'IVA 16%:',0,0,'R');
$pdf->Cell(20,6,'$'.number_format($iva,2),0,1,'R');

$pdf->SetFont('Arial','B',11);
$pdf->Cell(45,7,'TOTAL:',0,0,'R');
$pdf->Cell(20,7,'$'.number_format($total,2),0,1,'R');

$pdf->Ln(4);

// Línea divisora
$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ====== MENSAJE FINAL ======
$pdf->SetFont('Arial','I',8);
$pdf->Cell(0,5,'Gracias por su compra',0,1,'C');
$pdf->Cell(0,5,'Ticket actualizado tras devolucion parcial',0,1,'C');
$pdf->Cell(0,5,utf8_decode('¡Vuelva pronto!'),0,1,'C');

// Guardar PDF
$pdf->Output('F',$ruta);

// Guardar referencia en DB
$conn->query("UPDATE ventas SET ticket_pdf = 'ticket_$folio.pdf' WHERE folio_ticket = '$folio'");

echo json_encode(['success'=>true, 'message'=>'Devolución parcial realizada y ticket actualizado.']);
