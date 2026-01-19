<?php
include('includes/db.php');
include('includes/header.php');
include('includes/session.php');
require_once('includes/csrf.php');

$max_attempts = 5;
$lock_time = 60; // 1 minutos

// Inicializar contador en sesión
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = 0;
}

$swal = "";

// PROCESAR LOGIN
if (isset($_POST['login'])) {
    csrf_check();

    // Bloqueo temporal
    if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['login_last_attempt']) < $lock_time) {
        $wait = $lock_time - (time() - $_SESSION['login_last_attempt']);
        $swal = "
        Swal.fire({
            icon: 'error',
            title: 'Demasiados intentos',
            text: 'Intenta de nuevo en {$wait} segundos',
            confirmButtonColor: '#ff7b00'
        });
        ";
    } else {

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, nombre, password, rol, activo FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $nombre, $hashed_password, $rol, $activo);
            $stmt->fetch();

            if (!$activo) {
                $swal = "
                Swal.fire({
                    icon: 'warning',
                    title: 'Cuenta inactiva',
                    text: 'Tu cuenta aún no ha sido activada por un administrador',
                    confirmButtonColor: '#ff7b00'
                });
                ";
            } elseif (password_verify($password, $hashed_password)) {

                // Login exitoso
                $_SESSION['login_attempts'] = 0;
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = $id;
                $_SESSION['nombre'] = $nombre;
                $_SESSION['rol'] = $rol;
                $_SESSION['last_activity'] = time();

                $redirect = ($rol === 'administrador')
                    ? 'dashboard_admin.php'
                    : 'dashboard_vendedor.php';

                echo "<script>
                    Swal.fire({
                        title: '¡Bienvenido!',
                        html: '<b>{$nombre}</b><br><small>Accediendo al sistema...</small>',
                        icon: 'success',
                        timer: 2200,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    }).then(() => {
                        window.location.href = '{$redirect}';
                    });
                </script>";
                exit;

            } else {
                $swal = "
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Contraseña incorrecta',
                    confirmButtonColor: '#d33'
                });
                ";
            }
        } else {
            $swal = "
            Swal.fire({
                icon: 'warning',
                title: 'Correo no registrado',
                text: 'Verifica tu correo electrónico',
                confirmButtonColor: '#f0ad4e'
            });
            ";
        }

        $stmt->close();

        $_SESSION['login_attempts'] += 1;
        $_SESSION['login_last_attempt'] = time();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body.login-page {
            background: linear-gradient(135deg, #fff4e6, #ffe2c4);
        }

        @media (prefers-color-scheme: dark) {
            body.login-page {
                background: linear-gradient(135deg, #2b2b2b, #1c1c1c);
            }
        }

        .login-box {
            width: 100%;
        }

        .card {
            border-radius: 15px;
            border-top: 5px solid #ff7b00;
            box-shadow: 0 4px 18px rgba(0,0,0,0.2);
            animation: fadeIn 0.7s ease;
        }

        .login-logo img {
            width: 80px;
        }

        .logo-inline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
        }

        .btn-orange {
            background: #ff7b00;
            color: white;
            font-weight: bold;
        }

        .btn-orange:hover {
            background: #e36c00;
            color: white;
        }

        .form-control:focus {
            border-color: #ff7b00;
            box-shadow: 0 0 8px rgba(255,123,0,.4);
        }

        @media (max-width: 576px) {
            .logo-inline {
                flex-direction: column;
            }
            .login-logo img {
                width: 65px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .text-orange {
            color: #ff7b00;
        }

        .btn-orange {
            background: linear-gradient(135deg, #ff7b00, #ff9800);
            color: #fff;
            border: none;
        }

        .btn-orange:hover {
            background: linear-gradient(135deg, #e66a00, #ff7b00);
            color: #fff;
        }

        .hover-underline {
            position: relative;
            text-decoration: none;
        }

        .hover-underline::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -4px;
            width: 0;
            height: 2px;
            background: #ff7b00;
            transition: all .3s ease;
            transform: translateX(-50%);
        }

        .hover-underline:hover::after {
            width: 100%;
        }
    </style>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ff7b00">
    <meta name="apple-mobile-web-app-capable" content="yes">

</head>

<body class="hold-transition login-page">

<div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <div class="login-box">
                <div class="login-logo logo-inline">
                    <img src="includes/logo_login.png">
                    <b>Pescadores</b> de la prehistoria
                </div>

                <div class="card">
                    <div class="card-body login-card-body">

                        <p class="login-box-msg">Inicia sesión para continuar</p>

                        <form method="POST">
                            <div class="input-group mb-3">
                                <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <span class="fas fa-envelope"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="input-group mb-3">
                                <input type="password" id="password" name="password" class="form-control" placeholder="Contraseña" required>
                                <div class="input-group-append">
                                    <div class="input-group-text password-toggle" onclick="togglePassword()">
                                        <span class="fas fa-eye" id="eye"></span>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">

                            <button type="submit"
                                    name="login"
                                    class="btn btn-orange btn-lg btn-block shadow-sm d-flex align-items-center justify-content-center gap-2"
                                    id="loginBtn">

                                <span class="btn-text font-weight-bold">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Iniciar sesión
                                </span>

                                <span class="spinner-border spinner-border-sm d-none"
                                    id="loader"
                                    role="status"
                                    aria-hidden="true">
                                </span>
                            </button>
                            </form>

                            <!-- LINKS -->
                            <div class="mt-4 text-center">

                                <a href="forgot_password.php"
                                class="d-inline-block text-orange font-weight-semibold mb-2 hover-underline">
                                    <i class="fas fa-unlock-alt mr-1"></i> ¿Olvidaste tu contraseña?
                                </a>

                                <hr class="my-3" style="max-width:220px; margin:auto;">

                                <p class="mb-0">
                                    ¿No tienes cuenta?
                                    <a href="registrar.php"
                                    class="text-orange font-weight-bold hover-underline">
                                        Regístrate aquí
                                    </a>
                                </p>

                            </div>
                        </p>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if (!empty($swal)): ?>
<script>
    <?= $swal ?>
</script>
<?php endif; ?>
<script>
// 👁️ Mostrar / ocultar contraseña
function togglePassword() {
    const pass = document.getElementById('password');
    const eye = document.getElementById('eye');

    if (pass.type === 'password') {
        pass.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        pass.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ⏳ Loader al enviar
document.querySelector('form').addEventListener('submit', () => {
    document.querySelector('.btn-text').classList.add('d-none');
    document.getElementById('loader').classList.remove('d-none');
});

// 📱 Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js');
}
</script>

<?php if (!empty($_SESSION['swal'])): ?>
<script src="adminlte/plugins/sweetalert2/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

    Swal.fire({
        icon: '<?= $_SESSION['swal']['icon'] ?>',
        title: '<?= $_SESSION['swal']['title'] ?>',
        html: '<?= $_SESSION['swal']['html'] ?>',
        timer: <?= $_SESSION['swal']['timer'] ?? 'null' ?>,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        },
        didClose: () => {
            <?php if (!empty($_SESSION['swal']['redirect'])): ?>
                window.location.href = "<?= $_SESSION['swal']['redirect'] ?>";
            <?php endif; ?>
        }
    });

});

document.getElementById('loginBtn')?.addEventListener('click', () => {
    document.querySelector('.btn-text').classList.add('d-none');
    document.getElementById('loader').classList.remove('d-none');
});

</script>
<?php unset($_SESSION['swal']); endif; ?>

</body>
</html>
