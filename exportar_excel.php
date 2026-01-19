<?php
require 'vendor/autoload.php';
include('includes/db.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$inicio = $_GET['inicio'] ?? null;
$fin = $_GET['fin'] ?? null;

if (!$inicio || !$fin) {
    die("Fechas inválidas");
}

$sql = $conn->query("
    SELECT 
        v.id, 
        p.nombre AS producto, 
        p.precio_compra,
        p.precio_venta,
        v.cantidad_vendida, 
        v.fecha_venta
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) BETWEEN '$inicio' AND '$fin'
    ORDER BY v.fecha_venta DESC
");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// === TITULO ===
$sheet->setCellValue("A1", "REPORTE DE VENTAS ($inicio a $fin)");
$sheet->mergeCells("A1:F1");
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A1")->getAlignment()->setHorizontal('center');

// === ENCABEZADOS ===
$encabezados = [
    "ID", 
    "Producto", 
    "Precio Compra", 
    "Precio Venta", 
    "Cantidad", 
    "Fecha de Venta"
];

$columnas = ["A", "B", "C", "D", "E", "F"];

foreach ($columnas as $i => $col) {
    $sheet->setCellValue($col . "3", $encabezados[$i]);

    // Estilo del encabezado
    $sheet->getStyle($col . "3")->applyFromArray([
        "font" => ["bold" => true, "color" => ["rgb" => "FFFFFF"]],
        "fill" => [
            "fillType" => Fill::FILL_SOLID,
            "startColor" => ["rgb" => "4CAF50"]
        ],
        "borders" => [
            "allBorders" => [
                "borderStyle" => Border::BORDER_THIN,
                "color" => ["rgb" => "000000"]
            ]
        ]
    ]);

    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// === RELLENAR DATOS ===
$fila = 4;

while ($row = $sql->fetch_assoc()) {
    $sheet->setCellValue("A$fila", $row['id']);
    $sheet->setCellValue("B$fila", $row['producto']);
    $sheet->setCellValue("C$fila", "$" . number_format($row['precio_compra'], 2));
    $sheet->setCellValue("D$fila", "$" . number_format($row['precio_venta'], 2));
    $sheet->setCellValue("E$fila", $row['cantidad_vendida']);
    $sheet->setCellValue("F$fila", $row['fecha_venta']);

    // Bordes
    foreach ($columnas as $col) {
        $sheet->getStyle("$col$fila")->applyFromArray([
            "borders" => [
                "allBorders" => [
                    "borderStyle" => Border::BORDER_THIN,
                    "color" => ["rgb" => "777777"]
                ]
            ]
        ]);
    }

    $fila++;
}

$filename = "ReporteVentas_$inicio-$fin.xlsx";

// Encabezados para descarga
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
