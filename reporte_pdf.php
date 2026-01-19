<?php
require 'includes/fpdf.php';
include 'includes/db.php';

$proveedor = $_GET['proveedor'] ?? '';
if (!$proveedor) die('Proveedor no especificado');

/* ================= CLASE PDF ================= */
class PDF extends FPDF {
    function Footer(){
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(120,120,120);
        $this->Cell(0,10,
            utf8_decode('Sistema Tienda Pescadores | Página '.$this->PageNo()),
            0,0,'C'
        );
    }
}

$pdf = new PDF('L','mm','A4');
$pdf->AddPage();

/* ================= ENCABEZADO ================= */
$pdf->SetFillColor(33,37,41);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',18);
$pdf->Cell(0,14,utf8_decode('REPORTE INTEGRAL DE PROVEEDOR'),0,1,'C',true);

$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,utf8_decode("Proveedor: $proveedor"),0,1,'C',true);
$pdf->Cell(0,8,"Generado: ".date('d/m/Y H:i'),0,1,'C',true);

$pdf->Ln(8);
$pdf->SetTextColor(0,0,0);

/* ================= RESUMEN ================= */
$resumen = $conn->prepare("
    SELECT 
        SUM(rp.ventas) total_unidades,
        SUM(rp.ventas * p.precio_venta) total_importe,
        MAX(rp.fecha_conteo) ultima_fecha
    FROM reporte_proveedor rp
    INNER JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
");
$resumen->bind_param("s",$proveedor);
$resumen->execute();
$r = $resumen->get_result()->fetch_assoc();

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(233,236,239);
$pdf->Cell(90,10,'TOTAL UNIDADES',1,0,'C',true);
$pdf->Cell(60,10,'IMPORTE TOTAL',1,0,'C',true);
$pdf->Cell(60,10,'ULTIMO CONTEO',1,1,'C',true);

$pdf->SetFont('Arial','',12);
$pdf->Cell(90,12,$r['total_unidades'] ?? 0,1,0,'C');
$pdf->Cell(60,12,'$'.number_format($r['total_importe'] ?? 0,2),1,0,'C');
$pdf->Cell(60,12,$r['ultima_fecha'] ?? 'N/A',1,1,'C');

$pdf->Ln(10);

/* ================= DETALLE VENTAS ================= */
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Detalle de Ventas por Conteo',0,1);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(52,58,64);
$pdf->SetTextColor(255,255,255);

$pdf->Cell(40,8,'Folio',1,0,'C',true);
$pdf->Cell(65,8,'Producto',1,0,'C',true);
$pdf->Cell(20,8,'Ventas',1,0,'C',true);
$pdf->Cell(30,8,'P. Compra',1,0,'C',true);
$pdf->Cell(30,8,'P. Venta',1,0,'C',true);
$pdf->Cell(30,8,'Ganancia total',1,0,'C',true);
$pdf->Cell(35,8,'Fecha Conteo',1,1,'C',true);

$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);

$qVentas = $conn->prepare("
    SELECT 
        rp.ventas,
        rp.fecha_conteo,
        p.nombre,
        p.precio_compra,
        p.precio_venta,
        (
            SELECT v.folio_ticket
            FROM ventas v
            WHERE v.id_producto = rp.producto_id
            ORDER BY v.fecha_venta DESC
            LIMIT 1
        ) folio
    FROM reporte_proveedor rp
    INNER JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
    ORDER BY rp.fecha_conteo DESC
");
$qVentas->bind_param("s",$proveedor);
$qVentas->execute();
$rVentas = $qVentas->get_result();

$fill = false;
$totalCompra = 0;
$totalVenta  = 0;

while($row = $rVentas->fetch_assoc()){

    $totalCompraFila = $row['ventas'] * $row['precio_compra'];
    $totalVentaFila  = $row['ventas'] * $row['precio_venta'];

    $totalCompra += $totalCompraFila;
    $totalVenta  += $totalVentaFila;
    $ganancia = $totalVenta - $totalCompra;

    $folio = $row['folio'] ?? 'SIN-FOLIO';

    $pdf->SetFillColor($fill ? 248 : 255,249,250);

    $pdf->Cell(40,8,$folio,1,0,'C',$fill);
    $pdf->Cell(65,8,utf8_decode($row['nombre']),1,0,'L',$fill);
    $pdf->Cell(20,8,$row['ventas'],1,0,'C',$fill);
    $pdf->Cell(30,8,'$'.number_format($row['precio_compra'],2),1,0,'R',$fill);
    $pdf->Cell(30,8,'$'.number_format($row['precio_venta'],2),1,0,'R',$fill);
    $pdf->Cell(30,8,'$'.number_format($ganancia,2),1,0,'R',$fill);
    $pdf->Cell(35,8,$row['fecha_conteo'],1,1,'C',$fill);

    $fill = !$fill;
}


$pdf->Ln(10);

/* ================= CONTROL STOCK ================= */
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Control de Stock por Producto',0,1);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(52,58,64);
$pdf->SetTextColor(255,255,255);

$pdf->Cell(80,8,'Producto',1,0,'C',true);
$pdf->Cell(30,8,'Stock Inicial',1,0,'C',true);
$pdf->Cell(30,8,'Stock Contado',1,0,'C',true);
$pdf->Cell(25,8,'Ventas',1,0,'C',true);
$pdf->Cell(40,8,'Fecha Conteo',1,1,'C',true);

$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0,0,0);

$qStock = $conn->prepare("
    SELECT rp.stock_inicial, rp.stock_contado, rp.ventas, rp.fecha_conteo, p.nombre
    FROM reporte_proveedor rp
    INNER JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
    ORDER BY rp.fecha_conteo DESC
");
$qStock->bind_param("s",$proveedor);
$qStock->execute();
$rStock = $qStock->get_result();

$fill = false;
while($row = $rStock->fetch_assoc()){
    $pdf->SetFillColor($fill ? 248 : 255,249,250);

    $pdf->Cell(80,8,utf8_decode($row['nombre']),1,0,'L',$fill);
    $pdf->Cell(30,8,$row['stock_inicial'],1,0,'C',$fill);
    $pdf->Cell(30,8,$row['stock_contado'],1,0,'C',$fill);
    $pdf->Cell(25,8,$row['ventas'],1,0,'C',$fill);
    $pdf->Cell(40,8,$row['fecha_conteo'],1,1,'C',$fill);

    $fill = !$fill;
}

$pdf->Ln(8);
$pdf->SetFont('Arial','I',9);
$pdf->Cell(0,8,utf8_decode('Reporte generado automáticamente - Sistema Tienda Pescadores'),0,1,'C');

$pdf->Output();
