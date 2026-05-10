<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';

if (!esta_logeado()) {
    http_response_code(401);
    exit;
}

$id_notificacion = intval($_POST['id'] ?? 0);
if ($id_notificacion) {
    marcar_notificacion_leida($conexion, $id_notificacion, $_SESSION['id_usuario']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>