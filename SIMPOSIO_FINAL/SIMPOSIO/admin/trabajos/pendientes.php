<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}
// Procesar asignación de docente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asignar'])) {
    $id_articulo = intval($_POST['id_articulo']);
    $id_docente = intval($_POST['id_docente']);
    if ($id_articulo && $id_docente) {
        // Verificar que no exista ya una asignación pendiente
        $stmt = $conexion->prepare("SELECT id_asignacion FROM asignacion_revision WHERE id_articulo = ? AND estado_revision = 'pendiente'");
        $stmt->bind_param("i", $id_articulo);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            $stmt2 = $conexion->prepare("INSERT INTO asignacion_revision (id_articulo, id_docente, id_admin_asignador) VALUES (?, ?, ?)");
            $stmt2->bind_param("iii", $id_articulo, $id_docente, $_SESSION['id_admin']);
            $stmt2->execute();
            $mensaje_asignacion = "Trabajo asignado correctamente al docente.";
        } else {
            $mensaje_asignacion = "Ya hay una asignación pendiente para este trabajo.";
        }
    }
}
// Obtener trabajos pendientes con datos del evento y autor
$sql = "
    SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.fecha_registro, ar.estado_revision,
           u.nombre as autor_nombre, u.apellidos as autor_apellidos,
           e.titulo as evento_titulo, e.fecha as evento_fecha,
           d.nombre as docente_asignado_nombre, d.apellidos as docente_asignado_apellidos,
           doc.id_docente
    FROM articulo a
    LEFT JOIN asignacion_revision ar ON a.id_articulo = ar.id_articulo
    LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
    LEFT JOIN evento e ON a.id_evento = e.id_evento
    LEFT JOIN docente doc ON ar.id_docente = doc.id_docente
    LEFT JOIN usuario d ON doc.id_usuario = d.id_usuario
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
    <style>
        /* admin.css */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .admin-navbar {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-navbar .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .admin-navbar .nav-links a {
            color: white;
            margin: 0 1rem;
            text-decoration: none;
            transition: 0.3s;
        }

        .admin-navbar .nav-links a:hover {
            color: #D59F0F;
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h2 {
            font-size: 2.5rem;
            margin: 0;
            color: #293e6b;
        }

        .card p {
            margin: 0.5rem 0 1rem;
            color: #6c757d;
        }

        .btn-primary {
            background: #293e6b;
            border: none;
            border-radius: 30px;
            padding: 0.5rem 1rem;
            transition: 0.3s;
        }
        .btn-primary:hover {
            background: #D59F0F;
            transform: translateY(-2px);
        }

        .btn-success, .btn-danger, .btn-info {
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2><i class="fas fa-clock me-2"></i>Trabajos pendientes de aprobación</h2>
        <?php if (isset($mensaje_asignacion)): ?>
            <div class="aler alert-success alert-dismissible fade show"><?php echo $mensaje_asignacion; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <p>Revisa los trabajos enviados por los usuarios y decide su estado.</p>
        <?php if ($result->num_rows == 0): ?>
            <div class="alert alert-success">No hay trabajos pendientes en este momento.</div>
        <?php else: ?>
            <div class="row">
                <?php while ($row = $result->fetch_assoc()): 
                    $categoria = $row["categoria"];
                    $stmt_docentes = $conexion->prepare("
                    SELECT d.id_docente, u.nombre, u.apellidos, d.especialidad
                    FROM docente d
                    JOIN usuario u ON d.id_usuario = u.id_usuario
                    WHERE d.especialidad LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', d.especialidad, '%')
                    ORDER BY u.nombre
                ");
                $stmt_docentes->bind_param("ss", $categoria, $categoria);
                $stmt_docentes->execute();
                $docentes = $stmt_docentes->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
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
                                <?php if ($row['estado_revision']): ?>
                                    <small><strong>Asignado a:</strong> <?php echo htmlspecialchars($row['docente_asignado_nombre'] . ' ' . ($row['docente_asignado_apellidos'] ?? '')); ?></small>
                                <?php endif; ?>
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="aprobar.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-success btn-sm" onclick="return confirm('¿Aprobar este trabajo? Se mostrará en la agenda.')">
                                    <i class="fas fa-check-circle"></i> Aprobar
                                </a>
                                <a href="rechazar.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Rechazar este trabajo? No aparecerá en la agenda.')">
                                    <i class="fas fa-times-circle"></i> Rechazar
                                </a>
                                <?php if (count($docentes) > 0 && !$row['estado_revision']): ?>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAsignar-<?php echo $row['id_articulo']; ?>">
                                        <i class="fas fa-user-tie"></i> Asignar revisor
                                    </button>
                                <?php elseif ($row['estado_revision']): ?>
                                    <span class="badge bg-secondary">Asignado</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">No hay docentes con especialidad coincidente</span>
                                <?php endif; ?>
                                <a href="ver.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> Ver detalles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal para asignar docente -->
            <?php if (count($docentes) > 0 && !$row['estado_revision']): ?>
            <div class="modal fade" id="modalAsignar-<?php echo $row['id_articulo']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Asignar revisor a: <?php echo htmlspecialchars($row['titulo']); ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="id_articulo" value="<?php echo $row['id_articulo']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Seleccionar docente (especialidad relacionada con "<?php echo htmlspecialchars($row['categoria']); ?>")</label>
                                    <select name="id_docente" class="form-select" required>
                                        <option value="">-- Elige un docente --</option>
                                        <?php foreach ($docentes as $doc): ?>
                                        <option value="<?php echo $doc['id_docente']; ?>">
                                            <?php echo htmlspecialchars($doc['nombre'] . ' ' . ($doc['apellidos'] ?? '') . ' - ' . $doc['especialidad']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> El docente podrá revisar y aprobar/rechazar este trabajo desde su panel.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" name="asignar" class="btn btn-primary">Asignar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="../index.php" class="btn btn-secondary">← Volver al dashboard</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>