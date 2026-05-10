<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';

header('Content-Type: application/json');

if (!esta_logeado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$id_notificacion = intval($_POST['id'] ?? 0);

if ($id_notificacion <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit;
}

$resultado = eliminar_notificacion($conexion, $id_notificacion, $_SESSION['id_usuario']);

if ($resultado) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar']);
}
?>