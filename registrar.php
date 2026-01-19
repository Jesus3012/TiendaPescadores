<?php
include('includes/db.php');
include('includes/header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta</title>

    <!-- AdminLTE + Bootstrap + Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #ffe2c4; /* Fondo naranja */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            transform: translateY(0px);
        }

         .login-logo img {
            width: 90px;
            margin-bottom: 10px;
            animation: pop 0.7s ease-out;
        }
          .login-logo b {
            color: #ff7b00;
        }

        .register-box {
            width: 150%;
            max-width: 450px;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.10);
        }

        .btn-orange {
            background: #ff7b00;
            color: white;
            border: none;
        }
        .btn-orange:hover {
            background: #e66f00;
        }

        .login-link a {
            color: #ff7b00;
            font-weight: 600;
        }

        /* RESPONSIVE */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            .logo-img {
                width: 110px;
                height: 110px;
            }
            .register-box {
                width: 100%;
                max-width: 380px;
            }
        }
    </style>
</head>

<body>

<div class="register-box">

    <div class="login-logo text-center">
        <!-- LOGO PNG -->
        <img src="includes/logo_login.png" alt="Logo">
        <b>Crear</b> cuenta
    </div>

    <div class="card">
        <div class="card-body">

            <!-- FORMULARIO -->
            <form method="POST">
                <div class="input-group mb-3">
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><i class="fas fa-user"></i></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><i class="fas fa-envelope"></i></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><i class="fas fa-lock"></i></div>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <select name="rol" class="form-control" required>
                        <option value="vendedor">Vendedor</option>
                        <option value="administrador">Administrador</option>
                    </select>
                    <div class="input-group-append">
                        <div class="input-group-text"><i class="fas fa-user-tag"></i></div>
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-orange btn-block">
                    Crear Cuenta
                </button>
            </form>

            <p class="mt-3 text-center login-link">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
            </p>

            <!-- MENSAJES SWEETALERT -->
            <?php
                if (isset($_POST['register'])) {
                    $nombre = $_POST['nombre'];
                    $email = $_POST['email'];
                    $rol = $_POST['rol'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nombre, $email, $password, $rol);

                    if ($stmt->execute()) {
                        echo "
                        <script>
                        Swal.fire({
                            title: '¡Registro exitoso!',
                            text: 'El usuario fue creado correctamente.',
                            icon: 'success',
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#ff7b00'
                        });
                        </script>";
                    } else {
                        echo "
                        <script>
                        Swal.fire({
                            title: 'Error',
                            text: 'El correo ya está registrado.',
                            icon: 'error',
                            confirmButtonText: 'Intentar de nuevo',
                            confirmButtonColor: '#ff7b00'
                        });
                        </script>";
                    }
                    $stmt->close();
                }
            ?>

        </div>
    </div>
</div>

</body>
</html>
