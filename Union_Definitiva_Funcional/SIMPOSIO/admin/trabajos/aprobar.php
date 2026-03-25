<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: pendientes.php');
    exit;
}

// Actualizar estado del artículo
$stmt = $conexion->prepare("UPDATE articulo SET estado = 'aprobado', aprobado_por = ?, fecha_aprobacion = NOW() WHERE id_articulo = ?");
$stmt->bind_param("ii", $_SESSION['id_admin'], $id);
$stmt->execute();

// Opcional: también podrías registrar en la agenda (ya estaba)
// Solo si quieres notificar al usuario, podrías insertar en una tabla de notificaciones

header('Location: pendientes.php?mensaje=aprobado');
exit;