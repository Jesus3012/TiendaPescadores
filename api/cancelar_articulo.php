<?php
include '../includes/db.php';
require_once('../includes/fpdf.php');

header('Content-Type: application/json');

// -------------------------------------------------------
// 1. Leer JSON del POST
// -------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);

$folio = $input['folio'] ?? null;
$producto = $input['producto'] ?? null;
$motivo = $input['motivo'] ?? 'Cancelación';

// Validar
if (!$folio || !$producto) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos. Parámetros requeridos: folio, producto.']);
    exit;
}

// -------------------------------------------------------
// 2. Buscar el artículo dentro del ticket por nombre
// -------------------------------------------------------
$sql = $conn->prepare("
    SELECT v.id, v.id_producto, v.cantidad_vendida, p.nombre
    FROM ventas v
    JOIN productos p ON p.id = v.id_producto
    WHERE v.folio_ticket = ? AND p.nombre = ?
    LIMIT 1
");
$sql->bind_param("ss", $folio, $producto);
$sql->execute();
$venta = $sql->get_result()->fetch_assoc();

if (!$venta) {
    echo json_encode(['success' => false, 'message' => 'No se encontró el producto dentro del ticket.']);
    exit;
}

$idVenta = $venta['id'];
$idProducto = $venta['id_producto'];
$cantidadVendida = $venta['cantidad_vendida'];

// -------------------------------------------------------
// 3. Verificar si ya fue cancelada antes
// -------------------------------------------------------
$ver = $conn->prepare("SELECT id FROM ventas_canceladas WHERE id_venta = ?");
$ver->bind_param("i", $idVenta);
$ver->execute();
$resVer = $ver->get_result();

if ($resVer->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Este artículo ya fue cancelado anteriormente.']);
    exit;
}

// -------------------------------------------------------
// 4. Restaurar stock
// -------------------------------------------------------
$p = $conn->prepare("SELECT cantidad FROM productos WHERE id = ?");
$p->bind_param("i", $idProducto);
$p->execute();
$prod = $p->get_result()->fetch_assoc();

if ($prod) {
    $nuevoStock = $prod['cantidad'] + $cantidadVendida;
    $upd = $conn->prepare("UPDATE productos SET cantidad = ? WHERE id = ?");
    $upd->bind_param("ii", $nuevoStock, $idProducto);
    $upd->execute();
}

// -------------------------------------------------------
// 5. Registrar cancelación
// -------------------------------------------------------
$ins = $conn->prepare("
    INSERT INTO ventas_canceladas (id_venta, motivo, fecha_cancelacion)
    VALUES (?, ?, NOW())
");
$ins->bind_param("is", $idVenta, $motivo);
$ins->execute();

// -------------------------------------------------------
// 6. Eliminar SOLO ese artículo de la venta
// -------------------------------------------------------
$del = $conn->prepare("DELETE FROM ventas WHERE id = ?");
$del->bind_param("i", $idVenta);
$del->execute();

// -------------------------------------------------------
// 7. Consultar artículos restantes del ticket
// -------------------------------------------------------
$q = $conn->prepare("
    SELECT v.*, p.nombre, p.precio_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE v.folio_ticket = ?
");
$q->bind_param("s", $folio);
$q->execute();
$resRemain = $q->get_result();

if ($resRemain->num_rows == 0) {
    echo json_encode(['success' => true, 'message' => 'Artículo cancelado. No quedan productos en el ticket.']);
    exit;
}

// -------------------------------------------------------
// 8. Regenerar ticket PDF
// -------------------------------------------------------
// ==================== TICKET ====================
$carrito = [];
$total = 0;

while ($r = $resRemain->fetch_assoc()) {
    $carrito[] = $r;
    $total += $r['precio_venta'] * $r['cantidad_vendida'];
}

$subtotal = $total / 1.16;
$iva = $total - $subtotal;

if (!is_dir('../tickets')) mkdir('../tickets', 0777, true);

$rutaArchivo = "../tickets/ticket_$folio.pdf";

// Alto dinámico según productos
$pdf = new FPDF('P', 'mm', array(80, 180 + count($carrito) * 6));
$pdf->AddPage();
$pdf->SetMargins(5,5,5);

// ========== LOGO CENTRADO ==========
if (file_exists('../includes/logo.png')) {

    $anchoLogo = 20;                // tamaño del logo
    $anchoPagina = $pdf->GetPageWidth(); 
    $x = ($anchoPagina - $anchoLogo) / 2;  // centrado perfecto

    $pdf->Image('../includes/logo.png', $x, 4, $anchoLogo);
    $pdf->Ln(20);
}

// ========== ENCABEZADO ==========
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,6,utf8_decode('TIENDA PESCADORES'),0,1,'C');

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,4,utf8_decode('Nombre de la calle #123'),0,1,'C');
$pdf->Cell(0,4,utf8_decode('Ciudad, Estado'),0,1,'C');
$pdf->Cell(0,4,utf8_decode('RFC: PESC123456789'),0,1,'C');

$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== DATOS GENERALES ==========
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,5,'Folio: '.$folio,0,1,'C');
$pdf->Cell(0,5,'Fecha: '.date('d/m/Y H:i:s'),0,1,'C');

$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== ENCABEZADO DE COLUMNAS ==========
$pdf->SetFont('Arial','B',9);
$pdf->Cell(30,5,'Producto',0,0);
$pdf->Cell(10,5,'Cant',0,0,'C');
$pdf->Cell(15,5,'P.U.',0,0,'C');
$pdf->Cell(20,5,'Total',0,1,'R');

$pdf->SetFont('Arial','',9);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== PRODUCTOS ==========
foreach ($carrito as $p) {

    $nombre = utf8_decode($p['nombre']);
    if (strlen($nombre) > 18) $nombre = substr($nombre, 0, 18) . '...';

    $pdf->Cell(30,6,$nombre,0,0);
    $pdf->Cell(10,6,$p['cantidad_vendida'],0,0,'C');
    $pdf->Cell(15,6,number_format($p['precio_venta'],2),0,0,'C');
    $pdf->Cell(20,6,number_format($p['cantidad_vendida'] * $p['precio_venta'],2),0,1,'R');
}

// Separador
$pdf->Ln(3);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== TOTALES ==========
$pdf->SetFont('Arial','',9);
$pdf->Cell(45,5,'Subtotal:',0,0,'R');
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,5,'$'.number_format($subtotal,2),0,1,'R');

$pdf->SetFont('Arial','',9);
$pdf->Cell(45,5,'IVA (16%):',0,0,'R');
$pdf->SetFont('Arial','B',9);
$pdf->Cell(25,5,'$'.number_format($iva,2),0,1,'R');

$pdf->Ln(2);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(45,7,'TOTAL:',0,0,'R');
$pdf->Cell(25,7,'$'.number_format($total,2),0,1,'R');

// ========== MENSAJE FINAL ==========
$pdf->Ln(4);
$pdf->SetFont('Arial','I',8);
$pdf->Cell(0,5,utf8_decode('Gracias por tu compra.'),0,1,'C');
$pdf->Cell(0,5,'Ticket actualizado tras cancelacion',0,1,'C');

$pdf->Ln(3);

$pdf->Output('F',$rutaArchivo);

$conn->query("UPDATE ventas SET ticket_pdf='ticket_$folio.pdf' WHERE folio_ticket='$folio'");

echo json_encode(['success' => true, 'message' => 'Ticket generado correctamente y stock restaurado.']);
