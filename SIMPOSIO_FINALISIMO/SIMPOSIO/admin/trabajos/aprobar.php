<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}
$mensaje = "";
$tipo_mensaje = "";
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
    $mensaje = "Articulo Aprobado exitosamente";
    $tipo_mensaje = "Success";
    if ($mensaje):
                echo "<div class=alert alert-",$tipo_mensaje,">
                    ",$mensaje,"</div>";
    endif;
} catch (Exception $e) {
    $conexion->rollback();
}

header('refresh:2;url=pendientes.php?mensaje=aprobado');
exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .alert {
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
}
    </style>
</head>