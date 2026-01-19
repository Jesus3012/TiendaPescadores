<?php
include 'includes/session.php';
include 'includes/db.php';

header('Content-Type: application/json');

$id = $_SESSION['usuario_id'] ?? null;
$actual = $_POST['actual'] ?? '';
$nueva = $_POST['nueva'] ?? '';
$confirmar = $_POST['confirmar'] ?? '';

if (!$id) {
    echo json_encode(['status' => 'error', 'msg' => 'Sesión inválida.']);
    exit;
}

if ($nueva !== $confirmar) {
    echo json_encode(['status' => 'error', 'msg' => 'Las contraseñas no coinciden.']);
    exit;
}

// Validación de fuerza
if (
    strlen($nueva) < 8
) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'La contraseña debe tener mínimo 8 caracteres.'
    ]);
    exit;
}

// Obtener contraseña actual
$stmt = $conn->prepare("SELECT password FROM usuarios WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res || !password_verify($actual, $res['password'])) {
    echo json_encode(['status' => 'error', 'msg' => 'La contraseña actual es incorrecta.']);
    exit;
}

// Bloquear reutilización
if (password_verify($nueva, $res['password'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'La nueva contraseña no puede ser igual a la actual.'
    ]);
    exit;
}

// Guardar nueva contraseña
$hash = password_hash($nueva, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE usuarios SET password=? WHERE id=?");
$upd->bind_param("si", $hash, $id);

if (!$upd->execute()) {
    echo json_encode(['status' => 'error', 'msg' => 'Error al actualizar la contraseña.']);
    exit;
}

// Cerrar sesión por seguridad
session_destroy();

echo json_encode(['status' => 'success']);
