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
                                <a href="ver.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Asignar Docente
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