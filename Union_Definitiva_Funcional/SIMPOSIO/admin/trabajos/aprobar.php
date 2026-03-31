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

$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare("UPDATE articulo SET estado = 'aprobado', aprobado_por = ?, fecha_aprobacion = NOW() WHERE id_articulo = ?");
    $stmt->bind_param("ii", $_SESSION['id_admin'], $id);
    $stmt->execute();

    // Mostrar la actividad (si existe)
    $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 1 WHERE id_articulo = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    $conexion->commit();
} catch (Exception $e) {
    $conexion->rollback();
}

header('Location: pendientes.php?mensaje=aprobado');
exit;