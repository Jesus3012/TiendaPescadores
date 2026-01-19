<?php
date_default_timezone_set('America/Mexico_City');

session_start();
include 'includes/db.php';
include 'includes/header.php';
include 'includes/navbar.php';

$proveedorSeleccionado = $_GET['proveedor'] ?? '';
$fechaHoy = date('d/m/Y');

/* $resFecha = $conn->prepare("
    SELECT MIN(fecha_registro) AS fecha_registro
    FROM productos
    WHERE proveedor=?
");
$resFecha->bind_param("s", $proveedorSeleccionado);
$resFecha->execute();
$f = $resFecha->get_result()->fetch_assoc();
$fechaRegistroGeneral = date('d/m/Y', strtotime($f['fecha_registro'])); */

?>


<div class="content-wrapper">
<section class="content pt-3">
<div class="container-fluid">

<!-- =================== SELECT PROVEEDOR =================== -->
<div class="card card-outline card-primary">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-industry mr-2"></i> Venta por proveedor
        </h5>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row">
                <div class="col-md-6 col-12">
                    <label>Proveedor</label>
                    <select name="proveedor" class="form-control" onchange="this.form.submit()">
                        <option value="">Seleccione proveedor</option>
                        <?php
                        $prov = $conn->query("SELECT DISTINCT proveedor FROM productos WHERE proveedor IS NOT NULL ORDER BY proveedor");
                        while ($p = $prov->fetch_assoc()):
                        ?>
                            <option value="<?= $p['proveedor'] ?>" <?= $proveedorSeleccionado === $p['proveedor'] ? 'selected' : '' ?>>
                                <?= $p['proveedor'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($proveedorSeleccionado):

$productos = [];
$q = $conn->prepare("SELECT id, nombre, cantidad, precio_compra, precio_venta, fecha_registro FROM productos WHERE proveedor=?");
$q->bind_param("s",$proveedorSeleccionado);
$q->execute();
$r = $q->get_result();

while($row=$r->fetch_assoc()){
    $productos[]=$row;
}
?>

<!-- =================== TABLA =================== -->
<div class="card card-outline card-success mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-boxes mr-2"></i> 
            Control de stock – <?= $proveedorSeleccionado ?>
        </h5>

        <div>
            <a href="reporte_excel.php?proveedor=<?= urlencode($proveedorSeleccionado) ?>" 
               class="btn btn-success btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </a>

            <a href="reporte_pdf.php?proveedor=<?= urlencode($proveedorSeleccionado) ?>" 
                target="_blank" 
               class="btn btn-danger btn-sm ml-2">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
        </div>
    </div>

    <div class="card-body table-responsive">
        <form id="formStockFinal">
            <table class="table table-bordered table-sm text-center">
                <thead class="bg-light">
                <tr>
                <th>Producto</th>
                    <th>
                    Stock Inicial<br>
                    <!-- <small>Registro: <?= $fechaRegistroGeneral ?></small><br> -->
                        <small class="text-muted">
                            Hoy: <?= $fechaHoy ?>
                        </small>
                    </th>
                    <th >Stock después de contar <br>
                        <small class="text-muted">
                            <?= $fechaHoy ?>
                        </small>
                    </th>
                    <th>Ventas <br>
                        <small class="text-muted">
                            <?= $fechaHoy ?>
                        </small>
                    </th>
                    <th>Stock Final <br>
                        <small class="text-muted">
                            <?= $fechaHoy ?>
                        </small>
                    </th>
                    <th>Venta $</th>
                <th>Deuda $</th>
            <th>Ganancia $</th>
        </tr>
    </thead>
<tbody>

<?php foreach($productos as $p):
    $stockInicial = (int)$p['cantidad'];
    $fechaRegistro = date('d/m/Y',strtotime($p['fecha_registro']));
?>
<tr
    data-id="<?= $p['id'] ?>"
    data-stock-inicial="<?= $stockInicial ?>"
    data-precio-venta="<?= $p['precio_venta'] ?>"
    data-precio-compra="<?= $p['precio_compra'] ?>"
>
    <td><?= $p['nombre'] ?></td>

    <td>
        <strong><?= $stockInicial ?></strong><br>
        <small>Registro: <?= $fechaRegistro ?></small><br>
    </td>

    <td>
        <input type="number" class="form-control form-control-sm stock-conteo"
               value="0" min="0" max="<?= $stockInicial ?>">
        

        <!-- OCULTOS -->
        <input type="hidden" name="ventas[<?= $p['id'] ?>]" class="ventas-input" value="0">
        <input type="hidden" name="stock_final[<?= $p['id'] ?>]" class="stock-final-input" value="0">
    </td>

    <td class="ventasCalculadas">0<br>
    <td class="stockFinal">0<br>

    <td class="ventaMonto">$0.00</td>
    <td class="deudaMonto text-danger">$0.00</td>
    <td class="gananciaMonto text-success">$0.00</td>
</tr>
<?php endforeach; ?>

</tbody>
<tfoot class="bg-light font-weight-bold">
<tr>
    <td colspan="5">TOTALES</td>
    <td id="totalVentas">$0.00</td>
    <td id="totalDeuda" class="text-danger">$0.00</td>
    <td id="totalGanancia" class="text-success">$0.00</td>
</tr>
</tfoot>
</table>

<div class="text-right mt-3">
    <button type="submit" class="btn btn-success">
        <i class="fas fa-save"></i> Guardar Conteo
    </button>
</div>
</form>
</div>
</div>

<!-- =================== INFO BOX =================== -->
<div class="row mt-4">
<div class="col-md-4 col-12 mb-3">
<div class="info-box bg-info">
<span class="info-box-icon"><i class="fas fa-cash-register"></i></span>
<div class="info-box-content">
<span class="info-box-text">Ventas</span>
<span class="info-box-number" id="infoVentas">$0.00</span>
</div>
</div>
</div>

<div class="col-md-4 col-12 mb-3">
<div class="info-box bg-danger">
<span class="info-box-icon"><i class="fas fa-file-invoice-dollar"></i></span>
<div class="info-box-content">
<span class="info-box-text">Deuda</span>
<span class="info-box-number" id="infoDeuda">$0.00</span>
</div>
</div>
</div>

<div class="col-md-4 col-12 mb-3">
<div class="info-box bg-success">
<span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
<div class="info-box-content">
<span class="info-box-text">Ganancia</span>
<span class="info-box-number" id="infoGanancia">$0.00</span>
</div>
</div>
</div>
</div>

<!-- =================== GRAFICA =================== -->
<div class="row mt-4">
    <!-- GRAFICA VENTAS -->
    <div class="col-md-6 col-12 mb-3">
        <div class="card" style="height:400px">
            <div class="card-body h-100">
                <canvas id="graficaVentas"></canvas>
            </div>
        </div>
    </div>

    <!-- GRAFICA STOCK -->
    <div class="col-md-6 col-12 mb-3">
        <div class="card" style="height:400px">
            <div class="card-body h-100">
                <canvas id="graficaStock"></canvas>
            </div>
        </div>
    </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded',()=>{

/* ================= GRAFICA VENTAS ================= */
let chartVentas = new Chart(document.getElementById('graficaVentas'),{
    type:'bar',
    data:{
        labels:['Ventas','Deuda','Ganancia'],
        datasets:[{
            data:[0,0,0],
            backgroundColor:['#17a2b8','#dc3545','#28a745']
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true}}
    }
});

/* ================= DATOS PARA GRAFICA STOCK ================= */
const nombres = [];
const stockInicialArr = [];
const stockFinalArr = [];

document.querySelectorAll('tbody tr').forEach(tr=>{
    nombres.push(tr.querySelector('td').innerText);
    stockInicialArr.push(parseInt(tr.dataset.stockInicial));
    stockFinalArr.push(0);
});

/* ================= GRAFICA STOCK ================= */
let chartStock = new Chart(document.getElementById('graficaStock'),{
    type:'bar',
    data:{
        labels:nombres,
        datasets:[
            {
                label:'Stock Inicial',
                data:stockInicialArr,
                backgroundColor:'#007bff'
            },
            {
                label:'Stock Final',
                data:stockFinalArr,
                backgroundColor:'#28a745'
            }
        ]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        scales:{y:{beginAtZero:true}}
    }
});

/* ================= RECALCULO ================= */
function recalcular(){
    let tv=0, td=0, tg=0;

    document.querySelectorAll('tbody tr').forEach((tr,index)=>{
        let si = parseInt(tr.dataset.stockInicial);
        let pv = parseFloat(tr.dataset.precioVenta);
        let pc = parseFloat(tr.dataset.precioCompra);

        let input = tr.querySelector('.stock-conteo');
        let sc = parseInt(input.value) || 0;

        sc = Math.min(Math.max(sc,0),si);
        input.value = sc;

        let ventas = si - sc;
        let vm = ventas * pv;
        let dm = ventas * pc;
        let gm = vm - dm;

        tr.querySelector('.ventasCalculadas').innerHTML =
            ventas ;

        tr.querySelector('.stockFinal').innerHTML =
            sc;

        tr.querySelector('.ventaMonto').innerText = '$' + vm.toFixed(2);
        tr.querySelector('.deudaMonto').innerText = '$' + dm.toFixed(2);
        tr.querySelector('.gananciaMonto').innerText = '$' + gm.toFixed(2);

        tr.querySelector('.ventas-input').value = ventas;
        tr.querySelector('.stock-final-input').value = sc;

        stockFinalArr[index] = sc;

        tv += vm;
        td += dm;
        tg += gm;
    });

    document.getElementById('totalVentas').innerText = '$' + tv.toFixed(2);
    document.getElementById('totalDeuda').innerText = '$' + td.toFixed(2);
    document.getElementById('totalGanancia').innerText = '$' + tg.toFixed(2);

    document.getElementById('infoVentas').innerText = '$' + tv.toFixed(2);
    document.getElementById('infoDeuda').innerText = '$' + td.toFixed(2);
    document.getElementById('infoGanancia').innerText = '$' + tg.toFixed(2);

    chartVentas.data.datasets[0].data = [tv, td, tg];
    chartVentas.update();

    chartStock.update();
}

/* ================= EVENTOS ================= */
document.querySelectorAll('.stock-conteo').forEach(i=>{
    i.addEventListener('input',recalcular);
});

/* ================= GUARDAR ================= */
document.getElementById('formStockFinal').addEventListener('submit',e=>{
    e.preventDefault();
    fetch('actualizar_stock.php',{
        method:'POST',
        body:new FormData(e.target)
    })
    .then(r=>r.json())
    .then(res=>{
        if(res.status==='ok'){
            Swal.fire({
                icon:'success',
                title:'Venta guardada y reporte generado',
                timer:1500,
                showConfirmButton:false
            }).then(()=>location.reload());
        }else{
            Swal.fire('Error',res.msg,'error');
        }
    });
});

});
</script>

<?php endif; ?>


