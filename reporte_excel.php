<?php
include 'includes/db.php';

$proveedor = $_GET['proveedor'] ?? '';
if (!$proveedor) die('Proveedor no especificado');

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=Reporte_Proveedor_$proveedor.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<meta charset='UTF-8'>";

/* ================= ENCABEZADO ================= */
echo "<table border='0' width='100%'>";
echo "<tr>
        <td colspan='6' bgcolor='#212529' align='center'>
            <font color='white' size='4'><b>REPORTE INTEGRAL DE PROVEEDOR</b></font>
        </td>
      </tr>";
echo "<tr>
        <td colspan='6' align='center'>
            <font>
                Proveedor: <b>$proveedor</b> | Generado: ".date('d/m/Y H:i')."
            </font>
        </td>
      </tr>";
echo "</table><br>";

/* ================= RESUMEN ================= */
$resumen = $conn->prepare("
    SELECT 
        SUM(rp.ventas) total_unidades,
        SUM(rp.ventas * p.precio_venta) total_importe,
        MAX(rp.fecha_conteo) ultima_fecha
    FROM reporte_proveedor rp
    JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
");
$resumen->bind_param("s",$proveedor);
$resumen->execute();
$r = $resumen->get_result()->fetch_assoc();

echo "<table border='1' width='100%' cellpadding='5'>";
echo "<tr bgcolor='#343a40'>
        <th><font color='white'>TOTAL UNIDADES</font></th>
        <th><font color='white'>IMPORTE TOTAL</font></th>
        <th><font color='white'>ÚLTIMO CONTEO</font></th>
      </tr>";
echo "<tr bgcolor='#f8f9fa' align='center'>
        <td>{$r['total_unidades']}</td>
        <td align='right'>$".number_format($r['total_importe'],2)."</td>
        <td>{$r['ultima_fecha']}</td>
      </tr>";
echo "</table><br>";

/* ================= DETALLE VENTAS ================= */
echo "<table border='1' width='100%' cellpadding='5'>";
echo "<tr bgcolor='#343a40'>
        <th colspan='6'><font color='white'>DETALLE DE VENTAS POR CONTEO</font></th>
      </tr>";
echo "<tr bgcolor='#6c757d'
        <th><font color='white'>Folio</font></th>
        <th><font color='white'>Producto</font></th>
        <th><font color='white'>Ventas</font></th>
        <th><font color='white'>Precio</font></th>
        <th><font color='white'>Total</font></th>
        <th><font color='white'>Fecha Conteo</font></th>
      </tr>";

$qVentas = $conn->prepare("
    SELECT 
        rp.ventas,
        rp.fecha_conteo,
        p.nombre,
        p.precio_venta,
        (
            SELECT v.folio_ticket
            FROM ventas v
            WHERE v.id_producto = rp.producto_id
            ORDER BY v.fecha_venta DESC
            LIMIT 1
        ) folio
    FROM reporte_proveedor rp
    JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
    ORDER BY rp.fecha_conteo DESC
");
$qVentas->bind_param("s",$proveedor);
$qVentas->execute();
$rVentas = $qVentas->get_result();

$alt = false;
while($row = $rVentas->fetch_assoc()){
    $subtotal = $row['ventas'] * $row['precio_venta'];
    $bg = $alt ? '#f2f2f2' : '#ffffff';

    echo "<tr bgcolor='$bg'>
            <td align='center'>".($row['folio'] ?? 'SIN-FOLIO')."</td>
            <td>{$row['nombre']}</td>
            <td align='center'>{$row['ventas']}</td>
            <td align='right'>$".number_format($row['precio_venta'],2)."</td>
            <td align='right'>$".number_format($subtotal,2)."</td>
            <td align='center'>{$row['fecha_conteo']}</td>
          </tr>";
    $alt = !$alt;
}
echo "</table><br>";

/* ================= CONTROL DE STOCK ================= */
echo "<table border='1' width='100%' cellpadding='5'>";
echo "<tr bgcolor='#343a40'
        <th colspan='5'><font color='white'>CONTROL DE INVENTARIO</font></th>
      </tr>";
echo "<tr bgcolor='#6c757d'
        <th><font color='white'>Producto</font></th>
        <th><font color='white'>Stock Inicial</font></th>
        <th><font color='white'>Stock Contado</font></th>
        <th><font color='white'>Ventas</font></th>
        <th><font color='white'>Fecha Conteo</font></th>
      </tr>";

$qStock = $conn->prepare("
    SELECT rp.stock_inicial, rp.stock_contado, rp.ventas, rp.fecha_conteo, p.nombre
    FROM reporte_proveedor rp
    JOIN productos p ON rp.producto_id = p.id
    WHERE rp.proveedor = ?
    ORDER BY rp.fecha_conteo DESC
");
$qStock->bind_param("s",$proveedor);
$qStock->execute();
$rStock = $qStock->get_result();

$alt = false;
while($row = $rStock->fetch_assoc()){
    $bg = $alt ? '#f2f2f2' : '#ffffff';
    echo "<tr bgcolor='$bg'>
            <td>{$row['nombre']}</td>
            <td align='center'>{$row['stock_inicial']}</td>
            <td align='center'>{$row['stock_contado']}</td>
            <td align='center'>{$row['ventas']}</td>
            <td align='center'>{$row['fecha_conteo']}</td>
          </tr>";
    $alt = !$alt;
}
echo "</table><br>";

echo "<table width='100%'>
        <tr>
            <td align='center'>
                <i>Reporte generado automáticamente - Sistema Tienda Pescadores</i>
            </td>
        </tr>
      </table>";
