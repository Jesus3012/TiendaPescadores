<?php
session_start();
include 'includes/db.php';
include 'includes/csrf.php';
include 'includes/header.php';

$token = $_GET['token'] ?? '';

$stmt = $conn->prepare("
    SELECT email FROM password_resets
    WHERE token = ? AND expires_at > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows !== 1) {
?>
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center px-3">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-7 col-xl-6">

            <div class="card card-outline card-danger shadow-lg border-0">

                <!-- HEADER -->
                <div class="card-header text-center bg-danger py-4">
                    <h3 class="mb-0 text-white">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Enlace inválido
                    </h3>
                </div>

                <!-- BODY -->
                <div class="card-body text-center p-4 p-md-5">

                    <p class="text-muted mb-3" style="font-size:1.1rem;">
                        Este enlace ya fue utilizado o ha expirado.
                    </p>

                    <div class="my-4">
                        <i class="fas fa-link-slash fa-4x text-danger"></i>
                    </div>

                    <a href="forgot_password.php"
                       class="btn btn-outline-danger btn-lg btn-block mb-3">
                        <i class="fas fa-redo mr-1"></i> Solicitar nuevo enlace
                    </a>

                    <a href="login.php" class="text-muted d-block">
                        <i class="fas fa-arrow-left mr-1"></i> Volver al inicio
                    </a>

                </div>
            </div>

        </div>
    </div>
</div>
<?php
    exit;
}

$email = $res->fetch_assoc()['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($_POST['password'] !== $_POST['password_confirm']) {
        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => 'Las contraseñas no coinciden'
        ];
    } else {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $password, $email);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        $_SESSION['swal'] = [
            'icon'  => 'success',
            'title' => 'Contraseña restablecida',
            'html'  => 'Tu contraseña se actualizó correctamente.<br><small>Te redirigiremos al inicio de sesión</small>',
            'timer' => 2500,
            'redirect' => 'login.php'
        ];

        header('Location: login.php');
        exit;
    }
}
?>

<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center px-3">
    <div class="row w-100 justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">

            <div class="card card-outline card-success shadow-lg border-0">

                <!-- HEADER -->
                <div class="card-header text-center bg-success py-4 rounded-top">
                    <h3 class="mb-0 text-white font-weight-bold">
                        <i class="fas fa-shield-alt mr-2"></i> Nueva contraseña
                    </h3>
                </div>

                <!-- BODY -->
                <div class="card-body px-4 py-5 px-md-5">

                    <div class="text-center mb-4">
                        <p class="text-muted mb-1" style="font-size:1.05rem;">
                            Crea una contraseña segura para proteger tu cuenta
                        </p>
                        <small class="text-muted">
                            Usa al menos 8 caracteres
                        </small>
                    </div>

                    <form method="POST" id="resetForm">

                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <!-- PASSWORD -->
                        <div class="form-group mb-4">
                            <label class="text-muted small mb-1">Nueva contraseña</label>
                            <div class="input-group input-group-lg">
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       minlength="8"
                                       required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-pass" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CONFIRM -->
                        <div class="form-group mb-4">
                            <label class="text-muted small mb-1">Confirmar contraseña</label>
                            <div class="input-group input-group-lg">
                                <input type="password"
                                       id="password_confirm"
                                       name="password_confirm"
                                       class="form-control"
                                       minlength="8"
                                       required>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-pass" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small id="passHelp" class="d-none"></small>
                        </div>

                        <!-- BUTTON -->
                        <button type="submit"
                                class="btn btn-success btn-lg btn-block shadow-sm">
                            <i class="fas fa-check-circle mr-2"></i> Guardar contraseña
                        </button>
                    </form>

                    <!-- FOOTER -->
                    <div class="mt-4 text-center">
                        <a href="login.php" class="text-muted">
                            <i class="fas fa-arrow-left mr-1"></i> Volver al inicio
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- SweetAlert -->
<script src="adminlte/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
// Mostrar / ocultar contraseña

document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.input-group').querySelector('input');
        const icon  = btn.querySelector('i');

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        icon.classList.toggle('fa-eye', !isPassword);
        icon.classList.toggle('fa-eye-slash', isPassword);
    });
});

document.addEventListener('DOMContentLoaded', () => {

    const form  = document.getElementById('resetForm');
    const pass1 = document.getElementById('password');
    const pass2 = document.getElementById('password_confirm');
    const help  = document.getElementById('passHelp');

    if (!form) return;

    function validarPasswords() {

        if (pass2.value.length === 0) {
            help.className = 'd-none';
            pass2.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        if (pass1.value === pass2.value) {
            help.textContent = 'Las contraseñas coinciden';
            help.className = 'text-success';
            pass2.classList.remove('is-invalid');
            pass2.classList.add('is-valid');
            return true;
        } else {
            help.textContent = 'Las contraseñas no coinciden';
            help.className = 'text-danger';
            pass2.classList.remove('is-valid');
            pass2.classList.add('is-invalid');
            return false;
        }
    }

    pass1.addEventListener('input', validarPasswords);
    pass2.addEventListener('input', validarPasswords);

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!validarPasswords()) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas no coinciden'
            });
            return;
        }

        Swal.fire({
            title: 'Actualizando contraseña...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => form.submit(), 300);
    });

});
</script>




