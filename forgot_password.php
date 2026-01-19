<?php
session_start();
include 'includes/db.php';
include 'includes/csrf.php';
include('includes/header.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';


// ===============================
// RATE LIMIT (ANTI-SPAM)
// ===============================
if (!isset($_SESSION['recover_attempts'])) {
    $_SESSION['recover_attempts'] = 0;
    $_SESSION['recover_time'] = time();
}

if (time() - $_SESSION['recover_time'] < 60 && $_SESSION['recover_attempts'] >= 3) {
    $_SESSION['swal'] = [
        'icon' => 'error',
        'title' => 'Demasiados intentos',
        'html' => 'Espera un minuto antes de intentarlo de nuevo'
    ];
    header('Location: forgot_password.php');
    exit;
}

if (time() - $_SESSION['recover_time'] > 60) {
    $_SESSION['recover_attempts'] = 0;
    $_SESSION['recover_time'] = time();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $_SESSION['recover_attempts']++;
    $email = trim($_POST['email']);

    /* ===============================
       VALIDAR USUARIO
    =============================== */
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    if (!$stmt) {
        die("Error SQL: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    /* ===============================
       ❌ CORREO NO REGISTRADO
    =============================== */
    if ($res->num_rows === 0) {
        $_SESSION['swal'] = [
            'icon'  => 'error',
            'title' => 'Correo no registrado',
            'html'  => 'El correo ingresado no se encuentra en nuestro sistema.'
        ];

        header('Location: forgot_password.php');
        exit;
    }

    /* ===============================
       CREAR TOKEN
    =============================== */
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 900); // 15 minutos

    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) {
        die("Error SQL: " . $conn->error);
    }

    $stmt->bind_param("sss", $email, $token, $expires);
    $stmt->execute();

    $resetLink = "http://localhost/tiendapescadores/reset_password.php?token=$token";

    /* ===============================
       ENVÍO DE CORREO (HTML PRO)
    =============================== */
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jesusgabrielmtz78@gmail.com';
        $mail->Password   = 'iwdf uyqu erzq wvbm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom('jesusgabrielmtz78@gmail.com', 'Tienda Pescadores');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Restablecer tu contraseña';

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Recuperar contraseña</title>
        </head>
        <body style="margin:0; padding:0; background:#f4f6f9; font-family: Arial, Helvetica, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="padding:40px 15px;">
                        <table width="600" cellpadding="0" cellspacing="0"
                            style="background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,0.08);">
                            
                            <!-- HEADER -->
                            <tr>
                                <td style="background:#E86100; padding:25px; text-align:center;">
                                    <h1 style="color:#ffffff; margin:0; font-size:24px;">
                                        Tienda Pescadores
                                    </h1>
                                </td>
                            </tr>

                            <!-- CONTENIDO -->
                            <tr>
                                <td style="padding:35px;">
                                    <h2 style="color:#333333; margin-top:0;">
                                        Restablecer contraseña
                                    </h2>

                                    <p style="color:#555555; font-size:15px; line-height:1.6;">
                                        Hemos recibido una solicitud para restablecer tu contraseña.
                                        Haz clic en el botón de abajo para continuar.
                                    </p>

                                    <div style="text-align:center; margin:35px 0;">
                                        <a href="'.$resetLink.'"
                                        style="
                                                background:#FF7A00;
                                                color:#ffffff;
                                                padding:14px 28px;
                                                text-decoration:none;
                                                font-size:16px;
                                                border-radius:5px;
                                                display:inline-block;
                                                font-weight:bold;
                                        ">
                                            Restablecer contraseña
                                        </a>
                                    </div>

                                    <p style="color:#777777; font-size:14px;">
                                        Este enlace es válido por <strong>15 minutos</strong>.
                                    </p>

                                    <hr style="border:none;border-top:1px solid #eeeeee;margin:30px 0;">

                                    <p style="color:#999999; font-size:12px;">
                                        Si el botón no funciona, copia y pega este enlace:
                                        <br>
                                        <a href="'.$resetLink.'"
                                        style="color:#FF7A00; word-break:break-all;">
                                            '.$resetLink.'
                                        </a>
                                    </p>
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td style="background:#FFF3E8; padding:15px; text-align:center;">
                                    <small style="color:#888888;">
                                        © '.date('Y').' Tienda Pescadores · Todos los derechos reservados
                                    </small>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->send();

        $_SESSION['swal'] = [
            'icon'  => 'success',
            'title' => 'Correo enviado',
            'html'  => 'Revisa tu bandeja de entrada para continuar.'
        ];

    } catch (Exception $e) {
        $_SESSION['swal'] = [
            'icon'  => 'error',
            'title' => 'Error',
            'html'  => 'No se pudo enviar el correo. Intenta más tarde.'
        ];
    }

    header('Location: forgot_password.php');
    exit;
}
?>
<style>
.bg-orange { background-color: #fd7e14 !important; }
.btn-orange {
    background-color: #fd7e14;
    color: #fff;
}
.btn-orange:hover {
    background-color: #e96b0c;
    color: #fff;
}
</style>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6">

            <div class="card card-outline card-orange shadow-lg border-0">

                <!-- HEADER -->
                <div class="card-header text-center bg-orange py-4">
                    <h3 class="mb-0 text-white">
                        <i class="fas fa-unlock-alt mr-1"></i> Recuperar contraseña
                    </h3>
                </div>

                <!-- BODY -->
                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">
                        <p class="text-muted mb-1" style="font-size:1.1rem;">
                            ¿Olvidaste tu contraseña?
                        </p>
                        <small class="text-muted">
                            Ingresa tu correo y te enviaremos un enlace para restablecerla
                        </small>
                    </div>

                    <form method="POST" id="recoverForm">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="input-group mb-4">
                            <input type="email"
                                   name="email"
                                   class="form-control form-control-lg"
                                   placeholder="Correo electrónico"
                                   required>
                            <div class="input-group-append">
                                <div class="input-group-text px-3">
                                    <span class="fas fa-envelope"></span>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="btn btn-orange btn-block btn-lg shadow-sm">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar enlace
                        </button>
                    </form>

                    <div class="mt-4 text-center">
                        <a href="login.php" class="text-muted">
                            <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="adminlte/plugins/sweetalert2/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('recoverForm');

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // 🔒 DESHABILITAR BOTÓN (ANTI DOBLE ENVÍO)
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            Swal.fire({
                title: 'Enviando enlace...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // ⏳ pequeño delay visual
            setTimeout(() => {
                form.submit();
            }, 500);
        });
    }
});
</script>


<?php if (!empty($_SESSION['swal'])): ?>
<script>
Swal.fire({
    icon: '<?= $_SESSION['swal']['icon'] ?>',
    title: '<?= $_SESSION['swal']['title'] ?>',
    html: '<?= $_SESSION['swal']['html'] ?>',
    confirmButtonText: 'Aceptar'
});
</script>
<?php unset($_SESSION['swal']); endif; ?>
