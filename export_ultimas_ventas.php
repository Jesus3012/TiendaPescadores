<?php
ob_start(); // 🔥 CRÍTICO: evita Excel corrupto

require_once "includes/db.php";
require_once "vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

/* ===============================
   CONSULTAS
=============================== */

/* Ventas detalladas */
$ventas = $conn->query("
    SELECT p.nombre, v.cantidad_vendida, v.fecha_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    ORDER BY v.fecha_venta DESC
    LIMIT 50
");

/* Top productos */
$top = $conn->query("
    SELECT p.nombre, SUM(v.cantidad_vendida) total
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    GROUP BY p.nombre
    ORDER BY total DESC
    LIMIT 5
");

/* Comparativo mensual */
$mensual = $conn->query("
    SELECT DATE_FORMAT(fecha_venta,'%Y-%m') mes,
           SUM(cantidad_vendida) total
    FROM ventas
    GROUP BY mes
    ORDER BY mes DESC
    LIMIT 6
");

/* ===============================
   CREAR EXCEL
=============================== */
$spreadsheet = new Spreadsheet();

/* ==================================================
   HOJA 1 — VENTAS + KPI
================================================== */
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ventas');

/* TÍTULO */
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'REPORTE DE VENTAS');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

/* ENCABEZADOS */
$sheet->fromArray(['Producto', 'Cantidad', 'Fecha'], null, 'A3');
$sheet->getStyle('A3:C3')->applyFromArray([
    'font' => ['bold' => true,'color'=>['rgb'=>'FFFFFF']],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FF7B00']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$fila = 4;
$total = 0;
$ventasTotales = 0;

while ($r = $ventas->fetch_assoc()) {
    $sheet->setCellValue("A$fila", $r['nombre']);
    $sheet->setCellValue("B$fila", $r['cantidad_vendida']);
    $sheet->setCellValue("C$fila", date('d/m/Y', strtotime($r['fecha_venta'])));
    $total += $r['cantidad_vendida'];
    $ventasTotales++;
    $fila++;
}

/* TOTAL */
$sheet->mergeCells("A$fila:B$fila");
$sheet->setCellValue("A$fila", "TOTAL VENDIDO");
$sheet->setCellValue("C$fila", $total);
$sheet->getStyle("A$fila:C$fila")->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF3CD']],
]);

/* KPI */
$sheet->fromArray(['KPI','VALOR'], null, 'F3');
$sheet->getStyle('F3:G3')->applyFromArray([
    'font'=>['bold'=>true],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'0D6EFD']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
]);

$sheet->setCellValue('F4','Ventas registradas');
$sheet->setCellValue('G4',$ventasTotales);
$sheet->setCellValue('F5','Productos vendidos');
$sheet->setCellValue('G5',$total);

foreach (['A','B','C','F','G'] as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

/* ==================================================
   HOJA 2 — RESUMEN + GRÁFICAS
================================================== */
$exec = $spreadsheet->createSheet();
$exec->setTitle('Resumen Ejecutivo');

/* TÍTULO */
$exec->mergeCells('A1:E1');
$exec->setCellValue('A1','RESUMEN CORTO');
$exec->getStyle('A1')->applyFromArray([
    'font'=>['bold'=>true,'size'=>18],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
]);

/* TOP PRODUCTOS */
$exec->fromArray(['Producto','Total'],null,'A3');
$exec->getStyle('A3:B3')->applyFromArray([
    'font'=>['bold'=>true],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'198754']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
]);

$r = 4;
while($t = $top->fetch_assoc()){
    $exec->setCellValue("A$r",$t['nombre']);
    $exec->setCellValue("B$r",$t['total']);
    $r++;
}
$lastTop = $r - 1;

/* COMPARATIVO MENSUAL */
$exec->fromArray(['Mes','Ventas'],null,'D3');
$exec->getStyle('D3:E3')->applyFromArray([
    'font'=>['bold'=>true],
    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFC107']],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
]);

$m = 4;
while($mo = $mensual->fetch_assoc()){
    $exec->setCellValue("D$m",$mo['mes']);
    $exec->setCellValue("E$m",$mo['total']);
    $m++;
}
$lastMes = $m - 1;

/* ===============================
   GRÁFICA TOP PRODUCTOS
=============================== */
$labels = [new DataSeriesValues('String', "'Resumen Ejecutivo'!B3", null, 1)];
$categories = [new DataSeriesValues('String', "'Resumen Ejecutivo'!A4:A$lastTop", null, $lastTop-3)];
$values = [new DataSeriesValues('Number', "'Resumen Ejecutivo'!B4:B$lastTop", null, $lastTop-3)];

$series = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, count($values)-1),
    $labels,
    $categories,
    $values
);

$chartTop = new Chart(
    'TopProductos',
    new Title('Top Productos'),
    new Legend(Legend::POSITION_RIGHT),
    new PlotArea(null, [$series])
);

$chartTop->setTopLeftPosition('G3');
$chartTop->setBottomRightPosition('O15');
$exec->addChart($chartTop);

/* ===============================
   GRÁFICA COMPARATIVO MENSUAL
=============================== */
$labels = [new DataSeriesValues('String', "'Resumen Ejecutivo'!E3", null, 1)];
$categories = [new DataSeriesValues('String', "'Resumen Ejecutivo'!D4:D$lastMes", null, $lastMes-3)];
$values = [new DataSeriesValues('Number', "'Resumen Ejecutivo'!E4:E$lastMes", null, $lastMes-3)];

$series2 = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, count($values)-1),
    $labels,
    $categories,
    $values
);

$chartMes = new Chart(
    'VentasMensuales',
    new Title('Comparativo Mensual'),
    new Legend(Legend::POSITION_RIGHT),
    new PlotArea(null, [$series2])
);

$chartMes->setTopLeftPosition('G17');
$chartMes->setBottomRightPosition('O30');
$exec->addChart($chartMes);

/* ===============================
   DESCARGA
=============================== */
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Reporte_Ventas.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true); // Incluir gráficas
$writer->save('php://output');
exit;
