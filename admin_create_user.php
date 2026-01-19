<?php
include('includes/db.php');
include('includes/session.php');
require_once('includes/csrf.php');

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

if (isset($_POST['create_user'])) {
    csrf_check();

    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $rol = $_POST['rol'] === 'administrador' ? 'administrador' : 'vendedor';
    $password_plain = $_POST['password'];

    // policy mínima de contraseña
    if (strlen($password_plain) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!preg_match('/[A-Z]/', $password_plain) || !preg_match('/[a-z]/', $password_plain) || !preg_match('/[0-9]/', $password_plain)) {
        $error = "La contraseña debe incluir mayúsculas, minúsculas y números.";
    } else {
        $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
        $created_by = $_SESSION['usuario_id'];

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol, activo, created_by) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->bind_param("ssssi", $nombre, $email, $password_hashed, $rol, $created_by);

        if ($stmt->execute()) {
            $success = "Usuario creado y activado correctamente.";
        } else {
            $error = "Error al crear usuario. ¿El correo ya existe?";
        }
        $stmt->close();
    }
}
?>

<?php include('includes/header.php'); ?>
<div class="container">
  <h2>Crear Usuario (Admin)</h2>
  <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
  <?php if (!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>
  <form method="POST" action="">
    <input type="text" name="nombre" placeholder="Nombre completo" required>
    <input type="email" name="email" placeholder="Correo electrónico" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <select name="rol" required>
      <option value="vendedor">Vendedor</option>
      <option value="administrador">Administrador</option>
    </select>
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <button type="submit" name="create_user">Crear usuario</button>
  </form>
  <p><a href="dashboard_admin.php">Volver al panel</a></p>
</div>
</body>
</html>
