<?php
session_start();
require_once '../../includes/conexion.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

if (!es_admin()) {
    header('Location: ../login_admin.php');
    exit;
}

// Obtener trabajos con PDF (pendientes o aprobados)
$trabajos = obtener_trabajos_con_pdf($conexion, null); // todos los que tienen PDF
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluar extensos con IA - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Css/admin.css">
</head>
<body>
    <?php include '../../includes/navbar_admin.php'; ?>
    <div class="container mt-5">
        <h2><i class="fas fa-robot me-2"></i>Evaluación de extensos con IA</h2>
        <p>Utiliza inteligencia artificial para analizar los PDF subidos y determinar si cumplen los requisitos.</p>

        <?php if ($trabajos->num_rows == 0): ?>
            <div class="alert alert-info">No hay trabajos con archivo PDF subido.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $trabajos->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id_articulo']; ?></td>
                            <td><?php echo htmlspecialchars($row['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($row['autor_nombre'] . ' ' . $row['autor_apellidos']); ?></td>
                            <td><?php echo ucfirst($row['tipo_trabajo']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $row['estado'] == 'aprobado' ? 'success' : ($row['estado'] == 'rechazado' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($row['estado']); ?>
                                </span>
                             </td>
                            <td>
                                <a href="evaluar.php?id=<?php echo $row['id_articulo']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-brain"></i> Evaluar con IA
                                </a>
                             </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>