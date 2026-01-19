<?php
include 'includes/session.php'; 

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'vendedor') {
    header("Location: login.php");
    exit;
}

include('includes/db.php');
include('includes/header.php');
include('includes/navbar.php');

$id_vendedor = $_SESSION['usuario_id'];

// Fechas base
$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');

// CONSULTAS PRINCIPALES
$inicioFiltro = $_GET['inicio'] ?? null;
$finFiltro = $_GET['fin'] ?? null;

$condicionFecha = "";
if ($inicioFiltro && $finFiltro) {
    $condicionFecha = "WHERE DATE(v.fecha_venta) BETWEEN '$inicioFiltro' AND '$finFiltro'";
}

// Ventas día, semana y mes
$ventasHoy = $conn->query("
    SELECT SUM(p.precio_venta * v.cantidad_vendida) AS total, COUNT(*) AS num
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) = CURDATE()
    AND v.id_vendedor = $id_vendedor
")->fetch_assoc();

$ventasSemana = $conn->query("
    SELECT SUM(p.precio_venta * v.cantidad_vendida) AS total, COUNT(*) AS num
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE YEARWEEK(v.fecha_venta, 1) = YEARWEEK(CURDATE(), 1)
    AND v.id_vendedor = $id_vendedor
")->fetch_assoc();


$ventasMes = $conn->query("
    SELECT SUM(p.precio_venta * v.cantidad_vendida) AS total, COUNT(*) AS num
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) >= '$inicioMes'
    AND v.id_vendedor = $id_vendedor
")->fetch_assoc();

// Productos con bajo stock
$stockBajo = $conn->query("SELECT nombre, cantidad FROM productos WHERE cantidad < 5 ORDER BY cantidad ASC LIMIT 5");

// Guardar en un array para poder usarlo varias veces
$stockBajoArray = [];
while($s = $stockBajo->fetch_assoc()){
    $stockBajoArray[] = $s;
}


// Días sin vender
$ultimaVenta = $conn->query("SELECT MAX(fecha_venta) AS ultima FROM ventas")->fetch_assoc()['ultima'];
$diasSinVender = $ultimaVenta ? (new DateTime($ultimaVenta))->diff(new DateTime())->days : 'N/A';

// === NUEVO BLOQUE === //
date_default_timezone_set('America/Mexico_City');

// Fechas
$inicioMesActual = date('Y-m-01');
$finMesActual    = date('Y-m-t');

$inicioMesAnterior = date('Y-m-01', strtotime('-1 month'));
$finMesAnterior    = date('Y-m-t', strtotime('-1 month'));

// ===== VENTAS MES ANTERIOR =====
$resAnterior = $conn->query("
    SELECT IFNULL(SUM(p.precio_venta * v.cantidad_vendida),0) AS total
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) BETWEEN '$inicioMesAnterior' AND '$finMesAnterior'
    AND v.id_vendedor = $id_vendedor
")->fetch_assoc();

$ventasMesAnterior = (float)$resAnterior['total'];

// ===== META MENSUAL =====
$metaBase = 5000;
$metaMensual = ($ventasMesAnterior > 0)
    ? $ventasMesAnterior * 1.20
    : $metaBase;

// ===== VENTAS ACTUALES =====
$resActual = $conn->query("
    SELECT IFNULL(SUM(p.precio_venta * v.cantidad_vendida),0) AS total
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) BETWEEN '$inicioMesActual' AND CURDATE()
    AND v.id_vendedor = $id_vendedor
")->fetch_assoc();

$ventasActuales = (float)$resActual['total'];

// ===== PROGRESO REAL =====
$porcentaje = ($metaMensual > 0)
    ? min(100, ($ventasActuales / $metaMensual) * 100)
    : 0;

// ===== PREDICCIÓN =====
$diasDelMes  = (int)date('t');
$diaActual   = (int)date('j');
$promedioDia = ($diaActual > 0) ? $ventasActuales / $diaActual : 0;

$prediccionFinMes = $promedioDia * $diasDelMes;

// ===== COLOR DINÁMICO =====
if ($porcentaje < 40) {
    $colorBarra = 'bg-danger';
    $estado = 'Riesgo';
} elseif ($porcentaje < 75) {
    $colorBarra = 'bg-warning';
    $estado = 'En proceso';
} else {
    $colorBarra = 'bg-success';
    $estado = 'Buen ritmo';
}

// --- Clientes frecuentes ---
$clientesFrecuentes = $conn->query("
    SELECT 
        v.correo_cliente AS email, 
        COUNT(v.id) AS total_compras,
        SUM(p.precio_venta * v.cantidad_vendida) AS monto_gastado,
        MAX(v.id) AS ultimo_ticket
    FROM ventas v
    JOIN productos p ON v.id_producto = p.id
    WHERE v.correo_cliente IS NOT NULL 
    AND v.correo_cliente != ''
    AND v.id_vendedor = $id_vendedor
    GROUP BY v.correo_cliente
    ORDER BY monto_gastado DESC
    LIMIT 5
");

$ventasHoyDetalle = $conn->query("
    SELECT p.nombre, v.cantidad_vendida, (v.cantidad_vendida * p.precio_venta) AS total
    FROM ventas v 
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) = CURDATE()
    AND v.id_vendedor = $id_vendedor
")->fetch_all(MYSQLI_ASSOC);

// Detalle Ventas Semana
$ventasSemanaDetalle = $conn->query("
    SELECT p.nombre, v.cantidad_vendida, (v.cantidad_vendida * p.precio_venta) AS total
    FROM ventas v 
    JOIN productos p ON v.id_producto = p.id
    WHERE YEARWEEK(v.fecha_venta,1) = YEARWEEK(CURDATE(),1)
    AND v.id_vendedor = $id_vendedor
")->fetch_all(MYSQLI_ASSOC);

// Detalle Ventas Mes
$ventasMesDetalle = $conn->query("
    SELECT p.nombre, v.cantidad_vendida, (v.cantidad_vendida * p.precio_venta) AS total
    FROM ventas v 
    JOIN productos p ON v.id_producto = p.id
    WHERE DATE(v.fecha_venta) >= '$inicioMes'
    AND v.id_vendedor = $id_vendedor
")->fetch_all(MYSQLI_ASSOC);

$clientes = [];
$maxCompras = 0;

while ($row = $clientesFrecuentes->fetch_assoc()) {
    $clientes[] = $row;
    $maxCompras = max($maxCompras, $row['total_compras']);
}

?>

<style>
.content-wrapper {
    min-height: 100vh;
    padding: 20px;
    overflow-x: auto; /* scroll horizontal si algo se desborda */
    background: #f8f9fa;
}

.progress {
    height: 25px;
    border-radius: 20px;
    overflow: hidden;
}

.progress-bar {
    line-height: 25px;
    font-weight: bold;
}

.small-box {
    transition: transform .2s ease, box-shadow .2s ease;
}
.small-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
}
.small-box h2 {
    margin: 10px 0;
    font-weight: 700;
}
.alert {
    padding: .5rem .75rem;
    font-size: .9rem;
}
.alert .close {
    font-size: 1.2rem;
    opacity: .6;
}
.alert .close:hover {
    opacity: 1;
}

.list-group::-webkit-scrollbar {
  width: 6px;
}
.list-group::-webkit-scrollbar-thumb {
  background: #c7d2fe;
  border-radius: 6px;
}
.alert-danger {
    background: #f4bbb7ff !important;
    color: #313130ff !important;
    border-left: 5px solid #ff624dff;
}
</style>

<div class="content-wrapper">
    <section>
        <div class="container-fluid">
            <h2 class="mb-4 text-center">Panel de Ventas</h2>

            <!-- ALERTAS -->
            <?php if ($stockBajo->num_rows > 0): ?>
                  <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center position-relative shadow-sm" role="alert">
                      <div class="mx-auto d-flex align-items-center">
                          <i class="fas fa-exclamation-triangle mr-2"></i>
                          <span>Productos con <strong>stock bajo</strong>. Contacta al administrador.</span>
                      </div>
                      <button type="button" class="close position-absolute" 
                              style="right:10px; top:50%; transform:translateY(-50%);" 
                              data-dismiss="alert" aria-label="Cerrar">
                          <span aria-hidden="true">&times;</span>
                      </button>
                  </div>
              <?php endif; ?>

              <?php if ($diasSinVender !== 'N/A' && $diasSinVender > 3): ?>
                  <div class="alert alert-secondary alert-dismissible fade show d-flex align-items-center position-relative shadow-sm" role="alert">
                      <div class="mx-auto d-flex align-items-center">
                          <i class="fas fa-clock mr-2"></i>
                          <span>Última venta hace <strong><?php echo $diasSinVender; ?> días</strong>.</span>
                      </div>
                      <button type="button" class="close position-absolute" 
                              style="right:10px; top:50%; transform:translateY(-50%);" 
                              data-dismiss="alert" aria-label="Cerrar">
                          <span aria-hidden="true">&times;</span>
                      </button>
                  </div>
              <?php endif; ?>

            <!-- CARDS DE VENTAS -->
            <div class="row">
              <!-- Ventas Hoy -->
              <div class="col-lg-3 col-md-6 col-12">
                  <div class="small-box bg-info" style="cursor:pointer"
                      data-toggle="modal" data-target="#modalVentasHoy">
                      <div class="inner text-center">
                          <h4>Ventas Hoy</h4>
                          <h2 class="fw-bold">
                              $<?php echo number_format($ventasHoy['total'] ?? 0, 2); ?>
                          </h2>
                          <span class="badge badge-light">
                              <?php echo $ventasHoy['num'] ?? 0; ?> ventas
                          </span>
                      </div>
                      <div class="icon">
                          <i class="fas fa-calendar-day"></i>
                      </div>
                      <div class="small-box-footer">
                          Ver detalle <i class="fas fa-arrow-circle-right"></i>
                      </div>
                  </div>
              </div>

              <!-- Ventas Semana -->
              <div class="col-lg-3 col-md-6 col-12">
                  <div class="small-box bg-primary" style="cursor:pointer"
                      data-toggle="modal" data-target="#modalVentasSemana">
                      <div class="inner text-center">
                          <h4>Ventas Semana</h4>
                          <h2 class="fw-bold">
                              $<?php echo number_format($ventasSemana['total'] ?? 0, 2); ?>
                          </h2>
                          <span class="badge badge-light">
                              <?php echo $ventasSemana['num'] ?? 0; ?> ventas
                          </span>
                      </div>
                      <div class="icon">
                          <i class="fas fa-calendar-week"></i>
                      </div>
                      <div class="small-box-footer">
                          Ver detalle <i class="fas fa-arrow-circle-right"></i>
                      </div>
                  </div>
              </div>

              <!-- Ventas Mes -->
              <div class="col-lg-3 col-md-6 col-12">
                  <div class="small-box bg-success" style="cursor:pointer"
                      data-toggle="modal" data-target="#modalVentasMes">
                      <div class="inner text-center">
                          <h4>Ventas Mes</h4>
                          <h2 class="fw-bold">
                              $<?php echo number_format($ventasMes['total'] ?? 0, 2); ?>
                          </h2>
                          <span class="badge badge-light">
                              <?php echo $ventasMes['num'] ?? 0; ?> ventas
                          </span>
                      </div>
                      <div class="icon">
                          <i class="fas fa-calendar-alt"></i>
                      </div>
                      <div class="small-box-footer">
                          Ver detalle <i class="fas fa-arrow-circle-right"></i>
                      </div>
                  </div>
              </div>

              <!-- Stock -->
              <?php
              $stockBajo = !empty($stockBajoArray);
              $stockColor = $stockBajo ? 'bg-danger' : 'bg-success';
              $stockIcon = $stockBajo ? 'fa-exclamation-triangle' : 'fa-check-circle';
              $stockText = $stockBajo ? 'Stock bajo' : 'Stock suficiente';
              ?>
              <div class="col-lg-3 col-md-6 col-12">
                  <div class="small-box <?php echo $stockColor; ?>"
                      style="cursor:pointer"
                      <?php echo $stockBajo ? 'data-toggle="modal" data-target="#modalStockBajo"' : ''; ?>>
                      <div class="inner text-center">
                          <h4>Stock</h4>
                          <h2>
                              <i class="fas <?php echo $stockIcon; ?>"></i>
                          </h2>
                          <span class="badge badge-light">
                              <?php echo $stockText; ?>
                          </span>
                      </div>
                      <div class="icon">
                          <i class="fas fa-boxes"></i>
                      </div>
                      <div class="small-box-footer">
                          <?php echo $stockBajo ? 'Revisar productos' : 'Todo en orden'; ?>
                      </div>
                  </div>
              </div>
            </div>

            <!-- FILTROS -->
            <div class="row mb-4 justify-content-center align-items-center">
                <div class="col-md-3 col-5 mb-2">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-info text-white"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                        <input type="date" class="form-control" id="inicio" placeholder="Fecha inicio">
                    </div>
                </div>
                <div class="col-md-1 col-1 text-center align-self-center mb-2 font-weight-bold">a</div>
                <div class="col-md-3 col-5 mb-2">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-info text-white"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                        <input type="date" class="form-control" id="fin" placeholder="Fecha fin">
                    </div>
                </div>
                <div class="col-md-2 col-12 mb-2 text-center">
                    <button class="btn btn-success btn-sm w-100" onclick="filtrar()"><i class="fas fa-filter"></i> Filtrar Ventas</button>
                </div>
            </div>

            <!-- GRAFICO -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-outline card-info shadow">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Ventas por Producto</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="graficoVentas" style="height:300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- META + CLIENTES -->
            <div class="row">
                <!-- Productividad del Mes -->
                <div class="col-lg-6 col-12 mb-4">
                    <div class="card card-outline card-success shadow-sm h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bullseye text-success"></i> Productividad del Mes
                            </h5>
                        </div>

                        <div class="card-body">

                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted">Meta</small>
                                    <div class="font-weight-bold">
                                        $<?php echo number_format($metaMensual, 2); ?>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Ventas</small>
                                    <div class="font-weight-bold">
                                        $<?php echo number_format($ventasActuales, 2); ?>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Estado</small>
                                    <div class="font-weight-bold text-<?php echo str_replace('bg-', '', $colorBarra); ?>">
                                        <?php echo $estado; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="progress mb-2" style="height:22px; border-radius:12px;">
                                <div class="progress-bar <?php echo $colorBarra; ?> progress-bar-striped progress-bar-animated"
                                    style="width:<?php echo round($porcentaje); ?>%">
                                    <?php echo round($porcentaje); ?>%
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <small class="text-muted">
                                    Progreso real
                                </small>
                                <small class="text-muted">
                                    Predicción: $<?php echo number_format($prediccionFinMes, 2); ?>
                                </small>
                            </div>

                            <hr>

                            <small class="text-muted d-block">
                                <?php if ($ventasMesAnterior > 0): ?>
                                    Meta basada en mes anterior +20%
                                <?php else: ?>
                                    Meta base aplicada (sin historial previo)
                                <?php endif; ?>
                            </small>

                        </div>
                    </div>
                </div>

                <!-- Clientes Frecuentes -->
                <div class="col-lg-6 col-12 mb-4">
                  <div class="card h-100 border-0 shadow-sm">

                    <!-- HEADER -->
                    <div class="card-header bg-white border-bottom">
                      <h5 class="mb-0 font-weight-semibold text-dark">
                        <i class="fas fa-users text-muted mr-2"></i>Clientes Frecuentes
                      </h5>
                      <small class="text-muted">Actividad y valor acumulado</small>
                    </div>

                    <!-- BODY -->
                    <div class="card-body p-0">
                      <div class="list-group list-group-flush" style="max-height:320px; overflow:auto;">

                        <?php foreach ($clientes as $i => $cliente): 
                          $porcentaje = ($maxCompras > 0)
                            ? ($cliente['total_compras'] / $maxCompras) * 100
                            : 0;

                          $inicial = strtoupper(substr($cliente['email'],0,1));
                        ?>

                        <div class="list-group-item border-0 border-bottom py-3">

                          <div class="d-flex justify-content-between align-items-center">

                            <!-- LEFT -->
                            <div class="d-flex align-items-center" style="min-width:55%;">
                              <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center mr-3"
                                  style="width:36px;height:36px;font-weight:600;">
                                <?= $inicial ?>
                              </div>

                              <div class="text-truncate">
                                <div class="font-weight-semibold text-dark text-truncate"
                                    style="max-width:200px;">
                                  <?= htmlspecialchars($cliente['email']) ?>
                                </div>
                                <small class="text-muted">
                                  <?= $cliente['total_compras'] ?> compras
                                </small>
                              </div>
                            </div>

                            <!-- RIGHT -->
                            <div class="text-right">
                              <div class="font-weight-semibold text-success">
                                $<?= number_format($cliente['monto_gastado'],2) ?>
                              </div>
                              <small class="text-muted">Total</small>
                            </div>

                          </div>

                          <!-- MINI GRAPH -->
                          <div class="mt-2">
                            <div class="progress" style="height:6px; background:#f1f5f9;">
                              <div class="progress-bar"
                                  style="
                                    width:<?= round($porcentaje) ?>%;
                                    background:#64748b;
                                  ">
                              </div>
                            </div>
                          </div>

                        </div>

                        <?php endforeach; ?>

                      </div>
                    </div>
                    <!-- FOOTER -->
                    <div class="card-footer bg-white border-top small text-muted d-flex justify-content-between">
                      <span><i class="fas fa-chart-bar mr-1"></i>Dia con mayor venta</span>
                      <span><?= date('d/m/Y') ?></span>
                    </div>
                  </div>
                </div>
            </div>
        </div> <!-- container-fluid -->
    </section>
</div> <!-- content-wrapper -->

<!-- MODALES -->
<?php
// Ventas Hoy
?>
<div class="modal fade" id="modalVentasHoy" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content shadow-lg">

      <div class="modal-header bg-gradient-primary text-white">
        <h5 class="modal-title">
          <i class="fas fa-calendar-day mr-2"></i> Ventas de Hoy
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body p-0">
        <?php if (!empty($ventasHoyDetalle)): ?>

        <div class="p-3 bg-light border-bottom text-center">
          <strong>Total del día:</strong>
          <span class="text-success">
            $<?= number_format(array_sum(array_column($ventasHoyDetalle,'total')),2) ?>
          </span>
        </div>

        <table class="table table-striped table-hover table-sm mb-0">
          <thead class="bg-secondary ">
            <tr>
              <th>Producto</th>
              <th class="text-center">Cant.</th>
              <th class="text-right">Importe</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ventasHoyDetalle as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['nombre']) ?></td>
              <td class="text-center">
                <span class="badge badge-info"><?= $v['cantidad_vendida'] ?></span>
              </td>
              <td class="text-right font-weight-bold text-success">
                $<?= number_format($v['total'], 2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php else: ?>
          <div class="text-center text-muted p-5">
            <i class="fas fa-receipt fa-3x mb-2"></i>
            <p>No hay ventas registradas hoy.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
// Ventas Semana
?>
<div class="modal fade" id="modalVentasSemana" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content shadow-lg">

      <div class="modal-header bg-gradient-info text-white">
        <h5 class="modal-title">
          <i class="fas fa-calendar-week mr-2"></i> Ventas de la Semana
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body p-0">
        <?php if (!empty($ventasSemanaDetalle)): ?>

        <div class="p-3 bg-light border-bottom text-center">
          <strong>Total semanal:</strong>
          <span class="text-success">
            $<?= number_format(array_sum(array_column($ventasSemanaDetalle,'total')),2) ?>
          </span>
        </div>

        <table class="table table-striped table-hover table-sm mb-0">
          <thead class="bg-secondary">
            <tr>
              <th>Producto</th>
              <th class="text-center">Cant.</th>
              <th class="text-right">Importe</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ventasSemanaDetalle as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['nombre']) ?></td>
              <td class="text-center">
                <span class="badge badge-primary"><?= $v['cantidad_vendida'] ?></span>
              </td>
              <td class="text-right font-weight-bold text-success">
                $<?= number_format($v['total'], 2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php else: ?>
          <div class="text-center text-muted p-5">
            <i class="fas fa-calendar-times fa-3x mb-2"></i>
            <p>No hay ventas esta semana.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
// Ventas Mes
?>
<div class="modal fade" id="modalVentasMes" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content shadow-lg">

      <div class="modal-header bg-gradient-success text-white">
        <h5 class="modal-title">
          <i class="fas fa-calendar-alt mr-2"></i> Ventas del Mes
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body p-0">
        <?php if (!empty($ventasMesDetalle)): ?>

        <div class="p-3 bg-light border-bottom text-center">
          <strong>Total mensual:</strong>
          <span class="text-success">
            $<?= number_format(array_sum(array_column($ventasMesDetalle,'total')),2) ?>
          </span>
        </div>

        <table class="table table-striped table-hover table-sm mb-0">
          <thead class="bg-secondary">
            <tr>
              <th>Producto</th>
              <th class="text-center">Cant.</th>
              <th class="text-right">Importe</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ventasMesDetalle as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['nombre']) ?></td>
              <td class="text-center">
                <span class="badge badge-secondary"><?= $v['cantidad_vendida'] ?></span>
              </td>
              <td class="text-right font-weight-bold text-success">
                $<?= number_format($v['total'], 2) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php else: ?>
          <div class="text-center text-muted p-5">
            <i class="fas fa-calendar-minus fa-3x mb-2"></i>
            <p>No hay ventas este mes.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
// Stock Bajo
?>
<div class="modal fade" id="modalStockBajo" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg">

      <div class="modal-header bg-gradient-danger text-white">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-triangle mr-2"></i> Productos con Stock Bajo
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <?php if (!empty($stockBajoArray)): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($stockBajoArray as $s): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($s['nombre']) ?>
                <span class="badge badge-danger badge-pill">
                  <?= $s['cantidad'] ?> uds
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-center text-success p-4">
            <i class="fas fa-check-circle fa-3x mb-2"></i>
            <p>Stock en niveles óptimos.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let chart;

async function filtrar() {
    const inicio = document.getElementById('inicio').value;
    const fin = document.getElementById('fin').value;

   if (!inicio || !fin) {
    Swal.fire({
        icon: 'warning',
        title: 'Fechas incompletas',
        text: 'Selecciona ambas fechas para filtrar las ventas.',
        confirmButtonText: 'Aceptar'
    });
    return;
}

    const response = await fetch(`filtrar_ventas.php?inicio=${inicio}&fin=${fin}`);
    const data = await response.json();

    if (!data.success) {
        alert(data.message);
        return;
    }

    const ctx = document.getElementById('graficoVentas');
    const labels = data.grafica.map(g => g.producto);
    const values = data.grafica.map(g => g.total_vendida);

    if (chart) chart.destroy();

    chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad vendida',
                data: values,
                backgroundColor: 'rgba(30,156,67,0.3)',
                borderColor: '#1e9c43',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });
}

// Gráfico inicial
window.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('graficoVentas');
    chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $res = $conn->query("
                    SELECT p.nombre, SUM(v.cantidad_vendida) AS total_vendida 
                    FROM ventas v
                    JOIN productos p ON v.id_producto = p.id 
                    WHERE v.id_vendedor = $id_vendedor
                    GROUP BY p.nombre 
                    ORDER BY total_vendida DESC 
                    LIMIT 6
                ");
                $labels = [];
                $values = [];
                while($r = $res->fetch_assoc()) { 
                    $labels[] = "'".$r['nombre']."'";
                    $values[] = $r['total_vendida'];
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Cantidad vendida',
                data: [<?php echo implode(',', $values); ?>],
                backgroundColor: 'rgba(30,156,67,0.3)',
                borderColor: '#1e9c43',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
