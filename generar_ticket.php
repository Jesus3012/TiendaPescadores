<?php
require_once('includes/fpdf.php');
include('includes/db.php');
include('includes/session.php');

$id_venta = intval($_GET['id'] ?? 0);
if ($id_venta <= 0) {
    die("Venta no válida");
}

// Obtener datos de la venta
$q = $conn->query("
    SELECT v.id, v.cantidad_vendida, v.fecha_venta, v.id_producto,
           p.nombre AS producto, p.precio_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE v.id = $id_venta
");
$venta = $q->fetch_assoc();
if (!$venta) die("No se encontró la venta.");

// Cálculos
$subtotal = $venta['cantidad_vendida'] * $venta['precio_venta'];
$iva = $subtotal * 0.16;
$total = $subtotal + $iva;

// Crear carpeta si no existe
if (!file_exists('tickets')) mkdir('tickets', 0777, true);

// Crear PDF tamaño ticket térmico (80 mm)
$pdf = new FPDF('P', 'mm', array(80, 180));
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);

// === ENCABEZADO CON LOGO ===
if (file_exists('includes/logo.png')) {
    $pdf->Image('includes/logo.png', 25, 5, 30); // centrado
    $pdf->Ln(25);
}

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 6, utf8_decode('Tienda Pescadores'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, utf8_decode('RFC: PESC123456789'), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode('Av. Principal #45, Col. Centro'), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode('Tel: 222-555-8899'), 0, 1, 'C');
$pdf->Ln(3);

// Línea decorativa
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.2);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(3);

// === INFORMACIÓN DEL TICKET ===
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, utf8_decode('TICKET DE COMPRA'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Folio: ' . $venta['id'], 0, 1, 'C');
$pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i:s', strtotime($venta['fecha_venta'])), 0, 1, 'C');
$pdf->Cell(0, 5, utf8_decode('Atendido por: ') . ($_SESSION['nombre_usuario'] ?? 'Vendedor'), 0, 1, 'C');
$pdf->Ln(3);

// Línea decorativa
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(4);

// === DETALLE DE PRODUCTOS ===
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(32, 5, utf8_decode('Producto'), 0, 0, 'L');
$pdf->Cell(10, 5, utf8_decode('Cant'), 0, 0, 'C');
$pdf->Cell(15, 5, utf8_decode('P.U.'), 0, 0, 'C');
$pdf->Cell(18, 5, utf8_decode('Importe'), 0, 1, 'R');

$pdf->SetFont('Arial', '', 9);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(2);

// Producto
$nombreProducto = utf8_decode($venta['producto']);
if (strlen($nombreProducto) > 18) {
    $nombreProducto = substr($nombreProducto, 0, 18) . '...';
}

$pdf->Cell(32, 5, $nombreProducto, 0, 0, 'L');
$pdf->Cell(10, 5, $venta['cantidad_vendida'], 0, 0, 'C');
$pdf->Cell(15, 5, number_format($venta['precio_venta'], 2), 0, 0, 'C');
$pdf->Cell(18, 5, number_format($subtotal, 2), 0, 1, 'R');

$pdf->Ln(3);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(4);

// === TOTALES ===
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(45, 5, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(25, 5, '$' . number_format($subtotal, 2), 0, 1, 'R');
$pdf->Cell(45, 5, 'IVA (16%):', 0, 0, 'R');
$pdf->Cell(25, 5, '$' . number_format($iva, 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 7, 'TOTAL:', 0, 0, 'R');
$pdf->Cell(25, 7, '$' . number_format($total, 2), 0, 1, 'R');

$pdf->Ln(3);
$pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
$pdf->Ln(4);

// === PIE DE PÁGINA ===
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 5, utf8_decode("Gracias por su compra.\nConserve este ticket como comprobante."), 0, 'C');
$pdf->Ln(3);
$pdf->Cell(0, 5, utf8_decode('¡Vuelva pronto!'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, utf8_decode('Generado el ' . date('d/m/Y H:i')), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, utf8_decode('Sistema de Ventas Tienda Pescadores © 2025'), 0, 1, 'C');

// === GUARDAR PDF ===
$file = 'tickets/ticket_' . $venta['id'] . '.pdf';
$pdf->Output('F', $file);

echo "<script>alert(' Ticket profesional generado correctamente');window.location.href='dashboard_vendedor.php';</script>";
?>
