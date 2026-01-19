<?php
// registrar_usuario.php - Panel para admin: crear, listar, editar, reset pwd, eliminar

include 'includes/session.php';      // session_start() seguro
include 'includes/db.php';
include 'includes/csrf.php';

// Verificar que es admin
if (!isset($_SESSION['usuario_id']) || strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    header("Location: login.php");
    exit;
}

// Variables para mensajes
$errors = [];
$success = '';

// Parámetros de seguridad / política
$min_password_length = 8;

// Manejo de acciones POST: crear, editar, reset, eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Crear usuario
    if (isset($_POST['action']) && $_POST['action'] === 'create') {

        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = ($_POST['rol'] === 'administrador') ? 'administrador' : 'vendedor';
        $password_plain = $_POST['password'] ?? '';

        // Validaciones
        if ($nombre === '' || $email === '' || $password_plain === '') {
            $errors[] = "Completa todos los campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email no válido.";
        } elseif (strlen($password_plain) < $min_password_length) {
            $errors[] = "La contraseña debe tener al menos $min_password_length caracteres.";
        } else {

            // Insertar usuario
            $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
            $created_by = $_SESSION['usuario_id'];

            $stmt = $conn->prepare("
                INSERT INTO usuarios (nombre, email, password, rol, activo, created_by)
                VALUES (?, ?, ?, ?, 1, ?)
            ");

            if (!$stmt) {
                $errors[] = "Error en la base de datos: " . $conn->error;
            } else {
                $stmt->bind_param("ssssi", $nombre, $email, $password_hashed, $rol, $created_by);
                
                if ($stmt->execute()) {
                    $success = "Usuario creado y activado correctamente.";
                } else {
                    $errors[] = "No se pudo crear el usuario. ¿El correo ya existe?";
                }

                $stmt->close();
            }
        }
    }

    // Editar usuario
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = ($_POST['rol'] === 'administrador') ? 'administrador' : 'vendedor';
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id <= 0 || $nombre === '' || $email === '') {
            $errors[] = "Datos inválidos para editar.";
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?");
            if (!$stmt) { $errors[] = "Error en DB: " . $conn->error; }
            else {
                $stmt->bind_param("sssii", $nombre, $email, $rol, $activo, $id);
                if ($stmt->execute()) {
                    $success = "Usuario actualizado correctamente.";
                } else {
                    $errors[] = "Error al actualizar. ¿Email duplicado?";
                }
                $stmt->close();
            }
        }
    }

    // Reset password (admin establece nueva contraseña)
    if (isset($_POST['action']) && $_POST['action'] === 'reset_pwd') {
        $id = intval($_POST['id'] ?? 0);
        $new_pwd = $_POST['new_password'] ?? '';

        if ($id <= 0 || $new_pwd === '') {
            $errors[] = "Datos inválidos para restablecer contraseña.";
        } elseif (strlen($new_pwd) < $min_password_length) {
            $errors[] = "La contraseña debe tener al menos $min_password_length caracteres.";
        } else {
            $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            if (!$stmt) { $errors[] = "Error en DB: " . $conn->error; }
            else {
                $stmt->bind_param("si", $hash, $id);
                if ($stmt->execute()) {
                    $success = "Contraseña restablecida correctamente.";
                    // Aquí podrías enviar un correo al usuario con la nueva contraseña o un enlace para cambiarla
                } else {
                    $errors[] = "No se pudo restablecer la contraseña.";
                }
                $stmt->close();
            }
        }
    }

    // Eliminar usuario
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = "ID inválido para eliminar.";
        } else {
            // Evitar que el admin se borre a sí mismo
            if ($id === $_SESSION['usuario_id']) {
                $errors[] = "No puedes eliminar tu propia cuenta mientras estás conectado.";
            } else {
                $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
                if (!$stmt) { $errors[] = "Error en DB: " . $conn->error; }
                else {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $success = "Usuario eliminado correctamente.";
                    } else {
                        $errors[] = "No se pudo eliminar el usuario.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Obtener lista de usuarios
$users = [];
$res = $conn->query("SELECT id, nombre, email, rol, activo, fecha_registro, created_by FROM usuarios ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    $res->free();
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<style>

.content-wrapper {
    margin-top: 10px !important;   /* Altura del navbar */
}

/*  Asegura que el sidebar empiece debajo del navbar */
.main-sidebar {
    margin-top: 70px !important;
}

/*  Ajuste para las tarjetas dentro del contenido */
.content-wrapper .container {
    margin-top: 20px !important;
}

.layout-navbar-fixed .wrapper .content-wrapper {
    padding-top: 0 !important;
}

.main-sidebar {
    top: 0 !important;
    padding-top: 60px !important; /* Acomoda el sidebar debajo del navbar */
}

.content-wrapper .container {
    margin-top: 15px !important; 
}

/* Responsive */
@media (max-width: 768px) {
    .content-wrapper {
        margin-top: 80px !important;
    }
    .main-sidebar {
        margin-top: 80px !important;
    }
}

details > summary {
    list-style: none;
}
details > summary::-webkit-details-marker {
    display: none;
}
.action-details[open] summary {
    background-color: rgba(0,0,0,.05);
}
</style>

<div class="content-wrapper">

    <?php if (!empty($success)): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                Swal.fire({
                    icon: "success",
                    title: "Operación exitosa",
                    text: "<?php echo $success; ?>",
                    confirmButtonColor: "#28a745"
                });
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    html: "<?php foreach ($errors as $e) { echo htmlspecialchars($e) . '<br>'; } ?>",
                    confirmButtonColor: "#e74c3c"
                });
            });
        </script>
    <?php endif; ?>

    <div class="container">

        <!-- ALERTAS SWEETALERT (Renderizadas por PHP si existen) -->
        <?php if (!empty($success)): ?>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "Éxito",
                    text: "<?php echo $success; ?>",
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($errors)): foreach ($errors as $err): ?>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "<?php echo htmlspecialchars($err); ?>",
                });
            </script>
        <?php endforeach; endif; ?>


        <!-- CARD CREAR USUARIO -->
        <div class="card card-outline card-warning shadow-lg mb-4">
            <!-- HEADER -->
            <div class="card-header bg-white">
                <h3 class="card-title font-weight-bold mb-0">
                    <i class="fas fa-user-plus text-warning mr-2"></i>
                    Crear nuevo usuario
                </h3>
            </div>

            <!-- BODY -->
            <div class="card-body">
                <form method="POST" autocomplete="off" onsubmit="return validarFormularioUsuario()">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                    <!-- NOMBRE -->
                    <div class="form-group">
                        <label>Nombre completo</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text"
                                name="nombre"
                                class="form-control"
                                placeholder="Ej. Juan Pérez"
                                required>
                        </div>
                    </div>

                    <!-- EMAIL -->
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            </div>
                            <input type="email"
                                name="email"
                                class="form-control"
                                placeholder="usuario@correo.com"
                                required>
                        </div>
                    </div>

                    <!-- ROL -->
                    <div class="form-group">
                        <label>Rol del usuario</label>
                        <select name="rol" class="form-control">
                            <option value="vendedor">Vendedor – Acceso a ventas</option>
                            <option value="administrador">Administrador – Control total</option>
                        </select>
                    </div>

                    <!-- CONTRASEÑA -->
                    <div class="form-group">
                        <label>Contraseña</label>
                        <div class="input-group">
                            <input type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Mínimo <?php echo $min_password_length; ?> caracteres"
                                onkeyup="validarPassword()"
                                required>

                            <div class="input-group-append">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="togglePassword('password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- FUERZA -->
                        <div class="progress mt-2" style="height:6px;">
                            <div id="passwordStrength"
                                class="progress-bar"
                                role="progressbar"
                                style="width:0%">
                            </div>
                        </div>
                        <small id="passwordHelp" class="text-muted">
                            Usa letras, números y símbolos
                        </small>
                    </div>

                    <!-- CONFIRMAR CONTRASEÑA -->
                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <div class="input-group">
                            <input type="password"
                                id="confirm_password"
                                class="form-control"
                                placeholder="Repite la contraseña"
                                onkeyup="validarCoincidencia()"
                                required>

                            <div class="input-group-append">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="togglePassword('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small id="confirmHelp"></small>
                    </div>

                    <!-- BOTÓN -->
                    <button type="submit" class="btn btn-warning btn-block btn-lg mt-4">
                        <i class="fas fa-user-plus mr-1"></i> Crear usuario
                    </button>
                </form>
            </div>
        </div>

        <!-- CARD USUARIOS EXISTENTES -->
        <div class="card card-outline card-primary shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">
                    <i class="fas fa-users mr-2"></i>Usuarios existentes
                </h3>

                <input id="searchInput" type="text" class="form-control form-control-sm ml-auto" style="width: 220px" placeholder="Buscar usuario...">  
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover table-striped mb-0">
                        <thead class="bg-secondary">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Registro</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>

                                <td>
                                    <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                                </td>

                                <td><?= htmlspecialchars($u['email']) ?></td>

                                <td>
                                    <span class="badge badge-info text-capitalize">
                                        <?= $u['rol'] ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($u['activo']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <small><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></small>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group">

                                        <!-- EDITAR -->
                                        <button class="btn btn-sm btn-outline-primary"
                                                data-toggle="modal"
                                                data-target="#editUser<?= $u['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- CAMBIAR PASSWORD -->
                                        <button class="btn btn-sm btn-outline-warning"
                                                data-toggle="modal"
                                                data-target="#resetPwd<?= $u['id'] ?>">
                                            <i class="fas fa-key"></i>
                                        </button>

                                        <!-- ELIMINAR -->
                                        <form method="POST" action="registrar_usuario.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    onclick="eliminarUsuario(this.form, <?= $u['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> <!-- container -->
</div> <!-- content-wrapper -->

<?php foreach ($users as $u): ?>
<div class="modal fade" id="editUser<?= $u['id'] ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <form method="POST" onsubmit="return confirmEdit(this)" class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit mr-2"></i>Editar usuario
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">

                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">

                <div class="form-group">
                    <label>Nombre</label>
                    <input class="form-control"
                           name="nombre"
                           value="<?= htmlspecialchars($u['nombre']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input class="form-control" type="email"
                           name="email"
                           value="<?= htmlspecialchars($u['email']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" class="form-control">
                        <option value="vendedor" <?= $u['rol']==='vendedor'?'selected':'' ?>>Vendedor</option>
                        <option value="administrador" <?= $u['rol']==='administrador'?'selected':'' ?>>Administrador</option>
                    </select>
                </div>

                <div class="form-check">
                    <input type="checkbox"
                           class="form-check-input"
                           name="activo"
                           <?= $u['activo']?'checked':'' ?>>
                    <label class="form-check-label">Cuenta activa</label>
                </div>

            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Cancelar
                </button>
                <button class="btn btn-primary">
                    Guardar cambios
                </button>
            </div>

        </form>
    </div>
</div>
<div class="modal fade" id="resetPwd<?= $u['id'] ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <form method="POST" onsubmit="return confirmReset(this)" class="modal-content">

            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-key mr-2"></i>Restablecer contraseña
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">

                <input type="hidden" name="action" value="reset_pwd">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">

                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password"
                           class="form-control"
                           name="new_password"
                           placeholder="Mínimo 8 caracteres"
                           required>
                </div>

                <small class="text-muted">
                    El usuario deberá cambiarla al iniciar sesión.
                </small>

            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Cancelar
                </button>
                <button class="btn btn-warning">
                    Restablecer
                </button>
            </div>

        </form>
    </div>
</div>
<?php endforeach; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- BUSCADOR -->
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>


<!-- SWEET ALERT ACCIONES -->
<script>
/* ELIMINAR USUARIO */
function eliminarUsuario(form, id) {
    if (typeof Swal === 'undefined') {
        return confirm("¿Eliminar usuario ID " + id + "?");
    }

    Swal.fire({
        title: "¿Eliminar usuario?",
        text: "El usuario con ID " + id + " será eliminado.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#e74c3c",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then(r => {
        if (r.isConfirmed) form.submit();
    });

    return false;
}

/* EDITAR USUARIO */
function confirmEdit(form) {
    if (typeof Swal === 'undefined') {
        return confirm("¿Guardar cambios?");
    }

    Swal.fire({
        title: "¿Guardar cambios?",
        text: "Estás a punto de actualizar este usuario.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Guardar"
    }).then(r => {
        if (r.isConfirmed) form.submit();
    });

    return false;
}

/* RESETEAR CONTRASEÑA */
function confirmReset(form) {
    if (typeof Swal === 'undefined') {
        return confirm("¿Restablecer la contraseña?");
    }

    Swal.fire({
        title: "¿Restablecer contraseña?",
        text: "La contraseña será cambiada inmediatamente.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#f39c12",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Restablecer"
    }).then(r => {
        if (r.isConfirmed) form.submit();
    });

    return false;
}
</script>

<?php if (!empty($success)): ?>
<script>
Swal.fire({
    icon: "success",
    title: "Éxito",
    text: "<?= $success ?>",
    timer: 2500,
    showConfirmButton: false
});
</script>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<script>
Swal.fire({
    icon: "error",
    title: "Error",
    html: "<?= implode('<br>', $errors) ?>",
});
</script>
<?php endif; ?>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector("i");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    }
}

function validarPassword() {
    const pass = document.getElementById("password").value;
    const bar = document.getElementById("passwordStrength");
    const help = document.getElementById("passwordHelp");

    let strength = 0;

    if (pass.length >= 8) strength++;
    if (pass.length >= 12) strength++;
    if (/[a-z]/.test(pass)) strength++;
    if (/[A-Z]/.test(pass)) strength++;
    if (/[0-9]/.test(pass)) strength++;
    if (/[^A-Za-z0-9]/.test(pass)) strength++;

    bar.className = "progress-bar";

    const levels = [
        { pct: "10%", text: "Muy débil", color: "bg-danger" },
        { pct: "25%", text: "Débil", color: "bg-danger" },
        { pct: "40%", text: "Aceptable", color: "bg-warning" },
        { pct: "60%", text: "Media", color: "bg-info" },
        { pct: "80%", text: "Buena", color: "bg-primary" },
        { pct: "100%", text: "Fuerte", color: "bg-success" }
    ];

    const lvl = levels[Math.min(strength, levels.length - 1)];

    bar.style.width = lvl.pct;
    bar.classList.add(lvl.color);
    help.textContent = lvl.text;
}

function validarCoincidencia() {
    const pass = document.getElementById("password").value;
    const confirm = document.getElementById("confirm_password").value;
    const help = document.getElementById("confirmHelp");

    if (!confirm) {
        help.textContent = "";
        return;
    }

    if (pass === confirm) {
        help.textContent = "✔ Las contraseñas coinciden";
        help.className = "text-success";
    } else {
        help.textContent = "✖ Las contraseñas no coinciden";
        help.className = "text-danger";
    }
}

function validarFormularioUsuario() {
    const pass = document.getElementById("password").value;
    const confirm = document.getElementById("confirm_password").value;

    if (pass !== confirm) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Las contraseñas no coinciden"
        });
        return false;
    }

    if (pass.length < 8) {
        Swal.fire({
            icon: "warning",
            title: "Contraseña débil",
            text: "La contraseña debe tener al menos 8 caracteres"
        });
        return false;
    }

    return true;
}
</script>

