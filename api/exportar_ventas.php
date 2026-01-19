<?php
include '../includes/db.php';
require_once('../includes/fpdf.php'); // para PDF

$format = $_GET['format'] ?? 'csv';
$producto = $conn->real_escape_string($_GET['producto'] ?? '');
$cliente = $conn->real_escape_string($_GET['cliente'] ?? '');
$inicio = $_GET['inicio'] ?? '';
$fin = $_GET['fin'] ?? '';

$where = [];
if ($producto) $where[] = "p.nombre LIKE '%$producto%'";
if ($cliente) $where[] = "v.correo_cliente LIKE '%$cliente%'";
if ($inicio && $fin) $where[] = "DATE(v.fecha_venta) BETWEEN '$inicio' AND '$fin'";

$condicion = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT v.id, p.nombre AS producto, v.cantidad_vendida,
           (v.cantidad_vendida * p.precio_venta) AS total,
           v.correo_cliente, v.fecha_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    $condicion
    ORDER BY v.fecha_venta DESC
";
$res = $conn->query($sql);

// CSV
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ventas_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Producto','Cantidad','Total','Cliente','Fecha']);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [$row['id'],$row['producto'],$row['cantidad_vendida'],$row['total'],$row['correo_cliente'],$row['fecha_venta']]);
    }
    fclose($out);
    exit;
}

// PDF
if ($format === 'pdf') {
    $pdf = new FPDF('L','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,utf8_decode('Exportación de Ventas'),0,1,'C');
    $pdf->Ln(4);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(20,8,'ID',1,0,'C');
    $pdf->Cell(70,8,utf8_decode('Producto'),1,0,'C');
    $pdf->Cell(30,8,'Cantidad',1,0,'C');
    $pdf->Cell(30,8,'Total',1,0,'C');
    $pdf->Cell(60,8,'Cliente',1,0,'C');
    $pdf->Cell(40,8,'Fecha',1,1,'C');
    $pdf->SetFont('Arial','',10);
    while ($row = $res->fetch_assoc()) {
        $pdf->Cell(20,8,$row['id'],1,0,'C');
        $pdf->Cell(70,8,utf8_decode($row['producto']),1,0,'L');
        $pdf->Cell(30,8,$row['cantidad_vendida'],1,0,'C');
        $pdf->Cell(30,8,number_format($row['total'],2),1,0,'R');
        $pdf->Cell(60,8,utf8_decode($row['correo_cliente']),1,0,'L');
        $pdf->Cell(40,8,$row['fecha_venta'],1,1,'C');
    }
    $file = 'tickets/export_ventas_' . time() . '.pdf';
    $pdf->Output('D','ventas_export_' . date('Ymd_His') . '.pdf'); // fuerza descarga
    exit;
}

// default
echo "Formato no soportado.";
exit;
