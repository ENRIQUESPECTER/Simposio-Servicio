<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';

if (!es_admin()) {
    header('Location: login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: mis_proyectos.php');
    exit;
}

// Verificar que el usuario es autor del trabajo (o admin)
$stmt = $conexion->prepare("SELECT id_usuario, estado FROM articulo WHERE id_articulo = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$articulo = $stmt->get_result()->fetch_assoc();
if (!$articulo || ($articulo['id_usuario'] != $_SESSION['usuario'] && !es_admin())) {
    die("No tienes permiso para ver este historial.");
}

// Obtener historial
$stmt = $conexion->prepare("SELECT fecha_rechazo, detalles_json FROM historial_revisiones WHERE id_articulo = ? ORDER BY fecha_rechazo DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$historial = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de correcciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Css/admin.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Historial de correcciones solicitadas</h2>
        <?php if ($historial->num_rows == 0): ?>
            <div class="alert alert-info">No hay correcciones previas registradas.</div>
        <?php else: ?>
            <?php while ($row = $historial->fetch_assoc()): ?>
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        Fecha del rechazo: <?php echo date('d/m/Y H:i', strtotime($row['fecha_rechazo'])); ?>
                    </div>
                    <div class="card-body">
                        <ul>
                            <?php $detalles = json_decode($row['detalles_json'], true); ?>
                            <?php if (is_array($detalles)): ?>
                                <?php foreach ($detalles as $det): ?>
                                    <li><strong><?php echo htmlspecialchars($det['criterio']); ?>:</strong> <?php echo nl2br(htmlspecialchars($det['detalle'])); ?></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>No se registraron detalles específicos.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        <a href="admin/trabajos/evaluar.php?id=<?php echo $id; ?>" class="btn btn-primary">Volver al proyecto</a>
    </div>
</body>
</html>