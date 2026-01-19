<?php
include('includes/header.php');
include('includes/navbar.php');
include 'includes/db.php';

// --- Filtros (proveedor y fechas) ---
$filtroProveedor = $_GET['proveedor'] ?? '';
$filtroInicio = $_GET['fecha_inicio'] ?? '';
$filtroFin = $_GET['fecha_fin'] ?? '';

// --- Consulta principal ---
$sql = "
SELECT 
    p.id,
    p.nombre,
    p.proveedor,
    p.precio_compra,
    p.precio_venta,
    p.cantidad,
    IFNULL(SUM(v.cantidad_vendida), 0) AS total_vendida
FROM productos p
LEFT JOIN ventas v ON p.id = v.id_producto
WHERE 1
";

// Aplicar filtros
if ($filtroProveedor !== '') {
    $sql .= " AND p.proveedor = '" . $conn->real_escape_string($filtroProveedor) . "'";
}

if ($filtroInicio !== '' && $filtroFin !== '') {
    $sql .= " AND DATE(v.fecha_venta) BETWEEN '" . $conn->real_escape_string($filtroInicio) . "' 
    AND '" . $conn->real_escape_string($filtroFin) . "'";
}

$sql .= " GROUP BY p.id ORDER BY p.nombre ASC";

$resultado = $conn->query($sql);

// --- Variables ---
$totalGanancia = $totalProveedor = $totalVendidos = $totalStock = 0;
$productos = [];

while ($row = $resultado->fetch_assoc()) {
    $vendidos = (int)$row['total_vendida'];
    $stock = (int)$row['cantidad']; // refleja el stock actual real



    $ganancia = ($row['precio_venta'] - $row['precio_compra']) * $vendidos;
    $costoProveedor = $row['precio_compra'] * $vendidos;

    $totalGanancia += $ganancia;
    $totalProveedor += $costoProveedor;
    $totalVendidos += $vendidos;
    $totalStock += $stock;

    $productos[] = [
        'nombre' => $row['nombre'],
        'proveedor' => $row['proveedor'],
        'vendidos' => $vendidos,
        'stock' => $stock,
        'precio_compra' => $row['precio_compra'],
        'precio_venta' => $row['precio_venta'],
        'ganancia' => $ganancia
    ];
}

// Proveedores para filtro
$listaProveedores = $conn->query("SELECT DISTINCT proveedor FROM productos WHERE proveedor != ''");
?>

<style>
    /* Hace que las tablas sean más largas y ocupen todo el ancho */
.table td, .table th {
    white-space: nowrap;
    padding: 12px 16px !important;
    font-size: 22px;
}

/*  Permite scroll horizontal sin apretar columnas */
.table-responsive {
    overflow-x: auto;
}

/*  El content-wrapper no debe cortar nada */
.content-wrapper {
    min-height: 100vh;
    padding-bottom: 40px;
}

/* Las tablas se adaptan al PDF */
table {
    width: 100% !important;
    white-space: normal !important; /* ← evita que se haga enorme */
}


</style>


<div class="content-wrapper">

    <!-- HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">

                <!-- TITULO -->
                <div class="col-12 col-md-6 mb-2 mb-md-0">
                    <h1 class="mb-0 font-weight-bold">
                        <i class="text-primary mr-2"></i>
                        Reporte de Ventas
                    </h1>
                    <small class="text-muted">
                        Resumen financiero y desempeño comercial
                    </small>
                </div>

                <!-- BOTON PDF -->
                <div class="col-12 col-md-6 text-md-right text-left">
                    <button id="btnPDF" class="btn btn-danger btn-sm shadow-sm">
                        <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
                    </button>
                </div>

            </div>
        </div>
    </section>

    <!-- MAIN -->
    <section class="content">
        <div class="container-fluid">

            <!-- FILTROS -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-filter mr-2"></i>Filtros de búsqueda
                    </h3>
                </div>

                <div class="card-body">
                    <form method="GET" class="row">

                        <div class="col-12 col-md-4 mb-2">
                            <label class="text-muted">Proveedor</label>
                            <select name="proveedor" class="form-control">
                                <option value="">Todos</option>
                                <?php while ($p = $listaProveedores->fetch_assoc()): ?>
                                    <option value="<?= $p['proveedor'] ?>" <?= $filtroProveedor == $p['proveedor'] ? 'selected' : '' ?>>
                                        <?= $p['proveedor'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3 mb-2">
                            <label class="text-muted">Fecha inicio</label>
                            <input type="date" name="fecha_inicio" value="<?= $filtroInicio ?>" class="form-control">
                        </div>

                        <div class="col-6 col-md-3 mb-2">
                            <label class="text-muted">Fecha fin</label>
                            <input type="date" name="fecha_fin" value="<?= $filtroFin ?>" class="form-control">
                        </div>

                        <div class="col-12 col-md-2">
                            <button class="btn btn-success btn-block">
                                <i class="fas fa-search mr-1"></i> Aplicar
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- KPIs -->
            <div class="row">

                <div class="col-12 col-md-6 col-lg-3 mb-3">
                    <div class="small-box bg-info shadow-sm">
                        <div class="inner">
                            <h3><?= $totalVendidos ?></h3>
                            <p class="mb-0">Productos vendidos</p>
                            <small>Total acumulado</small>
                        </div>
                        <div class="icon"><i class="fas fa-box"></i></div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3 mb-3">
                    <div class="small-box bg-danger shadow-sm">
                        <div class="inner">
                            <h3>$<?= number_format($totalProveedor, 2) ?></h3>
                            <p class="mb-0">Deuda proveedores</p>
                            <small>Por liquidar</small>
                        </div>
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3 mb-3">
                    <div class="small-box bg-success shadow-sm">
                        <div class="inner">
                            <h3>$<?= number_format($totalGanancia, 2) ?></h3>
                            <p class="mb-0">Ganancia neta</p>
                            <small>Utilidad real</small>
                        </div>
                        <div class="icon"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3 mb-3">
                    <div class="small-box bg-warning shadow-sm">
                        <div class="inner">
                            <h3><?= $totalStock ?></h3>
                            <p class="mb-0">Stock restante</p>
                            <small>Inventario actual</small>
                        </div>
                        <div class="icon"><i class="fas fa-warehouse"></i></div>
                    </div>
                </div>

            </div>

            <!-- TABLA PRODUCTOS -->
            <div class="card card-outline card-warning shadow-sm mt-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-boxes mr-2"></i>Productos y Ventas
                    </h3>
                </div>

                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0 text-nowrap">
                        <thead class="thead-dark">
                            <tr>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th class="text-center">Vendidos</th>
                                <th class="text-center">Stock</th>
                                <th class="text-right">Compra</th>
                                <th class="text-right">Venta</th>
                                <th class="text-right">Ganancia</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td><?= $p['nombre'] ?></td>
                                <td><?= $p['proveedor'] ?></td>
                                <td class="text-center"><?= $p['vendidos'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $p['stock'] <= 0 ? 'badge-danger' : ($p['stock'] <= 5 ? 'badge-warning' : 'badge-success') ?>">
                                        <?= $p['stock'] ?>
                                    </span>
                                </td>
                                <td class="text-right">$<?= number_format($p['precio_compra'], 2) ?></td>
                                <td class="text-right">$<?= number_format($p['precio_venta'], 2) ?></td>
                                <td class="text-right font-weight-bold text-success">
                                    $<?= number_format($p['ganancia'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DEUDA CON PROVEEDORES -->
            <div class="card card-outline card-danger shadow-sm mt-4">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-hand-holding-usd mr-2"></i>
                        Deuda con Proveedores
                    </h3>

                    <span class="badge badge-danger p-2 ml-md-auto">
                        Total adeudo: $<?= number_format($totalProveedor, 2) ?>
                    </span>
                </div>

                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-sm mb-0 text-nowrap">
                        <thead class="thead-dark">
                            <tr>
                                <th>Producto</th>
                                <th>Proveedor</th>
                                <th class="text-center">Vendidos</th>
                                <th class="text-right">Costo unitario</th>
                                <th class="text-right">Deuda total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($productos as $p): ?>
                            <?php $deuda = $p['precio_compra'] * $p['vendidos']; ?>
                            <tr>
                                <td><?= $p['nombre'] ?></td>
                                <td><?= $p['proveedor'] ?></td>
                                <td class="text-center"><?= $p['vendidos'] ?></td>
                                <td class="text-right">$<?= number_format($p['precio_compra'], 2) ?></td>
                                <td class="text-right font-weight-bold text-danger">
                                    $<?= number_format($deuda, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer text-right">
                    <small class="text-muted">
                        Este monto representa el total pendiente de pago a proveedores.
                    </small>
                </div>
            </div>

            <!-- GRAFICA -->
            <div class="card card-outline card-info shadow-sm mt-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-chart-pie mr-2"></i>Distribución financiera
                    </h3>
                </div>

                <div class="card-body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="graficaVentas"></canvas>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>


<!-- SCRIPTS DE CHART -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
// Gráfica

const ctx = document.getElementById('graficaVentas');

new Chart(ctx, {
    type: 'pie',
    data: {
        labels: [
            'Ganancia neta',
            'Pago a proveedores',
            'Stock (valor estimado)'
        ],
        datasets: [{
            data: [
                <?= $totalGanancia ?>,
                <?= $totalProveedor ?>,
                <?= $totalStock * 100 ?>
            ],
            backgroundColor: [
                '#28a745', // verde ganancia
                '#f4a261', // rojo proveedores
                '#e76f51'  // amarillo stock
            ],
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    boxWidth: 18,
                    font: {
                        size: 13,
                        weight: 'bold'
                    }
                }
            },

            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const value = context.raw;
                        const percent = ((value / total) * 100).toFixed(1);

                        return `${context.label}: $${value.toLocaleString()} (${percent}%)`;
                    }
                }
            }
        },

        animation: {
            animateScale: true,
            animateRotate: true,
            duration: 1200,
            easing: 'easeOutQuart'
        }
    }
});

document.getElementById("btnPDF").addEventListener("click", () => {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF("p", "mm", "a4");

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    let y = 20;

    /* =========================
       ENCABEZADO PRINCIPAL
    ========================= */


    doc.setFontSize(16);
    doc.text("REPORTE INTEGRAL DE VENTAS", pageWidth / 2, 12, { align: "center" });

    doc.setFontSize(9);
    doc.text(
        `Fecha de generación: ${new Date().toLocaleString()}`,
        pageWidth - 14,
        17,
        { align: "right" }
    );

    doc.setTextColor(0);
    y = 26;


    /* =========================
       TABLA PRODUCTOS Y VENTAS
    ========================= */
    doc.setFontSize(12);
    doc.text("Productos y Ventas", 14, y);
    y += 4;

    doc.autoTable({
        html: document.querySelector(".card-warning table"),
        startY: y,
        theme: "striped",
        headStyles: {
            fillColor: [255, 193, 7],
            textColor: 0,
            fontStyle: "bold"
        },
        styles: {
            fontSize: 8,
            cellPadding: 2
        },
        alternateRowStyles: { fillColor: [245, 245, 245] },
        margin: { left: 10, right: 10 },
        didDrawPage: data => y = data.cursor.y + 8
    });

    /* =========================
       RESUMEN GENERAL (CAJA)
    ========================= */
    if (y > 230) {
        doc.addPage();
        y = 20;
    }

    doc.setFillColor(248, 249, 250);
    doc.rect(12, y - 5, pageWidth - 24, 40, "F");

    doc.setFontSize(12);
    doc.text("Resumen General", 14, y);
    y += 6;

    doc.setFontSize(9);

    const resumen = [
        ["Total vendidos", "<?= $totalVendidos ?>"],
        ["Total a proveedores", "$<?= number_format($totalProveedor, 2) ?>"],
        ["Ganancia neta", "$<?= number_format($totalGanancia, 2) ?>"],
        ["Stock restante", "<?= $totalStock ?>"]
    ];

    resumen.forEach(item => {
        doc.text(`• ${item[0]}`, 20, y);
        doc.text(item[1], pageWidth - 20, y, { align: "right" });
        y += 7;
    });

    y += 6;

    /* =========================
       TABLA DEUDA PROVEEDORES
    ========================= */
    if (y > 220) {
        doc.addPage();
        y = 20;
    }

    doc.setFontSize(12);
    doc.text("Deuda con Proveedores", 14, y);
    y += 4;

    doc.autoTable({
        html: document.querySelector(".card-danger table"),
        startY: y,
        theme: "striped",
        headStyles: {
            fillColor: [220, 53, 69],
            textColor: 255,
            fontStyle: "bold"
        },
        styles: {
            fontSize: 8,
            cellPadding: 2
        },
        alternateRowStyles: { fillColor: [248, 248, 248] },
        margin: { left: 10, right: 10 },
        didDrawPage: data => y = data.cursor.y + 8
    });

    /* =========================
       GRÁFICA
    ========================= */
    if (y > 200) {
        doc.addPage();
        y = 20;
    }

    doc.setFontSize(12);
    doc.text(
        "Distribución de Costos y Ganancias",
        pageWidth / 2,
        y,
        { align: "center" }
    );
    y += 6;

    const canvas = document.getElementById("graficaVentas");
    const imgData = canvas.toDataURL("image/png");

    const imgWidth = 160;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    doc.addImage(
        imgData,
        "PNG",
        (pageWidth - imgWidth) / 2,
        y,
        imgWidth,
        imgHeight
    );

    /* =========================
       PIE DE PÁGINA
    ========================= */
    const totalPages = doc.internal.getNumberOfPages();

    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text(
            `Sistema Tienda Pescadores | Página ${i} de ${totalPages}`,
            pageWidth / 2,
            pageHeight - 8,
            { align: "center" }
        );
    }

    doc.save("Reporte_Ventas_Profesional.pdf");
});

</script>

