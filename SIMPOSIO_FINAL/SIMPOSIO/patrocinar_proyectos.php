<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';

// Solo empresas pueden acceder
if (!esta_logeado() || !es_empresa()) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener el ID de la empresa
$stmt = $conexion->prepare("SELECT id_empresa FROM empresa WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$id_empresa = $empresa['id_empresa'];

// Procesar solicitud de patrocinio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'solicitar_patrocinio') {
    $id_articulo = intval($_POST['id_articulo']);
    $comentarios = trim($_POST['comentarios'] ?? '');

    if (ya_solicito_patrocinio($conexion, $id_articulo, $id_empresa)) {
        $mensaje = "Ya has solicitado patrocinio para este proyecto anteriormente.";
        $tipo_mensaje = "warning";
    } else {
        $stmt = $conexion->prepare("INSERT INTO patrocinios (id_articulo, id_empresa, comentarios_empresa) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $id_articulo, $id_empresa, $comentarios);
        if ($stmt->execute()) {
            $mensaje = "Solicitud de patrocinio enviada correctamente al autor del proyecto.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al enviar la solicitud: " . $conexion->error;
            $tipo_mensaje = "danger";
        }
    }
}

// Obtener estadísticas de la empresa
$stats_solicitudes = $conexion->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'aceptado' THEN 1 ELSE 0 END) as aceptados,
        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
    FROM patrocinios WHERE id_empresa = ?
");
$stats_solicitudes->bind_param("i", $id_empresa);
$stats_solicitudes->execute();
$stats = $stats_solicitudes->get_result()->fetch_assoc();

// Obtener proyectos aprobados para mostrar
$sql_proyectos = "SELECT a.id_articulo, a.titulo, a.categoria, a.tipo_trabajo, a.resumen,
                         u.nombre as autor_nombre, u.apellidos as autor_apellidos, u.correo as autor_correo,
                         (SELECT COUNT(*) FROM patrocinios p WHERE p.id_articulo = a.id_articulo AND p.id_empresa = ?) as ya_solicitado,
                         (SELECT nombre_archivo FROM proyecto_imagen WHERE id_articulo = a.id_articulo AND es_principal = 1 LIMIT 1) as imagen
                  FROM articulo a
                  JOIN usuario u ON a.id_usuario = u.id_usuario
                  WHERE a.estado = 'aprobado' AND a.id_articulo NOT IN (
                      SELECT id_articulo FROM patrocinios WHERE estado IN ('pendiente', 'aceptado') AND id_empresa = ?
                  )
                  ORDER BY a.fecha_registro DESC";
$stmt = $conexion->prepare($sql_proyectos);
$stmt->bind_param("ii", $id_empresa, $id_empresa);
$stmt->execute();
$proyectos_disponibles = $stmt->get_result();

// Obtener historial de solicitudes de esta empresa
$solicitudes = obtener_solicitudes_empresa($conexion, $id_empresa);

// Obtener conteo de revisiones pendientes
$revisiones_pendientes = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patrocinar Proyectos - SIMPOSIO FESC C4</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Estilos del proyecto (con los nuevos estilos de patrocinios) -->
    <link rel="stylesheet" href="Css/interfaz_usuario.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="convocatoria.php">
                            <i class="fas fa-scroll me-1"></i>Convocatoria
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ponencias.php">
                            <i class="fas fa-chalkboard me-1"></i>Ponencias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="programa/index_programa.php">
                            <i class="fas fa-calendar me-1"></i>Programa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="patrocinar_proyectos.php">
                            <i class="fas fa-hand-holding-usd me-1"></i>Patrocinar
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="perfil.php">
                                    <i class="fas fa-id-card me-2"></i>Mi Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="patrocinar_proyectos.php">
                                    <i class="fas fa-hand-holding-usd me-2"></i>Patrocinar
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Espacio para navbar fija -->
    <div style="height: 76px;"></div>

    <div class="container mb-5">
        <!-- Hero Section -->
        <div class="patrocinios-hero animate__animated animate__fadeInUp">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-hand-holding-usd me-3"></i>Patrocinar Proyectos
                    </h1>
                    <p class="lead mb-0">
                        Conecta con proyectos innovadores y apoya el desarrollo académico y tecnológico.
                        Tu patrocinio puede marcar la diferencia.
                    </p>
                </div>
                <div class="col-md-4 text-center">
                    <i class="fas fa-gem fa-4x" style="opacity: 0.8;"></i>
                </div>
            </div>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
            <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(41,62,107,0.1);">
                        <i class="fas fa-chart-line" style="color: #293e6b;"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Solicitudes</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255,193,7,0.1);">
                        <i class="fas fa-clock" style="color: #ffc107;"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pendientes'] ?? 0; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(40,167,69,0.1);">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['aceptados'] ?? 0; ?></div>
                    <div class="stat-label">Aceptados</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220,53,69,0.1);">
                        <i class="fas fa-times-circle" style="color: #dc3545;"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['rechazados'] ?? 0; ?></div>
                    <div class="stat-label">Rechazados</div>
                </div>
            </div>
        </div>

        <!-- Proyectos disponibles para patrocinar -->
        <div class="row mt-4">
            <div class="col-12 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-search me-2"></i>
                    Proyectos Disponibles
                </h3>
                <p class="section-subtitle">Explora los proyectos aprobados y elige cuál deseas patrocinar</p>
                <hr class="divider">
            </div>

            <?php if ($proyectos_disponibles->num_rows == 0): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4>No hay proyectos disponibles</h4>
                    <p class="text-muted">En este momento no hay proyectos aprobados para patrocinar. ¡Vuelve más tarde!</p>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="fas fa-home me-2"></i>Volver al Inicio
                    </a>
                </div>
            </div>
            <?php else: ?>
                <?php while ($proyecto = $proyectos_disponibles->fetch_assoc()): 
                    $tipo_clase = '';
                    $tipo_icono = '';
                    switch($proyecto['tipo_trabajo']) {
                        case 'cartel': 
                            $tipo_clase = 'badge-cartel'; 
                            $tipo_icono = 'fa-image';
                            break;
                        case 'ponencia': 
                            $tipo_clase = 'badge-ponencia'; 
                            $tipo_icono = 'fa-chalkboard-teacher';
                            break;
                        case 'taller': 
                            $tipo_clase = 'badge-taller'; 
                            $tipo_icono = 'fa-tools';
                            break;
                        case 'prototipo': 
                            $tipo_clase = 'badge-prototipo'; 
                            $tipo_icono = 'fa-cube';
                            break;
                        default:
                            $tipo_clase = 'badge-ponencia';
                            $tipo_icono = 'fa-chalkboard-teacher';
                    }
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="proyecto-card card h-100">
                        <div class="position-relative">
                            <?php if (!empty($proyecto['imagen'])): ?>
                                <img src="uploads/proyectos/<?php echo $proyecto['imagen']; ?>" class="proyecto-img" alt="Imagen del proyecto">
                            <?php else: ?>
                                <div class="proyecto-img-placeholder">
                                    <i class="fas fa-image fa-3x text-white"></i>
                                </div>
                            <?php endif; ?>
                            <span class="proyecto-badge <?php echo $tipo_clase; ?>">
                                <i class="fas <?php echo $tipo_icono; ?> me-1"></i>
                                <?php echo ucfirst($proyecto['tipo_trabajo']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($proyecto['titulo']); ?></h5>
                            <p class="card-text text-muted small">
                                <i class="fas fa-user me-1"></i> Autor: <?php echo htmlspecialchars($proyecto['autor_nombre'] . ' ' . $proyecto['autor_apellidos']); ?>
                            </p>
                            <p class="card-text">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($proyecto['categoria']); ?>
                                </span>
                            </p>
                            <?php if (!empty($proyecto['resumen'])): ?>
                                <p class="card-text small text-muted">
                                    <?php echo htmlspecialchars(substr($proyecto['resumen'], 0, 100)) . (strlen($proyecto['resumen']) > 100 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-0 pb-3">
                            <button type="button" class="btn btn-patrocinar w-100" data-bs-toggle="modal" data-bs-target="#modalPatrocinio" 
                                    data-id="<?php echo $proyecto['id_articulo']; ?>" 
                                    data-titulo="<?php echo htmlspecialchars($proyecto['titulo']); ?>"
                                    data-autor="<?php echo htmlspecialchars($proyecto['autor_nombre'] . ' ' . $proyecto['autor_apellidos']); ?>">
                                <i class="fas fa-gem me-2"></i>Solicitar Patrocinio
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Historial de solicitudes -->
        <div class="row mt-5">
            <div class="col-12 mb-4">
                <h3 class="section-title">
                    <i class="fas fa-history me-2"></i>
                    Mis Solicitudes de Patrocinio
                </h3>
                <p class="section-subtitle">Historial de todas tus solicitudes de patrocinio</p>
                <hr class="divider">
            </div>

            <div class="col-12">
                <?php if ($solicitudes->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No hay solicitudes</h4>
                    <p class="text-muted">Aún no has realizado ninguna solicitud de patrocinio.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive solicitudes-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><i class="fas fa-project-diagram me-2"></i>Proyecto</th>
                                <th><i class="fas fa-calendar me-2"></i>Fecha Solicitud</th>
                                <th><i class="fas fa-chart-simple me-2"></i>Estado</th>
                                <th><i class="fas fa-comment me-2"></i>Comentario del Autor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sol = $solicitudes->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($sol['proyecto_titulo']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])); ?></td>
                                <td>
                                    <?php if ($sol['estado'] == 'pendiente'): ?>
                                        <span class="estado-pendiente">
                                            <i class="fas fa-clock me-1"></i> Pendiente
                                        </span>
                                    <?php elseif ($sol['estado'] == 'aceptado'): ?>
                                        <span class="estado-aceptado">
                                            <i class="fas fa-check-circle me-1"></i> Aceptado
                                        </span>
                                    <?php else: ?>
                                        <span class="estado-rechazado">
                                            <i class="fas fa-times-circle me-1"></i> Rechazado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($sol['comentarios_autor'])): ?>
                                        <span class="text-muted">
                                            <i class="fas fa-quote-left me-1 text-muted"></i>
                                            <?php echo nl2br(htmlspecialchars(substr($sol['comentarios_autor'], 0, 80))); ?>
                                            <?php echo strlen($sol['comentarios_autor']) > 80 ? '...' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Sin comentarios</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botón de volver -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Inicio
                </a>
            </div>
        </div>
    </div>

    <!-- Modal para solicitar patrocinio -->
    <div class="modal fade modal-patrocinio" id="modalPatrocinio" tabindex="-1" aria-labelledby="modalPatrocinioLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalPatrocinioLabel">
                            <i class="fas fa-gem me-2"></i>Solicitar Patrocinio
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="solicitar_patrocinio">
                        <input type="hidden" name="id_articulo" id="id_articulo_modal">
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-hand-holding-heart fa-3x" style="color: #D59F0F;"></i>
                        </div>
                        
                        <p class="text-center mb-3">
                            ¿Deseas patrocinar el proyecto?
                        </p>
                        <div class="proyecto-info text-center">
                            <strong id="titulo_proyecto_modal" class="text-primary"></strong>
                            <br>
                            <small class="text-muted" id="autor_proyecto_modal"></small>
                        </div>
                        
                        <div class="mb-3 mt-3">
                            <label for="comentarios" class="form-label fw-semibold">
                                <i class="fas fa-comment me-2"></i>Mensaje para el autor
                            </label>
                            <textarea class="form-control" name="comentarios" id="comentarios" rows="4" 
                                      placeholder="Escribe un mensaje para el autor del proyecto... (opcional)"></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Un mensaje personalizado aumenta las posibilidades de que tu solicitud sea aceptada.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-patrocinar">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <!-- Footer -->
    <footer class="colorazul text-white mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4</h5>
                    <p class="text-white-50">Congreso Internacional sobre la Enseñanza y Aplicación de las Matemáticas</p>
                    <p class="text-white-50"><i class="fas fa-map-marker-alt me-2"></i>FES Cuautitlán, UNAM</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><i class="fas fa-address-card me-2"></i><a class="text-white" href="contactanos.php" style="text-decoration: none;">Contactanos</a></h5>
                    <p class="text-white-50"><i class="fas fa-envelope me-2"></i>info@simposiofesc.com</p>
                    <p class="text-white-50"><i class="fas fa-phone me-2"></i>(55) 1234-5678</p>
                    <p class="text-white-50"><i class="fas fa-clock me-2"></i>Lun-Vie: 9:00 - 18:00</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3"><i class="fas fa-share-alt me-2"></i>Síguenos</h5>
                    <div class="d-flex gap-3">
                        <a href="https://www.facebook.com/fescunamoficial/about?locale=es_LA" class="text-white fs-3"><i class="fab fa-facebook"></i></a>
                        <a href="https://x.com/FESC_UNAM?fbclid=IwY2xjawQyQHxleHRuA2FlbQIxMABicmlkETFvUEhaR0VMQmo5UEQ1b0M0c3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHunbJB2FGEliNtdbtCRQ5rraIYqxrw-P_F1GfK3vbH2iH1LCVWqhSXpl2LP7_aem_vLlrun1rax8EMbKR0qgxBQ" class="text-white fs-3"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/fescunamoficial?fbclid=IwY2xjawQyQnJleHRuA2FlbQIxMABicmlkETFjOU9lY2lsNWhBREVmV1Nxc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHvwGr8ZN8ksdMDFGCUCpjhMbJJW9cbvuMXJ5qhpo6m2tuK4zV1DqLw3vk0vB_aem_XcaPSOTLV8iGNi3yf750EQ" class="text-white fs-3"><i class="fab fa-instagram"></i></a>
                        <a href="https://youtube.com/@fescunamoficial9877?si=J4aNbVU3BTRfEzd7" class="text-white fs-3"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="border-white-50">
            <div class="text-center">
                <p class="mb-0 text-white-50"><i class="far fa-copyright me-2"></i><?php echo date('Y'); ?> Congreso Internacional de Matemáticas. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pasar datos al modal
        var modalPatrocinio = document.getElementById('modalPatrocinio');
        if (modalPatrocinio) {
            modalPatrocinio.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var idArticulo = button.getAttribute('data-id');
                var titulo = button.getAttribute('data-titulo');
                var autor = button.getAttribute('data-autor');
                
                document.getElementById('id_articulo_modal').value = idArticulo;
                document.getElementById('titulo_proyecto_modal').textContent = titulo;
                document.getElementById('autor_proyecto_modal').textContent = 'por ' + autor;
            });

            // Limpiar el textarea cuando se cierra el modal
            modalPatrocinio.addEventListener('hidden.bs.modal', function() {
                document.getElementById('comentarios').value = '';
            });
        }
    </script>
</body>
</html>