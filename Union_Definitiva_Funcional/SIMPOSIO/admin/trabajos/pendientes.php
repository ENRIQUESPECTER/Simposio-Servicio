<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

// Obtener trabajos pendientes con datos del evento y autor
$sql = "
    SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.fecha_registro,
           u.nombre as autor_nombre, u.apellidos as autor_apellidos,
           e.titulo as evento_titulo, e.fecha as evento_fecha
    FROM articulo a
    LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
    LEFT JOIN evento e ON a.id_evento = e.id_evento
    WHERE a.estado = 'pendiente'
    ORDER BY a.fecha_registro DESC
";
$result = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Trabajos pendientes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Css/admin.css">
    <style>
        /* Estilos adicionales para esta página */
        .card-pendiente {
            transition: transform 0.2s;
            margin-bottom: 1rem;
        }
        .card-pendiente:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-clock me-2"></i>Trabajos pendientes de aprobación</h2>
        <p>Revisa los trabajos enviados por los usuarios y decide su estado.</p>

        <?php if ($result->num_rows == 0): ?>
            <div class="alert alert-success">No hay trabajos pendientes en este momento.</div>
        <?php else: ?>
            <div class="row">
                <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-pendiente shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['titulo']); ?></h5>
                            <p class="card-text">
                                <small><strong>Tipo:</strong> <?php echo ucfirst($row['tipo_trabajo']); ?></small><br>
                                <small><strong>Categoría:</strong> <?php echo htmlspecialchars($row['categoria']); ?></small><br>
                                <small><strong>Evento:</strong> <?php echo htmlspecialchars($row['evento_titulo']); ?> (<?php echo date('d/m/Y', strtotime($row['evento_fecha'])); ?>)</small><br>
                                <small><strong>Autor:</strong> <?php echo htmlspecialchars($row['autor_nombre'] . ' ' . ($row['autor_apellidos'] ?? '')); ?></small><br>
                                <small><strong>Fecha de registro:</strong> <?php echo date('d/m/Y H:i', strtotime($row['fecha_registro'])); ?></small>
                            </p>
                            <div class="d-flex justify-content-between">
                                <a href="aprobar.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-success btn-sm" onclick="return confirm('¿Aprobar este trabajo? Se mostrará en la agenda.')">
                                    <i class="fas fa-check-circle"></i> Aprobar
                                </a>
                                <a href="rechazar.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Rechazar este trabajo? No aparecerá en la agenda.')">
                                    <i class="fas fa-times-circle"></i> Rechazar
                                </a>
                                <a href="ver.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> Ver detalles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="../dashboard.php" class="btn btn-secondary">← Volver al dashboard</a>
        </div>
    </div>
</body>
</html>