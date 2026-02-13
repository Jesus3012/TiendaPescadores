<?php
include('includes/db.php');
include('includes/header.php');
include('includes/navbar.php');
include('includes/session.php');
require_once('includes/csrf.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SESSION['rol'] !== 'vendedor') {
    header("Location: index.php");
    exit;
}

$alerta = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_venta'])) {
    csrf_check();

    $carrito = json_decode($_POST['carrito_json'] ?? '[]', true);
    $monto_pagado = floatval($_POST['monto_pagado']);
    $correo_cliente = trim($_POST['correo_cliente'] ?? '');
    $metodo_pago = $_POST['metodo_pago'] ?? null;

    // Inicializamos referencia vacía
    $referencia_pago = null;

    // Si el pago es con tarjeta (terminal física)
    if ($metodo_pago === "tarjeta_debito" || $metodo_pago === "tarjeta_credito") {

        $ultimos4 = $_POST['ultimos4'] ?? '';
        $tipo = $_POST['tipo_tarjeta'] ?? '';
        $auth = $_POST['folio_autorizacion'] ?? '';
        $ref = $_POST['referencia_pago'] ?? '';

        // Guardar referencia segura en BD
        $referencia_pago = "$tipo ****$ultimos4 | AUTH: $auth | REF: $ref";
    }

    // Si es transferencia
    else if ($metodo_pago === "transferencia") {
        $referencia_pago = trim($_POST['referencia_pago'] ?? '');
    }

    // Si es efectivo → no se guarda referencia
    else if ($metodo_pago === "efectivo") {
        $referencia_pago = null;
    }

    $id_vendedor = $_SESSION['usuario_id'];


    if (empty($carrito)) {
        $alerta = ['tipo' => 'error', 'titulo' => 'Carrito vacío', 'mensaje' => 'Agrega al menos un producto antes de registrar la venta.'];
    } else {
        $total = 0;
        foreach ($carrito as $item) $total += $item['precio'] * $item['cantidad'];
        $cambio = $monto_pagado - $total;

        if ($cambio < 0) {
            $alerta = ['tipo' => 'error', 'titulo' => 'Monto insuficiente', 'mensaje' => 'El monto pagado no cubre el total.'];
        } else {
            $errores = [];
            foreach ($carrito as $item) {
                $producto = $conn->query("SELECT cantidad FROM productos WHERE id={$item['id']}")->fetch_assoc();
                if ($producto['cantidad'] < $item['cantidad']) {
                    $errores[] = $item['nombre'];
                }
            }

            if (!empty($errores)) {
                $alerta = ['tipo' => 'error', 'titulo' => 'Sin stock', 'mensaje' => 'Stock insuficiente para: ' . implode(', ', $errores)];
            } else {
                // === Crear una sola venta principal ===
                $conn->query("
                    INSERT INTO ventas (id_producto, cantidad_vendida, correo_cliente, id_vendedor, metodo_pago, referencia_pago
                    ) VALUES (0, 0, '{$correo_cliente}', {$id_vendedor}, '{$metodo_pago}', '{$referencia_pago}')
                ");
                $idVentaPrincipal = $conn->insert_id;   // ← AHORA YA SE GUARDA CORRECTAMENTE

                // ===  Insertar los productos ===
                $folio = uniqid('VENTA_'); // Identificador común para todos los productos de esta venta
                $idVentaPrincipal = null;

                foreach ($carrito as $item) {
                    $conn->query("
                      INSERT INTO ventas ( id_producto, cantidad_vendida, correo_cliente, folio_ticket, id_vendedor, metodo_pago, referencia_pago
                      ) VALUES ( {$item['id']}, {$item['cantidad']}, '{$correo_cliente}', '$folio', {$id_vendedor}, '{$metodo_pago}', '{$referencia_pago}')
                  ");
                    $conn->query("UPDATE productos SET cantidad = cantidad - {$item['cantidad']} WHERE id={$item['id']}");
                }

                // === 3️⃣ Generar ticket ===
                require_once('includes/fpdf.php');
                require_once('includes/PHPMailer/src/Exception.php');
                require_once('includes/PHPMailer/src/PHPMailer.php');
                require_once('includes/PHPMailer/src/SMTP.php');

                if (!is_dir('tickets')) mkdir('tickets', 0777, true);

                $subtotal = $total / 1.16;
                $iva = $total - $subtotal;
                $folio = 'VENTA_' . str_pad($idVentaPrincipal, 6, '0', STR_PAD_LEFT);

                $pdf = new FPDF('P', 'mm', array(80, 180 + count($carrito) * 6));
                $pdf->AddPage();
                $pdf->SetMargins(5, 5, 5);

                if (file_exists('includes/logo.png')) {
                    $pdf->Image('includes/logo.png', 25, 5, 30);
                    $pdf->Ln(25);
                }

                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(0, 6, utf8_decode('Tienda Pescadores'), 0, 1, 'C');
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(0, 5, utf8_decode('RFC: PESC123456789'), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('Av. Principal #45, Col. Centro'), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('Tel: 222-555-8899'), 0, 1, 'C');
                $pdf->Ln(3);
                $pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
                $pdf->Ln(3);

                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(0, 5, utf8_decode('TICKET DE COMPRA'), 0, 1, 'C');
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(0, 5, 'Folio: ' . $folio, 0, 1, 'C');
                $pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('Cliente: ') . $correo_cliente, 0, 1, 'C');
                $pdf->Ln(3);
                $pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
                $pdf->Ln(3);

                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(32, 5, utf8_decode('Producto'), 0, 0, 'L');
                $pdf->Cell(10, 5, utf8_decode('Cant'), 0, 0, 'C');
                $pdf->Cell(15, 5, utf8_decode('P.U.'), 0, 0, 'C');
                $pdf->Cell(18, 5, utf8_decode('Importe'), 0, 1, 'R');
                $pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
                $pdf->Ln(2);

                $pdf->SetFont('Arial', '', 9);
                foreach ($carrito as $p) {
                    $nombreProducto = utf8_decode($p['nombre']);
                    if (strlen($nombreProducto) > 18) $nombreProducto = substr($nombreProducto, 0, 18) . '...';
                    $importe = $p['precio'] * $p['cantidad'];

                    $pdf->Cell(32, 5, $nombreProducto, 0, 0, 'L');
                    $pdf->Cell(10, 5, $p['cantidad'], 0, 0, 'C');
                    $pdf->Cell(15, 5, number_format($p['precio'], 2), 0, 0, 'C');
                    $pdf->Cell(18, 5, number_format($importe, 2), 0, 1, 'R');
                }

                $pdf->Ln(3);
                $pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
                $pdf->Ln(4);

                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(45, 5, 'Subtotal:', 0, 0, 'R');
                $pdf->Cell(25, 5, '$' . number_format($subtotal, 2), 0, 1, 'R');
                $pdf->Cell(45, 5, 'IVA (16%):', 0, 0, 'R');
                $pdf->Cell(25, 5, '$' . number_format($iva, 2), 0, 1, 'R');
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(45, 7, 'TOTAL:', 0, 0, 'R');
                $pdf->Cell(25, 7, '$' . number_format($total, 2), 0, 1, 'R');
                $pdf->Ln(3);
                $pdf->Line(5, $pdf->GetY(), 75, $pdf->GetY());
                $pdf->Ln(4);

                $pdf->SetFont('Arial', '', 9);
                $pdf->MultiCell(0, 5, utf8_decode("Gracias por su compra.\nConserve este ticket como comprobante."), 0, 'C');
                $pdf->Ln(3);
                $pdf->Cell(0, 5, utf8_decode('¡Vuelva pronto!'), 0, 1, 'C');
                $pdf->SetFont('Arial', 'I', 8);
                $pdf->Cell(0, 5, utf8_decode('Atendido por: ') . ($_SESSION['nombre'] ?? 'Vendedor'), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('Sistema de Ventas Tienda Pescadores © 2025'), 0, 1, 'C');

                $nombreArchivo = 'ticket_' . $folio . '.pdf';
                $rutaArchivo = 'tickets/' . $nombreArchivo;
                $pdf->Output('F', $rutaArchivo);
                $conn->query("UPDATE ventas SET ticket_pdf = '$nombreArchivo' WHERE folio_ticket = '$folio'");

                // Guardar nombre del ticket en la venta principal
                $conn->query("UPDATE ventas SET ticket_pdf = '$nombreArchivo' WHERE id = $idVentaPrincipal");
                // Enviar ticket por correo (opcional)
                $ticketEnviado = false;
                if (!empty($correo_cliente)) {
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
                        $mail->Subject = 'Ticket de compra - Tienda Pescadores';
                        $mail->Body = "Gracias por su compra.\nAdjuntamos su ticket en formato PDF.";
                        $mail->addAttachment($rutaArchivo);
                        $mail->send();
                        $ticketEnviado = true;
                    } catch (Exception $e) {}
                }

                $mensaje = "✅ Venta registrada correctamente. Cambio: $" . number_format($cambio, 2);
                if ($ticketEnviado) $mensaje .= "\\nTicket enviado correctamente a $correo_cliente";

                $alerta = ['tipo' => 'success', 'titulo' => 'Venta registrada', 'mensaje' => $mensaje];
            }
        }
    }
}
?>

<style>
/* =====================================================
   BASE GENERAL
===================================================== */

.content-wrapper {
  min-height: 100vh;
  padding: 20px;
  background: radial-gradient(circle at top, #fff7ed, #f8f9fa);
}


/* =====================================================
   CARD PRINCIPAL
===================================================== */

.pos-card{
  min-height:95vh;
  border:none;
  border-radius:24px;
  box-shadow:0 12px 40px rgba(0,0,0,.12);
  background:#f8fafc;
  overflow:hidden;
}

/* =====================================================
   HEADER
===================================================== */

.pos-header{
  background:linear-gradient(135deg,#f4a261,#e76f51);
  color:white;
  padding:18px 28px;
  box-shadow:0 6px 18px rgba(0,0,0,.18);
}

.pos-header h3{
  font-weight:900;
  letter-spacing:.6px;
}

/* =====================================================
   BUSCADOR
===================================================== */

.pos-buscador{
  display:flex;
  gap:14px;
  background:white;
  padding:18px;
  border-radius:18px;
  box-shadow:0 6px 18px rgba(0,0,0,.08);
  align-items:center;
  transition:.2s;
}

.pos-buscador:focus-within{
  box-shadow:0 0 0 3px rgba(244,162,97,.35), 0 10px 24px rgba(0,0,0,.12);
}

.pos-buscador input{
  font-size:18px;
  border:none;
  outline:none;
}

/* =====================================================
   TABLA CARRITO
===================================================== */

.table-responsive {
  width: 100%;
  overflow-x: auto;
  max-height: 75vh;
}

.pos-tabla{
  background:white;
  border-radius:20px;
  overflow:hidden;
  box-shadow:0 8px 22px rgba(0,0,0,.08);
}

.pos-tabla thead{
  background:linear-gradient(135deg,#f4a261,#e76f51);
  color:white;
}

.table-hover tbody tr {
  background:#fff;
  border-radius:12px;
  box-shadow:0 3px 10px rgba(0,0,0,0.06);
  transition:.2s;
}

.table-hover tbody tr:hover {
  background:#fff2e6;
  transform:scale(1.01);
}

.table-hover tbody tr td {
  border:none !important;
  vertical-align: middle;
}

/* =====================================================
   TOTALES
===================================================== */

.pos-totales .form-group,
.pos-correo{
  background:white;
  padding:16px;
  border-radius:16px;
  box-shadow:0 4px 14px rgba(0,0,0,.06);
}

.pos-total{
  font-size:32px;
  font-weight:900;
  text-align:center;
  color:#16a34a;
  background:#ecfdf5;
  border:none;
}

.pos-input{
  border:none;
  background:#f1f5f9;
  border-radius:10px;
  font-weight:600;
}

.form-control[readonly] {
  background:#fff2e6;
  font-weight:bold;
}

/* =====================================================
   BOTONES
===================================================== */

.btn-agregar {
  background:#f4a261; 
  color:white; 
  width:100%;
  font-weight:700;
  border-radius:10px;
}
.btn-agregar:hover {
  background:#e76f51;
}

.btn-registrar {
  background:#e76f51; 
  color:white; 
  font-size:1.2em; 
  padding:12px 25px;
  border-radius:12px;
}
.btn-registrar:hover {
  background:#f4a261;
}

.pos-btn-venta{
  background:linear-gradient(135deg,#22c55e,#16a34a);
  border:none;
  padding:18px 32px;
  font-size:20px;
  font-weight:900;
  border-radius:18px;
  color:white;
  box-shadow:0 12px 28px rgba(34,197,94,.45);
  transition:.2s;
  width:100%;
  font-size:26px;
  padding:22px;
  letter-spacing:1px;
}

.pos-btn-venta:hover{
  transform:scale(1.05);
}

/* =====================================================
   MÉTODOS DE PAGO
===================================================== */
.metodos-wrapper {
  display: flex;
  justify-content: center;
  width: 100%;
  margin-bottom: 20px;
}

.pos-metodos,
.metodos-container {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
  gap:18px;
  padding:10px;
}

.pos-metodo,
.metodo-radio {
  background:white;
  border-radius:18px;
  padding:16px;
  text-align:center;
  box-shadow:0 6px 18px rgba(0,0,0,.08);
  transition:.25s;
  cursor:pointer;
  border:2px solid transparent;
}

.pos-metodo:hover,
.metodo-radio:hover{
  transform:translateY(-4px) scale(1.04);
  border-color:#f4a261;
  background:#fff7ed;
}

.metodo-radio input {
  display: none;
}

.metodo-radio img.icono-metodo {
  width: 45px;
  margin-bottom: 8px;
}

.metodo-radio span {
  font-weight: 700;
}

.metodo-radio input:checked + img,
.metodo-radio input:checked ~ span {
  filter: drop-shadow(0 0 6px #f4a261);
}

/* =====================================================
   TARJETAS (SI LAS USAS)
===================================================== */

.tarjeta-container {
  display: flex;
  gap: 20px;
  justify-content: center;
  margin-top: 10px;
  flex-wrap: wrap;
}

.tarjeta-item {
  min-width: 180px;
}

/* =====================================================
   EFECTOS PREMIUM
===================================================== */

.pos-card *{
  transition:.15s ease;
}

/* ANIMACIÓN ENTRADA PRODUCTO */
@keyframes slideIn {
  from {opacity:0; transform:translateX(30px) scale(.95);}
  to {opacity:1; transform:translateX(0) scale(1);}
}

.producto-animado {
  animation: slideIn .35s cubic-bezier(.4,0,.2,1);
}

/* PARPADEO TOTAL */
@keyframes blinkTotal {
  0%{box-shadow:0 0 0 rgba(34,197,94,0);}
  50%{box-shadow:0 0 25px rgba(34,197,94,.9);}
  100%{box-shadow:0 0 0 rgba(34,197,94,0);}
}

.total-flash {
  animation: blinkTotal .45s ease;
}

/* EFECTO PAGO */
.pago-ok {
  animation: pagoPulse .6s ease;
}

@keyframes pagoPulse {
  0%{transform:scale(1);}
  50%{transform:scale(1.07);}
  100%{transform:scale(1);}
}

</style>


<div class="content-wrapper">
  <div class="container-fluid">

    <h2 class="mb-4 text-center font-weight-bold" style="letter-spacing:.5px;">
       Punto de venta
    </h2>

    <div class="row justify-content-center">
      <div class="col-12">

        <div class="card pos-card">

          <div class="card-header pos-header">
            <h3 class="card-title mb-0">
              <i class="fas fa-cash-register mr-2"></i> Registro de venta
            </h3>
          </div>

          <div class="card-body">

            <form method="POST" id="ventaForm">

              <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="registrar_venta" value="1">
              <input type="hidden" name="carrito_json" id="carrito_json">

              <!-- BUSCADOR -->
              <div class="pos-buscador mb-4">
                <i class="fas fa-barcode text-muted fa-lg"></i>
                <input type="text" class="form-control border-0" id="codigo" placeholder="Escanea o escribe el código del producto">
                <button type="button" class="btn btn-warning btn-md" onclick="agregarProducto()">
                  <i class="fas fa-plus"></i> Agregar
                </button>
              </div>

              <!-- TABLA -->
              <div class="table-responsive mb-4 pos-tabla">
                <table class="table table-hover text-center mb-0">
                  <thead>
                    <tr>
                      <th>Producto</th>
                      <th width="120">Cantidad</th>
                      <th width="120">Precio</th>
                      <th width="120">Subtotal</th>
                      <th width="100">Acción</th>
                    </tr>
                  </thead>
                  <tbody id="carritoBody"></tbody>
                </table>
              </div>

              <!-- TOTALES -->
              <div class="row mb-4 pos-totales">

                <div class="col-md-4">
                  <div class="form-group">
                    <label>Total</label>
                    <input type="text" class="form-control pos-total" id="total" value="0.00" readonly>
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label>Monto pagado</label>
                    <input type="number" class="form-control pos-input" name="monto_pagado" id="monto_pagado" step="0.01" required>
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label>Cambio</label>
                    <input type="text" class="form-control pos-input" id="cambio" value="0.00" readonly>
                  </div>
                </div>

              </div>

              <!-- CORREO -->
              <div class="form-group mb-4 pos-correo">
                <label>Correo del cliente (opcional)</label>
                <input type="email" class="form-control pos-input" name="correo_cliente" id="correo_cliente" placeholder="cliente@correo.com">
              </div>

              <!-- MÉTODOS -->
              <div class="metodos-container pos-metodos">

                <label class="metodo-radio pos-metodo">
                  <input type="radio" name="metodo_pago" value="efectivo" checked onclick="mostrarCamposPago()">
                  <img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/svg/1f4b5.svg" class="icono-metodo">
                  <span>Efectivo</span>
                </label>

                <label class="metodo-radio pos-metodo">
                  <input type="radio" name="metodo_pago" value="transferencia" onclick="mostrarCamposPago()">
                  <img src="https://cdn-icons-png.flaticon.com/512/2331/2331947.png" class="icono-metodo">
                  <span>Transferencia</span>
                </label>

                <label class="metodo-radio pos-metodo">
                  <input type="radio" name="metodo_pago" value="tarjeta_debito" onclick="mostrarCamposPago()">
                  <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png" class="icono-metodo">
                  <span>Débito</span>
                </label>

                <label class="metodo-radio pos-metodo">
                  <input type="radio" name="metodo_pago" value="tarjeta_credito" onclick="mostrarCamposPago()">
                  <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Mastercard-logo.png" class="icono-metodo">
                  <span>Crédito</span>
                </label>

              </div>

              <div id="extraCampos">
                <input type="hidden" id="id_pago" name="id_pago" value="">
              </div>

              <!-- BOTÓN -->
              <div class="form-group text-right mt-4">
                <button type="button" class="btn pos-btn-venta" onclick="confirmarVenta()">
                  <i class="fas fa-cash-register mr-2"></i> Confirmar venta
                </button>
              </div>

            </form>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<audio id="sonidoCaja" preload="auto">
  <source src="https://assets.mixkit.co/sfx/preview/mixkit-cash-register-purchase-2759.mp3" type="audio/mpeg">
</audio>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

localStorage.removeItem('carrito');
carrito = [];
async function agregarProducto() {
  const codigo = document.getElementById('codigo').value.trim();
  if (!codigo) return Swal.fire('Atención', 'Ingresa o escanea un código.', 'warning');

  const res = await fetch(`buscar_producto.php?codigo=${codigo}`);
  const data = await res.json();
  if (!data.success) return Swal.fire('Error', data.message, 'error');

  const existente = carrito.find(p => p.id === data.id);
  if (existente) {
    if (existente.cantidad < data.stock) existente.cantidad++;
    else return Swal.fire('Sin stock', 'Ya alcanzaste el stock disponible.', 'error');
  } else {
    carrito.push({ id: data.id, nombre: data.nombre, precio: parseFloat(data.precio_venta), cantidad: 1, stock: parseInt(data.stock), imagen: data.imagen });
  }

  document.getElementById('codigo').value = '';
  guardarCarrito();
  renderCarrito();
}

function renderCarrito() {
  const body = document.getElementById('carritoBody');
  body.innerHTML = '';
  let total = 0;
  carrito.forEach((item, index) => {
    const subtotal = item.precio * item.cantidad;
    total += subtotal;
    body.innerHTML += `
      <tr>
        <td style="display:flex; align-items:center; gap:8px;">
          <img src="${item.imagen}" width="40" height="40" style="border-radius:6px; object-fit:cover;">
          <div style="text-align:left;"><strong>${item.nombre}</strong><br><small>Stock: ${item.stock}</small></div>
        </td>
        <td><input type="number" min="1" max="${item.stock}" value="${item.cantidad}" onchange="actualizarCantidad(${index}, this.value)"></td>
        <td>$${item.precio.toFixed(2)}</td>
        <td>$${subtotal.toFixed(2)}</td>
       <td>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="eliminarProducto(${index})" title="Eliminar producto">
          <i class="fas fa-trash-alt"></i>
        </button>
      </td>
      </tr>`;
  });
  document.getElementById('total').value = total.toFixed(2);
  actualizarCambio();
}

function guardarCarrito() { localStorage.setItem('carrito', JSON.stringify(carrito)); }
function cargarCarrito() { carrito = JSON.parse(localStorage.getItem('carrito') || '[]'); renderCarrito(); }
window.addEventListener('load', cargarCarrito);
function actualizarCantidad(index, val) { carrito[index].cantidad = parseInt(val) || 1; renderCarrito(); }
function eliminarProducto(index) { carrito.splice(index, 1); renderCarrito(); }
function actualizarCambio() {
  const total = parseFloat(document.getElementById('total').value) || 0;
  const pago = parseFloat(document.getElementById('monto_pagado').value) || 0;
  document.getElementById('cambio').value = (pago - total).toFixed(2);
}
document.getElementById('monto_pagado').addEventListener('input', actualizarCambio);

function confirmarVenta() {
  if (carrito.length === 0) return Swal.fire('Atención', 'No hay productos en el carrito.', 'warning');
  const total = parseFloat(document.getElementById('total').value);
  const pago = parseFloat(document.getElementById('monto_pagado').value);
  if (!pago || pago <= 0) return Swal.fire('Atención', 'Ingresa un monto válido.', 'warning');

  let resumen = carrito.map(p => `${p.nombre} x${p.cantidad} - $${(p.precio*p.cantidad).toFixed(2)}`).join('<br>');
  Swal.fire({
    title: 'Confirmar venta',
    html: `${resumen}<hr><strong>Total:</strong> $${total.toFixed(2)}<br><strong>Pago:</strong> $${pago.toFixed(2)}<br><strong>Cambio:</strong> $${(pago-total).toFixed(2)}`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Registrar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#e76f51'
  }).then(r => {
    if (r.isConfirmed) {
      document.getElementById('carrito_json').value = JSON.stringify(carrito);
      document.getElementById('ventaForm').submit();
    }
  });
}

<?php if(isset($alerta)): ?>
Swal.fire({
  icon: '<?php echo $alerta['tipo']; ?>',
  title: '<?php echo $alerta['titulo']; ?>',
  text: '<?php echo $alerta['mensaje']; ?>',
  confirmButtonColor: '#e76f51'
}).then(() => { 
  <?php if($alerta['tipo']==='success'): ?> localStorage.removeItem('carrito'); carrito=[]; renderCarrito(); <?php endif; ?>
});
<?php endif; ?>


function mostrarCamposPago() {
    let metodo = document.querySelector('input[name="metodo_pago"]:checked').value;
    let extra = document.getElementById("extraCampos");

    if (metodo === "efectivo") {
        extra.innerHTML = "";
    }

    if (metodo === "transferencia") {
        extra.innerHTML = `
            <div class="form-group">
            <label>Folio de transferencia</label>
            <input type="text" class="form-control" name="referencia_pago" id="folio_transferencia" placeholder="Ej: TRX87439210" maxlength="20" oninput="formatearFolioTransferencia(this)">          
            </div>
        `;
    }

    if (metodo === "tarjeta_debito" || metodo === "tarjeta_credito") {
        extra.innerHTML = `
          <div class="tarjeta-container">
            <input type="text" class="form-control" id="ultimos4"
            name="ultimos4" maxlength="4" placeholder="Ej: 4921"
            oninput="this.value = this.value.replace(/\D/g,''); detectarTipoTarjeta();">
            <input type="hidden" name="tipo_tarjeta_detectada" id="tipo_tarjeta_detectada">

            <div class="form-group">
                <label>Últimos 4 dígitos</label>
                <input type="text" class="form-control" id="ultimos4"
                    name="ultimos4" maxlength="4" placeholder="Ej: 4921"
                    oninput="this.value = this.value.replace(/\D/g,'')">
            </div>

            <div class="form-group">
                <label>Tipo de tarjeta (detectado automáticamente)</label>
                <select class="form-control" id="tipo_tarjeta" name="tipo_tarjeta" disabled>
                    <option value="">Detectando...</option>
                    <option value="VISA">VISA</option>
                    <option value="MASTERCARD">MASTERCARD</option>
                    <option value="AMEX">AMERICAN EXPRESS</option>
                    <option value="DISCOVER">DISCOVER</option>
                    <option value="CARNET">CARNET</option>
                    <option value="VALES">VALES</option>
                    <option value="OTRA">OTRA</option>
                </select>
            </div>

           <div class="form-group">
                <label>Folio de autorización</label>
                <input type="text" class="form-control" name="folio_autorizacion"
                    maxlength="16" id="folio_autorizacion"
                  placeholder="Ej: AUTH-938492" oninput="validarFolio(this)">
            </div>

            <div class="form-group">
                <label>Número de referencia</label>
                <input type="text" class="form-control" name="referencia_pago"
                    maxlength="20" id="referencia_pago"
                  placeholder="Ej: REF-39482091" oninput="validarReferencia(this)">
            </div>
          </div>
        `;
    }
}

function formatearFolioTransferencia(input) {
    let valor = input.value;

    // Solo letras MAYÚSCULAS y números
    valor = valor.toUpperCase().replace(/[^A-Z0-9]/g, "");

    // Longitud típica bancos: 8 a 20 caracteres
    valor = valor.substring(0, 20);

    input.value = valor;
}
function validarFolio(input) {
    let value = input.value;

    // Solo letras y números — NO espacios, NO símbolos
    value = value.toUpperCase().replace(/[^A-Z0-9]/g, "");

    // Máximo 16 caracteres
    value = value.substring(0, 16);

    input.value = value;
}

function validarReferencia(input) {
    let value = input.value;

    // Solo letras y números
    value = value.toUpperCase().replace(/[^A-Z0-9]/g, "");

    // Máximo 20 caracteres
    value = value.substring(0, 20);

    input.value = value;
}

function detectarTipoTarjeta() {
    const ul4 = document.getElementById("ultimos4").value;
    const tipoSelect = document.getElementById("tipo_tarjeta");
    const oculto = document.getElementById("tipo_tarjeta_detectada");

    let tipo = "OTRA";

    // ---------------------------
    // TARJETAS COMUNES EN MÉXICO
    // ---------------------------

    // VISA (empieza con 4)
    if (/^4/.test(ul4)) tipo = "VISA";

    // Mastercard (51–55 o 2221–2720)
    else if (/^(5[1-5]|22[2-9]|2[3-7])/.test(ul4)) tipo = "MASTERCARD";

    // American Express (34 o 37)
    else if (/^(34|37)/.test(ul4)) tipo = "AMEX";

    // Discover (6011, 65, 644-649)
    else if (/^(6011|65|64[4-9])/.test(ul4)) tipo = "DISCOVER";

    // CARNET (tarjetas nacionales mexicanas)
    // Ejemplos reales: 2869, 5020, 5043, 5060, 5887
    else if (/^(2869|5020|5043|5060|5887)/.test(ul4)) tipo = "CARNET";

    // Tarjetas de VALORES / VALES (Sí Vale, Edenred, Sodexo)
    else if (/^(6060|6061|6062|6277)/.test(ul4)) tipo = "VALES";

    // Fallback
    else tipo = "OTRA";

    tipoSelect.value = tipo;
    oculto.value = tipo;
}

document.getElementById('codigo').addEventListener('keydown', function(e){
    if(e.key === 'Enter'){
        e.preventDefault();
        agregarProducto();
    }
});
</script>