<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}
$mensaje = "";
$tipo_mensaje= "";
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: pendientes.php');
    exit;
}

// Iniciar transacción
$conexion->begin_transaction();
try {
    // Actualizar estado del artículo
    $stmt = $conexion->prepare("UPDATE articulo SET estado = 'rechazado', aprobado_por = ?, fecha_aprobacion = NOW() WHERE id_articulo = ?");
    $stmt->bind_param("ii", $_SESSION['id_admin'], $id);
    $stmt->execute();

    // Ocultar la actividad asociada
    $stmt2 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    $conexion->commit();
    $mensaje = "Articulo rechazado exitosamente";
    $tipo_mensaje = "Success";
    if ($mensaje):
                echo "<div class=alert alert-",$tipo_mensaje,">
                    ",$mensaje,"</div>";
    endif;
    
    header('refresh:2;url=pendientes.php?mensaje=rechazado');
} catch (Exception $e) {
    $conexion->rollback();
}

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
    <link rel="stylesheet" href="../../Css/interfaz_usuario.css">
    <title>Mis Proyectos - SIMPOSIO</title>
    <style>
        .alert {
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
}
    </style>
</head>
<body>
    <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
</body>