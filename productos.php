<?php
ob_start();
session_start();
require_once 'includes/csrf.php';
require_once 'includes/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit;
}


include 'includes/header.php';
include 'includes/navbar.php';
require_once 'includes/fpdf.php';
require_once __DIR__.'/vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;



$success = '';
$errors = [];

// ========================= AGREGAR PRODUCTO =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_check();

    $nombre = trim($_POST['nombre'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $precio_compra = floatval($_POST['precio_compra'] ?? 0);
    $precio_venta = floatval($_POST['precio_venta'] ?? 0);
    $tipo_codigo = $_POST['tipo_codigo'] ?? 'multiple';

    if ($nombre === '' || $proveedor === '' || $cantidad <= 0 || $precio_compra <= 0 || $precio_venta <= 0) {
        echo "<script>alert('Completa todos los campos correctamente.');</script>";
    } else {
        $imagen_path = '';
        if (!empty($_FILES['imagen']['name'])) {
            $upload_dir = __DIR__.'/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $imagen_name = time().'_'.preg_replace('/\s+/', '_', $_FILES['imagen']['name']);
            $imagen_path = 'uploads/'.$imagen_name;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $imagen_path);
        }

        $stmt = $conn->prepare("INSERT INTO productos (nombre, proveedor, imagen, cantidad, precio_compra, precio_venta, tipo_codigo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssidds", $nombre, $proveedor, $imagen_path, $cantidad, $precio_compra, $precio_venta, $tipo_codigo);
        if ($stmt->execute()) {
            $producto_id = $stmt->insert_id;
            $stmt->close();

            generarPDFCodigos($nombre, $producto_id, $cantidad, $tipo_codigo);

            echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Producto agregado',
                text: 'El producto se agregó correctamente.',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                window.location = 'productos.php';
            });
            </script>";
            exit;
            echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al insertar producto.'
            });
            </script>";
        }
    }
}

// ========================= ACTUALIZAR PRODUCTO =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_check();

    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $proveedor = trim($_POST['proveedor']);
    $cantidad = intval($_POST['cantidad']);
    $precio_compra = floatval($_POST['precio_compra']);
    $precio_venta = floatval($_POST['precio_venta']);
    $tipo_codigo = $_POST['tipo_codigo'] ?? 'multiple';

    $imagen_path = '';
    if (!empty($_FILES['imagen']['name'])) {
        $upload_dir = __DIR__.'/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $imagen_name = time().'_'.preg_replace('/\s+/', '_', $_FILES['imagen']['name']);
        $imagen_path = 'uploads/'.$imagen_name;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $imagen_path);
    }

    if ($imagen_path) {
        $stmt = $conn->prepare("UPDATE productos SET nombre=?, proveedor=?, imagen=?, cantidad=?, precio_compra=?, precio_venta=?, tipo_codigo=? WHERE id=?");
        $stmt->bind_param("sssiddsi", $nombre, $proveedor, $imagen_path, $cantidad, $precio_compra, $precio_venta, $tipo_codigo, $id);
    } else {
        $stmt = $conn->prepare("UPDATE productos SET nombre=?, proveedor=?, cantidad=?, precio_compra=?, precio_venta=?, tipo_codigo=? WHERE id=?");
        $stmt->bind_param("ssiddsi", $nombre, $proveedor, $cantidad, $precio_compra, $precio_venta, $tipo_codigo, $id);
    }

    if ($stmt->execute()) {
        $conn->query("DELETE FROM codigos_barras WHERE producto_id = $id");

        $old_pdf = __DIR__ . '/uploads/codigos_producto_' . $id . '.pdf';
        if (file_exists($old_pdf)) @unlink($old_pdf);

        generarPDFCodigos($nombre, $id, $cantidad, $tipo_codigo);

        echo "<script>
        Swal.fire({
            icon: 'success',
            title: 'Producto actualizado',
            text: 'Los cambios se guardaron correctamente.',
        }).then(() => {
            window.location='productos.php';
        });
        </script>";
    } else {
        echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error al actualizar',
            text: 'No se pudo actualizar el producto. Intenta nuevamente.',
            confirmButtonText: 'Aceptar'
        });
        </script>";
    }
}

// ========================= ELIMINAR PRODUCTO =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "<script>
    Swal.fire({
        icon: 'success',
        title: 'Producto eliminado',
        text: 'El producto fue eliminado correctamente.'
    }).then(() => {
        window.location='productos.php';
    });
    </script>";
    exit;
}

// ========================= GENERAR PDF CÓDIGOS =========================
function generarPDFCodigos($nombre, $producto_id, $cantidad, $tipo_codigo = 'multiple') {
    global $conn;
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

    $cantidad = max(0, intval($cantidad));
    $uploads_dir = __DIR__ . '/uploads/';
    if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0777, true);

    $file = $uploads_dir . 'codigos_producto_' . $producto_id . '.pdf';
    if (file_exists($file)) @unlink($file);

    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFont('Arial', '', 10);

    if ($tipo_codigo === 'unico') {
        $codigo = "P" . str_pad($producto_id, 6, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT IGNORE INTO codigos_barras (producto_id, codigo) VALUES (?, ?)");
        $stmt->bind_param("is", $producto_id, $codigo);
        $stmt->execute();
        $stmt->close();

        $pdf->AddPage();
        $pngData = $generator->getBarcode($codigo, $generator::TYPE_CODE_128);
        $tmp = tempnam(sys_get_temp_dir(), 'bc_') . '.png';
        file_put_contents($tmp, $pngData);

        $pdf->Cell(0, 10, utf8_decode("Producto: $nombre"), 0, 1, 'C');
        $pdf->Image($tmp, 55, 40, 100, 30, 'PNG');
        $pdf->SetXY(55, 75);
        $pdf->Cell(100, 10, $codigo, 0, 0, 'C');

        @unlink($tmp);

    } else {
        $codigos_por_fila = 4;
        $filas_por_pagina = 5;
        $codigos_por_pagina = $codigos_por_fila * $filas_por_pagina;

        $ancho_codigo = 40;
        $alto_codigo = 20;
        $margen_x = 20;
        $margen_y = 15;
        $espaciado_x = 45;
        $espaciado_y = 45;

        for ($i = 0; $i < $cantidad; $i++) {
            if ($i % $codigos_por_pagina == 0) $pdf->AddPage();

            $index = $i % $codigos_por_pagina;
            $columna = $index % $codigos_por_fila;
            $fila = intdiv($index, $codigos_por_fila);
            $x = $margen_x + ($columna * $espaciado_x);
            $y = $margen_y + ($fila * $espaciado_y);

            $codigo = $producto_id . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT IGNORE INTO codigos_barras (producto_id, codigo) VALUES (?, ?)");
            $stmt->bind_param("is", $producto_id, $codigo);
            $stmt->execute();
            $stmt->close();

            $pngData = $generator->getBarcode($codigo, $generator::TYPE_CODE_128);
            $tmp = tempnam(sys_get_temp_dir(), 'bc_') . '.png';
            file_put_contents($tmp, $pngData);

            $pdf->SetXY($x, $y);
            $pdf->Cell($ancho_codigo, 5, utf8_decode($nombre), 0, 2, 'C');
            $pdf->Image($tmp, $x + 2, $y + 6, $ancho_codigo - 4, $alto_codigo, 'PNG');
            $pdf->SetXY($x, $y + $alto_codigo + 10);
            $pdf->Cell($ancho_codigo, 5, $codigo, 0, 0, 'C');

            @unlink($tmp);
        }
    }

    $pdf->Output('F', $file);
}

// ========================= NUEVO: PDF CON TODOS LOS CÓDIGOS =========================
if (isset($_GET['action']) && $_GET['action'] === 'todos_codigos') {
    generarPDFTodosCodigos($conn);
}

function generarPDFTodosCodigos($conn) {
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $pdf = new FPDF();
    $pdf->SetAutoPageBreak(false);
    $pdf->SetFont('Arial', '', 8);

    $query = "SELECT cb.codigo, p.nombre FROM codigos_barras cb 
              JOIN productos p ON cb.producto_id = p.id 
              ORDER BY p.nombre ASC, cb.codigo ASC";
    $res = $conn->query($query);

    $pdf->AddPage();
    $margen_x = 20;
    $margen_y = 15;
    $espaciado_x = 45;
    $espaciado_y = 40;
    $ancho_codigo = 40;
    $alto_codigo = 20;
    $col = 0;
    $fila = 0;

    while ($row = $res->fetch_assoc()) {
        if ($fila >= 6) {
            $pdf->AddPage();
            $fila = 0;
            $col = 0;
        }

        $x = $margen_x + ($col * $espaciado_x);
        $y = $margen_y + ($fila * $espaciado_y);
        $pngData = $generator->getBarcode($row['codigo'], $generator::TYPE_CODE_128);
        $tmp = tempnam(sys_get_temp_dir(), 'bc_') . '.png';
        file_put_contents($tmp, $pngData);

        $pdf->SetXY($x, $y);
        $pdf->Cell($ancho_codigo, 4, utf8_decode($row['nombre']), 0, 2, 'C');
        $pdf->Image($tmp, $x + 2, $y + 5, $ancho_codigo - 4, $alto_codigo, 'PNG');
        $pdf->SetXY($x, $y + $alto_codigo + 8);
        $pdf->Cell($ancho_codigo, 4, $row['codigo'], 0, 0, 'C');

        @unlink($tmp);

        $col++;
        if ($col >= 4) {
            $col = 0;
            $fila++;
        }
    }

    $file = __DIR__ . '/uploads/todos_codigos.pdf';
    $pdf->Output('F', $file);
    header("Location: uploads/todos_codigos.pdf");
    exit;
}

// ========================= CONSULTAR PRODUCTOS =========================
$productos = [];
$res = $conn->query(" SELECT * FROM productos WHERE activo = 1 ORDER BY id DESC");

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $productos[] = $row;
    }
    $res->free();
}
?>

<style>
/* Quitar completamente el margen automático de AdminLTE */
.layout-navbar-fixed .wrapper .content-wrapper {
    margin-top: 0 !important;
    padding-top: 70px !important; /* Altura exacta del navbar */
}

/* Sidebar alineado con navbar */
.main-sidebar {
    margin-top: 0 !important;
    padding-top: 70px !important; /* Igual que arriba para alinear */
}

/* Eliminar margen adicional causado por container */
.content-wrapper .container,
.content-header,
.content {
    margin-top: 0 !important;
    padding-top: 0 !important;
}


</style>


<div class="content-wrapper">

    <section>
        <div class="container-fluid">

            <div class="row">

                <!-- FORMULARIO ARRIBA -->
                <div class="col-12">
                    <div class="card card-primary card-outline shadow-sm">

                        <!-- HEADER -->
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold mb-0">
                                <i class="fas fa-box-open mr-2"></i> Nuevo producto
                            </h3>

                            <!-- BOTÓN LIMPIAR -->
                            <button type="button"
                                    class="btn btn-light btn-sm ml-auto"
                                    title="Limpiar formulario"
                                    onclick="limpiarFormulario()">
                                <i class="fas fa-undo-alt"></i>
                            </button>
                        </div>

                        <!-- BODY -->
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="formProducto">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                                <div class="row">

                                    <!-- DATOS BÁSICOS -->
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Nombre del producto</label>
                                            <input type="text"
                                                name="nombre"
                                                class="form-control"
                                                placeholder="Ej. Llaveros"
                                                required>
                                            <small class="text-muted">
                                                Nombre con el que se identificará el producto
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label>Proveedor</label>
                                            <input type="text"
                                                name="proveedor"
                                                class="form-control"
                                                placeholder="Ej. Proveedor S.A."
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label>Imagen del producto</label>
                                            <input type="file"
                                                name="imagen"
                                                class="form-control"
                                                accept="image/*"
                                                onchange="previewImagen(event)">
                                            <small class="text-muted">
                                                Opcional – imagen de referencia
                                            </small>

                                            <img id="previewImg"
                                                class="img-thumbnail mt-2 d-none"
                                                style="max-height:120px;">
                                        </div>

                                    </div>

                                    <!-- INVENTARIO Y PRECIOS -->
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Cantidad inicial</label>
                                            <input type="number"
                                                name="cantidad"
                                                class="form-control"
                                                min="1"
                                                placeholder="Ej. 10"
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label>Precio de compra</label>
                                            <input type="number"
                                                step="0.01"
                                                name="precio_compra"
                                                class="form-control"
                                                placeholder="Costo del proveedor"
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label>Precio de venta</label>
                                            <input type="number"
                                                step="0.01"
                                                name="precio_venta"
                                                class="form-control"
                                                placeholder="Precio al público"
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label>Tipo de código de barras</label>
                                            <select name="tipo_codigo" class="form-control">
                                                <option value="multiple" selected>
                                                    Un código por producto
                                                </option>
                                                <option value="unico">
                                                    Un solo código
                                                </option>
                                            </select>
                                            <small class="text-muted">
                                                Recomendado: un código por producto
                                            </small>
                                        </div>

                                    </div>

                                </div>

                                <!-- BOTÓN -->
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-success btn-block btn-lg">
                                        <i class="fas fa-save mr-1"></i> Guardar producto
                                    </button>
                                </div>

                            </form>
                        </div>

                    </div>
                </div>

                <!-- TABLA DEBAJO -->
                <div class="col-12 mt-4">
                    <div class="card card-outline card-dark shadow-sm flex-fill">
                        <!-- HEADER -->
                        <div class="card-header">
                            <div class="d-flex flex-column flex-md-row align-items-md-center">
                                <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                                    <i class="fas fa-box mr-2"></i> Productos existentes
                                </h3>

                                <div class="ml-md-auto mt-2 mt-md-0" style="max-width:250px;">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-search"></i>
                                            </span>
                                        </div>

                                        <input type="text"
                                            id="searchProductos"
                                            class="form-control"
                                            placeholder="Buscar producto...">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- BODY -->
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered table-sm mb-0 text-nowrap">
                                    <thead class="bg-secondary">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Proveedor</th>
                                            <th class="text-center">Imagen</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-right">Compra</th>
                                            <th class="text-right">Venta</th>
                                            <th class="text-center">PDF</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($productos as $p): ?>
                                        <tr>
                                            <td class="font-weight-bold">
                                                <?= htmlspecialchars($p['nombre']) ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($p['proveedor']) ?>
                                            </td>

                                            <!-- IMAGEN -->
                                            <td class="text-center">
                                                <?php if ($p['imagen']): ?>
                                                    <img src="<?= $p['imagen'] ?>"
                                                        class="img-thumbnail"
                                                        style="width:50px;height:50px;object-fit:cover;"
                                                        alt="Producto">
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Sin imagen</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- CANTIDAD -->
                                            <td class="text-center">
                                                <span class="badge
                                                    <?= $p['cantidad'] <= 0 ? 'badge-danger' :
                                                        ($p['cantidad'] <= 5 ? 'badge-warning' : 'badge-success') ?>">
                                                    <?= $p['cantidad'] ?>
                                                </span>
                                            </td>

                                            <!-- PRECIOS -->
                                            <td class="text-right">
                                                $<?= number_format($p['precio_compra'], 2) ?>
                                            </td>

                                            <td class="text-right font-weight-bold text-success">
                                                $<?= number_format($p['precio_venta'], 2) ?>
                                            </td>

                                            <!-- PDF -->
                                            <td class="text-center">
                                                <?php
                                                $pdf_file = 'uploads/codigos_producto_' . $p['id'] . '.pdf';
                                                if (file_exists($pdf_file)):
                                                ?>
                                                    <a href="<?= $pdf_file ?>?v=<?= filemtime($pdf_file) ?>"
                                                        class="btn btn-outline-success btn-sm"
                                                        target="_blank">
                                                        <i class="far fa-file-pdf"></i> PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- ACCIONES -->
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">

                                                    <button class="btn btn-info"
                                                        title="Editar"
                                                        onclick="editarProducto(
                                                            <?= $p['id'] ?>,
                                                            '<?= htmlspecialchars($p['nombre']) ?>',
                                                            '<?= htmlspecialchars($p['proveedor']) ?>',
                                                            <?= $p['cantidad'] ?>,
                                                            <?= $p['precio_compra'] ?>,
                                                            <?= $p['precio_venta'] ?>
                                                        )">
                                                        <i class="fas fa-edit"></i>
                                                    </button>

                                                    <form id="formEliminar" method="POST" style="display:none;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" id="delete_id">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    </form>

                                                    <button class="btn btn-danger"
                                                        title="Eliminar"
                                                        onclick="confirmarEliminar(<?= $p['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>

                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- BOTÓN PDF TODOS LOS CÓDIGOS -->
                            <div class="p-3 d-flex justify-content-start">
                                <a href="productos.php?action=todos_codigos"
                                    target="_blank"
                                    class="btn btn-success btn-sm">
                                    <i class="fas fa-file-pdf mr-1"></i> PDF con todos los códigos
                                </a>
                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="card-footer text-muted text-right">
                            <small>
                                Total productos: <strong><?= count($productos) ?></strong>
                            </small>
                        </div>

                    </div>
                </div>


                <!-- MODAL EDITAR PRODUCTO -->
                <div class="modal fade" id="modalEditar" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" enctype="multipart/form-data" class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Editar Producto</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>

                            <div class="modal-body">

                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" id="edit_id" name="id">

                                <div class="form-group">
                                    <label>Nombre:</label>
                                    <input type="text" id="edit_nombre" name="nombre" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Proveedor:</label>
                                    <input type="text" id="edit_proveedor" name="proveedor" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Cantidad:</label>
                                    <input type="number" id="edit_cantidad" name="cantidad" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Precio compra:</label>
                                    <input type="number" step="0.01" id="edit_precio_compra" name="precio_compra" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Precio venta:</label>
                                    <input type="number" step="0.01" id="edit_precio_venta" name="precio_venta" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label>Nueva imagen (opcional):</label>
                                    <input type="file" name="imagen" id="edit_imagen" accept="image/*" class="form-control">
                                </div>

                                <div class="form-group">
                                    <label>Tipo código:</label>
                                    <select id="edit_tipo_codigo" name="tipo_codigo" class="form-control">
                                        <option value="unico">Código único</option>
                                        <option value="multiple">Por artículo</option>
                                    </select>
                                </div>
                                
                            </div>

                            <div class="modal-footer">
                                <button class="btn btn-success">Actualizar</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div><!-- row -->

        </div><!-- container-fluid -->
    </section>
</div>
<form id="formEliminar" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" id="delete_id" name="id">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
</form>



<!-- jQuery (Debe ir siempre primero) -->
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

// --- EDITAR PRODUCTO ---
function editarProducto(id, nombre, proveedor, cantidad, precioCompra, precioVenta, tipo_codigo = "multiple") {

    // Cargar datos al formulario del modal
    $('#edit_id').val(id);
    $('#edit_nombre').val(nombre);
    $('#edit_proveedor').val(proveedor);
    $('#edit_cantidad').val(cantidad);
    $('#edit_precio_compra').val(precioCompra);
    $('#edit_precio_venta').val(precioVenta);
    $('#edit_tipo_codigo').val(tipo_codigo);

    // Confirmación SweetAlert
    Swal.fire({
        title: "Editar producto",
        text: "¿Deseas modificar este producto?",
        icon: "info",
        showCancelButton: true,
        confirmButtonText: "Sí, editar",
        cancelButtonText: "Cancelar"
    }).then((res) => {
        if (res.isConfirmed) {
            $('#modalEditar').modal('show');
        }
    });
}


// --- ELIMINAR PRODUCTO ---
function confirmarEliminar(id) {
    Swal.fire({
        title: "¿Eliminar producto?",
        text: "Esta acción no se puede deshacer",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("delete_id").value = id;
            document.getElementById("formEliminar").submit();
        }
    });
}

document.getElementById('searchProductos').addEventListener('keyup', function () {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll('table tbody tr');

    filas.forEach(fila => {
        const texto = fila.innerText.toLowerCase();
        fila.style.display = texto.includes(filtro) ? '' : 'none';
    });
});

function limpiarFormulario() {
    const form = document.getElementById('formProducto');
    form.reset();

    const img = document.getElementById('previewImg');
    img.src = '';
    img.classList.add('d-none');
}

function previewImagen(event) {
    const img = document.getElementById('previewImg');
    img.src = URL.createObjectURL(event.target.files[0]);
    img.classList.remove('d-none');
}
</script>

<?php
ob_end_flush();
?>
