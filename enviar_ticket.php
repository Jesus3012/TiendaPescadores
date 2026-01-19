<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';
require 'includes/fpdf.php';
include('includes/db.php');
include('includes/session.php');

header('Content-Type: application/json');

// --- VALIDACIÓN ---
$folio = $_GET['folio'] ?? '';
if (!$folio) {
    echo json_encode(['success' => false, 'message' => 'Folio no recibido.']);
    exit;
}

// Obtener ventas del mismo folio
$sql = "
    SELECT v.id, v.folio_ticket, v.correo_cliente, v.fecha_venta,
           p.nombre, v.cantidad_vendida, p.precio_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE v.folio_ticket = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $folio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'No existe una venta con este folio.']);
    exit;
}

// Agrupar ventas
$ventas = [];
$total = 0;
$correo_cliente = "";
$fecha_venta = "";

while ($row = $result->fetch_assoc()) {
    $ventas[] = $row;
    $correo_cliente = trim($row['correo_cliente']);
    $fecha_venta = $row['fecha_venta'];
    $total += $row['cantidad_vendida'] * $row['precio_venta'];
}

if (empty($correo_cliente)) {
    echo json_encode(['success' => false, 'message' => 'La venta no tiene correo registrado.']);
    exit;
}

$subtotal = $total / 1.16;
$iva = $total - $subtotal;

// === GENERAR TICKET PDF ===
if (!is_dir('tickets')) mkdir('tickets', 0777, true);

$nombreArchivo = 'ticket_' . $folio . '.pdf';
$rutaArchivo = 'tickets/' . $nombreArchivo;

$alto = 200 + count($ventas) * 8; // Ticket más estético y amplio
$pdf = new FPDF('P', 'mm', array(80, $alto));
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);

// ========== LOGO SUPERIOR ==========
// tamaño del logo
$anchoLogo = 20;

// Cargar ancho de la página (80mm si usas ticket chico)
$anchoPagina = $pdf->GetPageWidth();

// Calcular posición centrada
$x = ($anchoPagina - $anchoLogo) / 2;

// Colocar logo centrado
$pdf->Image('includes/logo.png', $x, 4, $anchoLogo);

// Mover hacia abajo después del logo
$pdf->Ln(18);


// ========== ENCABEZADO DE TIENDA ==========
$pdf->SetFont('Arial','B',13);
$pdf->Cell(0,6,utf8_decode('TIENDA PESCADORES'),0,1,'C');

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,4,utf8_decode('Nombre de la calle #123'),0,1,'C');
$pdf->Cell(0,4,utf8_decode('Ciudad, Estado'),0,1,'C');
$pdf->Cell(0,4,utf8_decode('RFC: PESC123456789'),0,1,'C');

$pdf->Ln(2);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== INFO GENERAL ==========
$pdf->SetFont('Arial','',9);
$pdf->Cell(0,5,'FOLIO: '.$folio,0,1,'C');
$pdf->Cell(0,5,'FECHA: '.date('d/m/Y H:i:s', strtotime($fecha_venta)),0,1,'C');
$pdf->Cell(0,5,utf8_decode('CLIENTE: ').$correo_cliente,0,1,'C');

$pdf->Ln(2);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== COLUMNAS ==========
$pdf->SetFont('Arial','B',9);
$pdf->Cell(30,5,'Producto',0,0);
$pdf->Cell(10,5,'Cant',0,0,'C');
$pdf->Cell(15,5,'P.U.',0,0,'C');
$pdf->Cell(20,5,'Total',0,1,'R');

$pdf->SetFont('Arial','',9);
$pdf->Cell(0,4,str_repeat('-', 45),0,1,'C');

// ========== PRODUCTOS ==========
foreach ($ventas as $v) {

    $nombre = utf8_decode($v['nombre']);
    if (strlen($nombre) > 18) $nombre = substr($nombre, 0, 18) . '...';

    $pdf->Cell(30,6,$nombre,0,0);
    $pdf->Cell(10,6,$v['cantidad_vendida'],0,0,'C');
    $pdf->Cell(15,6,number_format($v['precio_venta'],2),0,0,'C');
    $pdf->Cell(20,6,number_format($v['cantidad_vendida'] * $v['precio_venta'],2),0,1,'R');
}

// Línea separadora
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
$pdf->SetFont('Arial','B',12);
$pdf->Cell(25,7,'$'.number_format($total,2),0,1,'R');

$pdf->Ln(4);

// Mensaje final elegante
$pdf->SetFont('Arial','I',8);
$pdf->Cell(0,5,utf8_decode('Gracias por tu compra.'),0,1,'C');
$pdf->Cell(0,5,utf8_decode('¡Vuelve pronto!'),0,1,'C');
$pdf->Ln(3);

$pdf->Output('F',$rutaArchivo);

// === ENVIAR CORREO ===
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'jesusgabrielmtz78@gmail.com';
    $mail->Password = 'iwdf uyqu erzq wvbm';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('jesusgabrielmtz78@gmail.com', 'Tienda Pescadores');
    $mail->addAddress($correo_cliente);
    $mail->Subject = "Ticket actualizado ($folio)";
    $mail->Body = "Tu ticket actualizado ha sido enviado.\n\nTotal: $" . number_format($total, 2);
    $mail->addAttachment($rutaArchivo);

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => "Ticket reenviado correctamente."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Error al enviar correo: {$mail->ErrorInfo}"
    ]);
}
