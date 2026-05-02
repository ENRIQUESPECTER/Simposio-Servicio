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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../../Css/admin.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="../eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                <li class="nav-item"><a class="nav-link" href="../actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                <li class="nav-item"><a class="nav-link" href="../trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                <li class="nav-item"><a class="nav-link" href="../trabajos/evaluacion.php"><i class="fas fa-calendar me-1"></i>Evaluación Extensos</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario'];  ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </li>
            </ul>
        </div>
    </div>
</nav>
    <div class="container mt-5">
        <h2><i class="fas fa-robot me-2"></i>Evaluación de extensos</h2>
        <p>Si asi lo desea, se puede utilizar inteligencia artificial para analizar los PDF subidos y determinar si cumplen los requisitos.</p>

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