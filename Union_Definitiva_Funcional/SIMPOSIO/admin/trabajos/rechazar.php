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
$stmt = $conexion->prepare("UPDATE articulo SET estado = 'rechazado', aprobado_por = ?, fecha_aprobacion = NOW() WHERE id_articulo = ?");
$stmt->bind_param("ii", $_SESSION['id_admin'], $id);
$stmt->execute();

// Opcional: eliminar la actividad asociada (si quieres limpiar)
$stmt2 = $conexion->prepare("DELETE FROM actividad_evento WHERE id_articulo = ?");
$stmt2->bind_param("i", $id);
$stmt2->execute();

header('Location: pendientes.php?mensaje=rechazado');
exit;