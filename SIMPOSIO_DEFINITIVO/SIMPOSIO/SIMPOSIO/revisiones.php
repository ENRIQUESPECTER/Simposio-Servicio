<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';

// Solo docentes pueden acceder
if (!esta_logeado() || !es_docente()) {
    header('Location: ../login.php');
    exit;
}

// Obtener id_docente a partir del usuario
$stmt = $conexion->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$id_docente = $stmt->get_result()->fetch_assoc()['id_docente'] ?? null;

if (!$id_docente) {
    die("No se encontró información del docente.");
}

// Procesar aprobación/rechazo desde esta página
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && isset($_POST['id_articulo'])) {
    $id_articulo = intval($_POST['id_articulo']);
    $accion = $_POST['accion'];
    
    // Verificar que este trabajo esté asignado a este docente y pendiente
    $stmt = $conexion->prepare("
        SELECT ar.id_articulo 
        FROM asignacion_revision ar
        WHERE ar.id_articulo = ? AND ar.id_docente = ? AND ar.estado_revision = 'pendiente'
    ");
    $stmt->bind_param("ii", $id_articulo, $id_docente);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $error = "No tienes permiso para revisar este trabajo o ya fue revisado.";
    } else {
        // Iniciar transacción
        $conexion->begin_transaction();
        try {
            if ($accion == 'aprobar') {
                // Cambiar estado del artículo a aprobado
                $stmt2 = $conexion->prepare("UPDATE articulo SET estado = 'aprobado' WHERE id_articulo = ?");
                $stmt2->bind_param("i", $id_articulo);
                $stmt2->execute();
                // Asegurar que la actividad sea visible
                $stmt3 = $conexion->prepare("UPDATE actividad_evento SET visible = 1 WHERE id_articulo = ?");
                $stmt3->bind_param("i", $id_articulo);
                $stmt3->execute();
                $mensaje = "Trabajo aprobado correctamente.";
            } else { // rechazar
                $stmt2 = $conexion->prepare("UPDATE articulo SET estado = 'rechazado' WHERE id_articulo = ?");
                $stmt2->bind_param("i", $id_articulo);
                $stmt2->execute();
                $stmt3 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
                $stmt3->bind_param("i", $id_articulo);
                $stmt3->execute();
                $mensaje = "Trabajo rechazado.";
            }
            // Actualizar estado de la asignación
            $stmt4 = $conexion->prepare("UPDATE asignacion_revision SET estado_revision = ? WHERE id_articulo = ? AND id_docente = ?");
            $estado_nuevo = ($accion == 'aprobar') ? 'aprobado' : 'rechazado';
            $stmt4->bind_param("sii", $estado_nuevo, $id_articulo, $id_docente);
            $stmt4->execute();
            
            $conexion->commit();
        } catch (Exception $e) {
            $conexion->rollback();
            $error = "Error al procesar: " . $e->getMessage();
        }
    }
}

// Obtener trabajos asignados pendientes de revisión
$sql = "
    SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.resumen,
           e.titulo as evento_titulo, e.fecha as evento_fecha,
           ae.hora_inicio, ae.hora_fin, ae.descripcion, ae.referencias, s.nombre as salon,
           u.nombre as autor_nombre, u.apellidos as autor_apellidos
    FROM asignacion_revision ar
    JOIN articulo a ON ar.id_articulo = a.id_articulo
    JOIN evento e ON a.id_evento = e.id_evento
    LEFT JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
    LEFT JOIN salones s ON ae.id_salon = s.id_salon
    JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE ar.id_docente = ? AND ar.estado_revision = 'pendiente'
    ORDER BY ar.fecha_asignacion DESC
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_docente);
$stmt->execute();
$trabajos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis revisiones - Docente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../Css/user.css">
    <style>
        .container { margin-top: 100px; }
        .card-revision { border-left: 4px solid #D59F0F; transition: 0.3s; margin-bottom: 1.5rem; }
        .card-revision:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color: #293e6b;">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Cerrar sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div style="height: 76px;"></div>

    <div class="container">
        <h2><i class="fas fa-tasks me-2"></i>Revisiones asignadas</h2>
        <p>Como revisor, puedes aprobar o rechazar los trabajos que te han sido asignados.</p>
        <?php if (isset($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $mensaje; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if ($trabajos->num_rows == 0): ?>
            <div class="alert alert-info">No tienes trabajos pendientes de revisión.</div>
        <?php else: ?>
            <div class="row">
                <?php while ($t = $trabajos->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card card-revision shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($t['titulo']); ?></h5>
                            <p class="card-text">
                                <small><strong>Autor:</strong> <?php echo htmlspecialchars($t['autor_nombre'] . ' ' . ($t['autor_apellidos'] ?? '')); ?></small><br>
                                <small><strong>Evento:</strong> <?php echo htmlspecialchars($t['evento_titulo']); ?> (<?php echo date('d/m/Y', strtotime($t['evento_fecha'])); ?>)</small><br>
                                <small><strong>Tipo:</strong> <?php echo ucfirst($t['tipo_trabajo']); ?> | <strong>Categoría:</strong> <?php echo htmlspecialchars($t['categoria']); ?></small><br>
                                <?php if ($t['hora_inicio']): ?>
                                <small><strong>Horario:</strong> <?php echo substr($t['hora_inicio'],0,5); ?> - <?php echo substr($t['hora_fin'],0,5); ?> <?php echo $t['salon'] ? "($t[salon])" : ''; ?></small><br>
                                <?php endif; ?>
                            </p>
                            <div class="d-flex gap-2">
                                <form method="POST" onsubmit="return confirm('¿Aprobar este trabajo? Se mostrará en la agenda.')">
                                    <input type="hidden" name="id_articulo" value="<?php echo $t['id_articulo']; ?>">
                                    <input type="hidden" name="accion" value="aprobar">
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-circle"></i> Aprobar</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('¿Rechazar este trabajo? No aparecerá en la agenda.')">
                                    <input type="hidden" name="id_articulo" value="<?php echo $t['id_articulo']; ?>">
                                    <input type="hidden" name="accion" value="rechazar">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times-circle"></i> Rechazar</button>
                                </form>
                                <a href="ver_proyecto.php?id=<?php echo $t['id_articulo']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> Ver detalles</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        <div class="mt-4">
            <a href="mis_proyectos.php" class="btn btn-primary">← Volver a mis proyectos</a>
        </div>
    </div>

    <footer class="colorazul text-white mt-5 py-4 text-center">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> SIMPOSIO FESC C4</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>