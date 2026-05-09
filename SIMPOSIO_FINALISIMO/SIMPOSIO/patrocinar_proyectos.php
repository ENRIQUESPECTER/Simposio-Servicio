<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';
require_once 'includes/notificaciones.php';

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
$mostrar_solicitudes = isset($_GET['ver']) && $_GET['ver'] == 'solicitudes';

// Procesar solicitud de patrocinio
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Solicitar nuevo patrocinio
    if (isset($_POST['accion']) && $_POST['accion'] == 'solicitar_patrocinio') {
        $id_articulo = intval($_POST['id_articulo']);
        $comentarios = trim($_POST['comentarios'] ?? '');

        if (ya_solicito_patrocinio($conexion, $id_articulo, $id_empresa)) {
            $mensaje = "Ya tienes una solicitud pendiente o aceptada para este proyecto.";
            $tipo_mensaje = "warning";
        } elseif (intentos_patrocinio_agotados($conexion, $id_articulo, $id_empresa)) {
            $mensaje = "Has agotado el límite de solicitudes para este proyecto. Solo se permite 1 solicitud inicial y 1 reenvío.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $conexion->prepare("INSERT INTO patrocinios (id_articulo, id_empresa, comentarios_empresa) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $id_articulo, $id_empresa, $comentarios);
            if ($stmt->execute()) {
                notificar_nuevo_patrocinio($conexion, $id_articulo, $id_empresa);
                $mensaje = "Solicitud de patrocinio enviada correctamente al autor del proyecto.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al enviar la solicitud: " . $conexion->error;
                $tipo_mensaje = "danger";
            }
        }
    }
    
    // Reenviar solicitud rechazada
    if (isset($_POST['accion']) && $_POST['accion'] == 'reenviar_solicitud') {
        $id_patrocinio = intval($_POST['id_patrocinio']);
        $comentarios = trim($_POST['comentarios'] ?? '');
        
        // Obtener el id_articulo del patrocinio
        $stmt = $conexion->prepare("SELECT id_articulo FROM patrocinios WHERE id_patrocinio = ? AND id_empresa = ?");
        $stmt->bind_param("ii", $id_patrocinio, $id_empresa);
        $stmt->execute();
        $patrocinio = $stmt->get_result()->fetch_assoc();
        
        if ($patrocinio) {
            $id_articulo = $patrocinio['id_articulo'];
            // Crear nueva solicitud (no reutilizar la rechazada)
            $stmt = $conexion->prepare("INSERT INTO patrocinios (id_articulo, id_empresa, comentarios_empresa) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $patrocinio['id_articulo'], $id_empresa, $comentarios);
            if ($stmt->execute()) {
                notificar_nuevo_patrocinio($conexion, $id_articulo, $id_empresa);
                $mensaje = "Nueva solicitud de patrocinio enviada correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al reenviar la solicitud.";
                $tipo_mensaje = "danger";
            }
        }
    }
    
    // Cancelar patrocinio (solo para solicitudes pendientes o aceptadas)
    if (isset($_POST['accion']) && $_POST['accion'] == 'cancelar_patrocinio') {
        $id_patrocinio = intval($_POST['id_patrocinio']);
        
        // Verificar que el patrocinio pertenezca a esta empresa y esté pendiente o aceptado
        $stmt = $conexion->prepare("SELECT id_patrocinio, estado FROM patrocinios WHERE id_patrocinio = ? AND id_empresa = ? AND estado IN ('pendiente', 'aceptado')");
        $stmt->bind_param("ii", $id_patrocinio, $id_empresa);
        $stmt->execute();
        $patrocinio = $stmt->get_result()->fetch_assoc();
        
        if ($patrocinio) {
            // Eliminar el patrocinio (o cambiarlo a estado 'rechazado' por la empresa)
            $stmt = $conexion->prepare("UPDATE patrocinios SET estado = 'rechazado', comentarios_empresa = CONCAT(comentarios_empresa, ' [CANCELADO POR LA EMPRESA]') WHERE id_patrocinio = ?");
            $stmt->bind_param("i", $id_patrocinio);
            if ($stmt->execute()) {
                $mensaje = "Patrocinio cancelado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al cancelar el patrocinio.";
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = "No se encontró el patrocinio o ya no es válido.";
            $tipo_mensaje = "warning";
        }
    }

    // Eliminar patrocinios rechazados para liberar el proyecto
    if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar_rechazados') {
        $id_articulo = intval($_POST['id_articulo']);
        
        // Verificar que todos los patrocinios de esta empresa para este proyecto estén rechazados
        $stmt = $conexion->prepare("
            SELECT COUNT(*) as total,
                SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazados
            FROM patrocinios 
            WHERE id_articulo = ? AND id_empresa = ?
        ");
        $stmt->bind_param("ii", $id_articulo, $id_empresa);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['total'] > 0 && $result['total'] == $result['rechazados']) {
            // Todos están rechazados, se pueden eliminar
            $stmt = $conexion->prepare("DELETE FROM patrocinios WHERE id_articulo = ? AND id_empresa = ? AND estado = 'rechazado'");
            $stmt->bind_param("ii", $id_articulo, $id_empresa);
            if ($stmt->execute()) {
                $mensaje = "Se han eliminado tus solicitudes rechazadas. El proyecto está disponible nuevamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar las solicitudes: " . $conexion->error;
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = "No se pueden eliminar las solicitudes porque hay una pendiente o aceptada.";
            $tipo_mensaje = "warning";
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

// Obtener proyectos aprobados para mostrar (excluyendo los que ya tienen solicitud pendiente o aceptada)
$sql_proyectos = "SELECT a.id_articulo, a.titulo, a.categoria, a.tipo_trabajo, a.resumen,
                         u.nombre as autor_nombre, u.apellidos as autor_apellidos, u.correo as autor_correo,
                         (SELECT COUNT(*) FROM patrocinios p WHERE p.id_articulo = a.id_articulo AND p.id_empresa = ? AND p.estado IN ('pendiente', 'aceptado')) as ya_solicitado,
                         (SELECT nombre_archivo FROM proyecto_imagen WHERE id_articulo = a.id_articulo AND es_principal = 1 LIMIT 1) as imagen
                  FROM articulo a
                  JOIN usuario u ON a.id_usuario = u.id_usuario
                  WHERE a.estado = 'aprobado'
                  ORDER BY a.fecha_registro DESC";
$stmt = $conexion->prepare($sql_proyectos);
$stmt->bind_param("i", $id_empresa);
$stmt->execute();
$proyectos_disponibles = $stmt->get_result();

// Obtener historial de solicitudes de esta empresa (con más detalles)
$solicitudes = obtener_solicitudes_empresa($conexion, $id_empresa);

// Obtener conteo de revisiones pendientes
$revisiones_pendientes = 0;

// Obtener notificaciones para el navbar
$total_notificaciones = 0;
$notificaciones_no_leidas = [];
$ultimas_notificaciones = [];

if (esta_logeado()) {
    $total_notificaciones = contar_notificaciones_no_leidas($conexion, $_SESSION['id_usuario']);
    $notificaciones_no_leidas = obtener_notificaciones_no_leidas($conexion, $_SESSION['id_usuario'], 5);
    $ultimas_notificaciones = obtener_notificaciones($conexion, $_SESSION['id_usuario'], 0, 10);
}
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
    
    <link rel="stylesheet" href="Css/interfaz_usuario.css?v=<?php echo time(); ?>">
    <style>
        /* Estilos adicionales para botones de acción en tabla */
        .btn-accion-tabla {
            padding: 5px 10px;
            font-size: 0.8rem;
            margin: 2px;
            border-radius: 5px;
        }
        .solicitudes-table td {
            vertical-align: middle;
        }
        .acciones-cell {
            min-width: 150px;
        }
        .modal-cancelar .modal-header {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            color: white;
        }
        .modal-reenviar .modal-header {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #000;
        }
        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .estado-aceptado {
            background-color: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .estado-rechazado {
            background-color: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .modal-liberar .modal-header {
            background: linear-gradient(135deg, #17a2b8, #0d6efd);
            color: white;
        }
        /* Modal Patrocinio */
        .modal-patrocinio .modal-content {
            border: none !important;
            border-radius: 20px !important;
            overflow: hidden !important;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2) !important;
        }

        .modal-patrocinio .modal-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a) !important;
            color: white !important;
            padding: 20px 25px !important;
            border-bottom: none !important;
        }

        .modal-patrocinio .modal-header .modal-title {
            font-weight: 700 !important;
            font-size: 1.2rem !important;
        }

        .modal-patrocinio .modal-body {
            padding: 25px !important;
        }

        .modal-patrocinio .modal-footer {
            padding: 15px 25px !important;
            border-top: 1px solid #e9ecef !important;
            background: #f8f9fa !important;
        }

        .modal-patrocinio .modal-footer .btn {
            padding: 10px 20px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .modal-patrocinio .modal-footer .btn:hover {
            transform: translateY(-2px) !important;
        }

        .modal-icono.patrocinio {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(213,159,15,0.15);
            color: #D59F0F;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }

        .modal-patrocinio .proyecto-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 15px;
            border-left: 4px solid #D59F0F;
        }

        .modal-patrocinio textarea {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .modal-patrocinio textarea:focus {
            border-color: #D59F0F;
            box-shadow: 0 0 0 0.2rem rgba(213,159,15,0.25);
        }

        /* Animación */
        .modal.fade .modal-dialog {
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
        }
    </style>
</head>
<body>
    <!-- Navbar (igual que antes) -->
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
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="convocatoria.php"><i class="fas fa-scroll me-1"></i>Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="ponencias.php"><i class="fas fa-chalkboard me-1"></i>Ponencias</a></li>
                    <li class="nav-item"><a class="nav-link" href="programa/index_programa.php"><i class="fas fa-calendar me-1"></i>Programa</a></li>
                    
                    <?php if (esta_logeado()): ?>
                        <?php if (es_empresa()): ?>
                            <li class="nav-item"><a class="nav-link" href="patrocinar_proyectos.php"><i class="fas fa-hand-holding-usd me-1"></i>Patrocinar</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="mis_proyectos.php"><i class="fas fa-project-diagram me-1"></i>Mis Proyectos</a></li>
                            <li class="nav-item"><a class="nav-link" href="registrar_trabajos.php"><i class="fas fa-upload me-1"></i>Registrar Trabajo</a></li>
                        <?php endif; ?>
                        
                        <!-- ========== DROPDOWN DE NOTIFICACIONES CON BOTÓN ELIMINAR ========== -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="notificacionesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if ($total_notificaciones > 0): ?>
                                    <span class="badge bg-danger rounded-pill notificacion-badge" style="font-size: 0.7rem; margin-left: -5px; margin-top: -10px;"><?php echo $total_notificaciones; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notificacion-dropdown">
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bell me-2"></i>Notificaciones</span>
                                    <div>
                                        <?php if ($total_notificaciones > 0): ?>
                                            <a href="notificaciones/marcar_todas.php" class="text-decoration-none small me-2">Marcar todas</a>
                                        <?php endif; ?>
                                        <a href="notificaciones/ver_todas.php" class="text-decoration-none small">Ver todas</a>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider m-0"></li>
                                
                                <?php if ($total_notificaciones == 0 && $ultimas_notificaciones->num_rows == 0): ?>
                                    <li class="no-notificaciones">
                                        <i class="fas fa-inbox"></i>
                                        No hay notificaciones
                                    </li>
                                <?php else: ?>
                                    <?php 
                                    $notif_items = [];
                                    if ($notificaciones_no_leidas) {
                                        while ($notif = $notificaciones_no_leidas->fetch_assoc()) {
                                            $notif_items[] = $notif;
                                        }
                                    }
                                    if ($ultimas_notificaciones) {
                                        $ultimas_notificaciones->data_seek(0);
                                        while ($notif = $ultimas_notificaciones->fetch_assoc()) {
                                            if ($notif['leida'] == 1 && count($notif_items) < 10) {
                                                $notif_items[] = $notif;
                                            }
                                        }
                                    }
                                    
                                    foreach (array_slice($notif_items, 0, 10) as $notif): 
                                    ?>
                                    <li class="dropdown-item notificacion-item" data-id="<?php echo $notif['id_notificacion']; ?>" style="padding: 12px 15px;">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3 flex-shrink-0">
                                                <i class="fas <?php echo $notif['icono'] ?? 'fa-bell'; ?> 
                                                text-<?php echo $notif['tipo'] == 'success' ? 'success' : ($notif['tipo'] == 'danger' ? 'danger' : 'primary'); ?>">
                                                </i>
                                            </div>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                                                <div class="small text-muted" style="word-break: break-word; line-height: 1.4;">
                                                    <?php 
                                                    $mensaje_corto = htmlspecialchars($notif['mensaje']);
                                                    if (strlen($mensaje_corto) > 80) {
                                                        $mensaje_corto = substr($mensaje_corto, 0, 80) . '...';
                                                    }
                                                    echo $mensaje_corto;
                                                    ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo time_elapsed_string($notif['fecha_creacion']); ?>
                                                </div>
                                            </div>
                                            <div class="ms-2 flex-shrink-0 d-flex align-items-center gap-1">
                                                <?php if (!$notif['leida']): ?>
                                                    <span class="badge bg-primary rounded-pill" style="font-size: 0.6rem;">Nueva</span>
                                                <?php endif; ?>
                                                <!-- Botón eliminar FUERA del enlace -->
                                                <button type="button" class="btn-eliminar" 
                                                        onclick="eliminarNotificacion(<?php echo $notif['id_notificacion']; ?>, this)"
                                                        title="Eliminar notificación">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Enlace separado que NO cubre el botón -->
                                        <?php if ($notif['enlace']): ?>
                                            <div class="mt-1">
                                                <a href="<?php echo htmlspecialchars($notif['enlace']); ?>" class="small text-decoration-none" 
                                                onclick="marcarNotificacion(<?php echo $notif['id_notificacion']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>Ver detalles
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                    <li><hr class="dropdown-divider m-0"></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                        
                        <!-- Menú de usuario -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                                <?php if (es_empresa()): ?>
                                    <li><a class="dropdown-item" href="patrocinar_proyectos.php"><i class="fas fa-hand-holding-usd me-2"></i>Patrocinar</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos</a></li>
                                <?php endif; ?>
                                <?php if (es_docente() && !es_empresa()): ?>
                                    <li><a class="dropdown-item" href="revisiones.php"><i class="fas fa-tasks me-2"></i>Mis revisiones</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="notificaciones/preferencias.php"><i class="fas fa-bell me-2"></i>Preferencias</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                        
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="registro.php"><i class="fas fa-user-plus me-1"></i>Registro</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

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

        <!-- Mensajes -->
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
                <div class="section-header-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="section-title mb-1">
                                <i class="fas fa-search me-2"></i>Proyectos Disponibles
                            </h3>
                            <p class="section-subtitle mb-0">Explora los proyectos aprobados y elige cuál deseas patrocinar</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-primary rounded-pill px-3 py-2">
                                <i class="fas fa-lightbulb me-1"></i> <?php echo $proyectos_disponibles->num_rows; ?> disponibles
                            </span>
                        </div>
                    </div>
                </div>
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
                            <?php 
                            $intentos_agotados_proy = intentos_patrocinio_agotados($conexion, $proyecto['id_articulo'], $id_empresa);
                            ?>
                            <?php if ($proyecto['ya_solicitado'] > 0): ?>
                                <button class="btn btn-secondary w-100" disabled>
                                    <i class="fas fa-check me-2"></i>Ya solicitado
                                </button>
                            <?php elseif ($intentos_agotados_proy): ?>
                                <button class="btn btn-secondary w-100" disabled>
                                    <i class="fas fa-ban me-2"></i>Intentos agotados
                                </button>
                                <small class="text-danger d-block text-center mt-1">Ya enviaste 2 solicitudes para este proyecto</small>
                            <?php else: ?>
                                <button type="button" class="btn btn-patrocinar w-100" data-bs-toggle="modal" data-bs-target="#modalPatrocinio" 
                                        data-id="<?php echo $proyecto['id_articulo']; ?>" 
                                        data-titulo="<?php echo htmlspecialchars($proyecto['titulo']); ?>"
                                        data-autor="<?php echo htmlspecialchars($proyecto['autor_nombre'] . ' ' . $proyecto['autor_apellidos']); ?>">
                                    <i class="fas fa-gem me-2"></i>Solicitar Patrocinio
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Historial de solicitudes -->
        <div class="row mt-5">
            <div class="col-12 mb-4">
                <div class="section-header-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="section-title mb-1">
                                <i class="fas fa-history me-2"></i>Mis Solicitudes de Patrocinio
                            </h3>
                            <p class="section-subtitle mb-0">Historial de todas tus solicitudes de patrocinio</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 me-1">
                                <i class="fas fa-clock me-1"></i> <?php echo $stats['pendientes'] ?? 0; ?> pendientes
                            </span>
                            <span class="badge bg-success rounded-pill px-3 py-2">
                                <i class="fas fa-gem me-1"></i> <?php echo $stats['aceptados'] ?? 0; ?> activos
                            </span>
                        </div>
                    </div>
                </div>
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
                                <th><i class="fas fa-comment me-2"></i>Respuesta del Autor</th>
                                <th><i class="fas fa-cogs me-2"></i>Acciones</th>
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
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-success btn-accion-tabla" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalVerRespuesta"
                                                data-proyecto="<?php echo htmlspecialchars($sol['proyecto_titulo']); ?>"
                                                data-respuesta="<?php echo htmlspecialchars($sol['comentarios_autor']); ?>"
                                                onclick="verRespuestaAutor(this)"
                                                title="Ver respuesta completa">
                                            <i class="fas fa-eye me-1"></i> Ver respuesta
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">
                                            <i class="fas fa-minus-circle me-1"></i>Sin comentarios
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="acciones-cell">
                                    <!-- Botón Ver Proyecto (Siempre visible) -->
                                    <a href="ver_proyecto.php?id=<?php echo $sol['id_articulo']; ?>" 
                                    class="btn btn-sm btn-info btn-accion-tabla" 
                                    target="_blank"
                                    title="Ver proyecto">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    
                                    <?php if ($sol['estado'] == 'rechazado'): ?>
                                        <?php
                                        // Verificar si ya agotó los intentos para este proyecto
                                        $intentos_agotados = intentos_patrocinio_agotados($conexion, $sol['id_articulo'], $id_empresa);
                                        ?>
                                        <?php if ($intentos_agotados): ?>
                                            <!-- Ya no puede reenviar - Mostrar botón para liberar -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger btn-accion-tabla" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalLiberarProyecto"
                                                    data-id-articulo="<?php echo $sol['id_articulo']; ?>"
                                                    data-proyecto="<?php echo htmlspecialchars($sol['proyecto_titulo']); ?>"
                                                    title="Eliminar solicitudes rechazadas y liberar proyecto">
                                                <i class="fas fa-undo"></i> Liberar
                                            </button>
                                        <?php else: ?>
                                            <!-- Botón Reenviar Solicitud -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-warning btn-accion-tabla" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalReenviar"
                                                    data-id="<?php echo $sol['id_patrocinio']; ?>"
                                                    data-proyecto="<?php echo htmlspecialchars($sol['proyecto_titulo']); ?>"
                                                    title="Reenviar solicitud (1 reenvío disponible)">
                                                <i class="fas fa-paper-plane"></i> Reenviar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($sol['estado'] == 'pendiente' || $sol['estado'] == 'aceptado'): ?>
                                        <!-- Botón Cancelar Patrocinio -->
                                        <button type="button" 
                                                class="btn btn-sm btn-danger btn-accion-tabla" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalCancelar"
                                                data-id="<?php echo $sol['id_patrocinio']; ?>"
                                                data-proyecto="<?php echo htmlspecialchars($sol['proyecto_titulo']); ?>"
                                                data-estado="<?php echo $sol['estado']; ?>"
                                                title="Cancelar patrocinio">
                                            <i class="fas fa-trash-alt"></i> Cancelar
                                        </button>
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
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <a href="index.php" class="btn-volver-pequeno">
                        <i class="fas fa-arrow-left me-2"></i>
                        Volver al Inicio
                    </a>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Modal para solicitar patrocinio (NUEVO) -->
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

    <!-- Modal para REENVIAR solicitud (nuevo) -->
    <div class="modal fade modal-reenviar" id="modalReenviar" tabindex="-1" aria-labelledby="modalReenviarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalReenviarLabel">
                            <i class="fas fa-paper-plane me-2"></i>Reenviar Solicitud de Patrocinio
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="reenviar_solicitud">
                        <input type="hidden" name="id_patrocinio" id="id_patrocinio_reenviar">
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-hand-holding-heart fa-3x" style="color: #ffc107;"></i>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Estás a punto de reenviar una solicitud de patrocinio que fue rechazada anteriormente.
                        </div>
                        
                        <p class="text-center mb-3">
                            ¿Deseas volver a solicitar el patrocinio para el proyecto?
                        </p>
                        <div class="proyecto-info text-center mb-3">
                            <strong id="proyecto_reenviar_nombre" class="text-warning"></strong>
                        </div>
                        
                        <div class="mb-3 mt-3">
                            <label for="comentarios_reenviar" class="form-label fw-semibold">
                                <i class="fas fa-comment me-2"></i>Nuevo mensaje para el autor
                            </label>
                            <textarea class="form-control" name="comentarios" id="comentarios_reenviar" rows="4" 
                                      placeholder="Escribe un nuevo mensaje para el autor del proyecto..."></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>Un nuevo mensaje personalizado puede mejorar tus posibilidades.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-paper-plane me-2"></i>Reenviar Solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para CANCELAR patrocinio (nuevo) -->
    <div class="modal fade modal-cancelar" id="modalCancelar" tabindex="-1" aria-labelledby="modalCancelarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalCancelarLabel">
                            <i class="fas fa-trash-alt me-2"></i>Cancelar Patrocinio
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="cancelar_patrocinio">
                        <input type="hidden" name="id_patrocinio" id="id_patrocinio_cancelar">
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-warning me-2"></i>
                            <strong>¡Atención!</strong> Esta acción es irreversible.
                        </div>
                        
                        <p class="text-center mb-3">
                            ¿Estás seguro de que deseas cancelar el patrocinio del siguiente proyecto?
                        </p>
                        <div class="proyecto-info text-center mb-3 p-3 bg-light rounded">
                            <strong id="proyecto_cancelar_nombre" class="text-danger"></strong>
                        </div>
                        
                        <p class="small text-muted text-center">
                            El autor del proyecto será notificado de la cancelación.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-arrow-left me-2"></i>Volver
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Sí, Cancelar Patrocinio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para LIBERAR proyecto (eliminar rechazados) -->
    <div class="modal fade modal-liberar" id="modalLiberarProyecto" tabindex="-1" aria-labelledby="modalLiberarLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8, #0d6efd); color: white;">
                        <h5 class="modal-title" id="modalLiberarLabel">
                            <i class="fas fa-undo me-2"></i>Liberar Proyecto
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="eliminar_rechazados">
                        <input type="hidden" name="id_articulo" id="id_articulo_liberar">
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-sync-alt fa-3x" style="color: #17a2b8;"></i>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¿Qué hace esta acción?</strong>
                            <ul class="mb-0 mt-2">
                                <li>Elimina todas tus solicitudes <strong>rechazadas</strong> para este proyecto</li>
                                <li>El proyecto volverá a estar disponible para enviar una nueva solicitud</li>
                                <li>Solo funciona si NO tienes solicitudes pendientes o aceptadas</li>
                            </ul>
                        </div>
                        
                        <p class="text-center mb-3">
                            ¿Deseas liberar el siguiente proyecto para volver a solicitarlo?
                        </p>
                        <div class="proyecto-info text-center mb-3 p-3 bg-light rounded">
                            <strong id="proyecto_liberar_nombre" class="text-info"></strong>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Se eliminará todo el historial de solicitudes rechazadas para este proyecto.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-info text-white">
                            <i class="fas fa-undo me-2"></i>Sí, Liberar Proyecto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para ver respuesta completa del autor -->
    <div class="modal fade" id="modalVerRespuesta" tabindex="-1" aria-labelledby="modalVerRespuestaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white;">
                    <h5 class="modal-title" id="modalVerRespuestaLabel">
                        <i class="fas fa-reply me-2"></i>Respuesta del Autor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold"><i class="fas fa-project-diagram me-2"></i>Proyecto:</label>
                        <p id="modal_respuesta_proyecto" class="p-2 bg-light rounded"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold"><i class="fas fa-comment-dots me-2"></i>Mensaje del autor:</label>
                        <div class="p-3 rounded" style="background: #e8f5e9; border-left: 4px solid #28a745;" id="modal_respuesta_texto">
                            Cargando...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cerrar
                    </button>
                </div>
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
                        <a href="https://x.com/FESC_UNAM" class="text-white fs-3"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/fescunamoficial" class="text-white fs-3"><i class="fab fa-instagram"></i></a>
                        <a href="https://youtube.com/@fescunamoficial9877" class="text-white fs-3"><i class="fab fa-youtube"></i></a>
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
        // Pasar datos al modal de solicitud de patrocinio
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

            modalPatrocinio.addEventListener('hidden.bs.modal', function() {
                document.getElementById('comentarios').value = '';
            });
        }

        // Pasar datos al modal de reenviar solicitud
        var modalReenviar = document.getElementById('modalReenviar');
        if (modalReenviar) {
            modalReenviar.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var idPatrocinio = button.getAttribute('data-id');
                var proyectoNombre = button.getAttribute('data-proyecto');
                
                document.getElementById('id_patrocinio_reenviar').value = idPatrocinio;
                document.getElementById('proyecto_reenviar_nombre').textContent = proyectoNombre;
            });

            modalReenviar.addEventListener('hidden.bs.modal', function() {
                document.getElementById('comentarios_reenviar').value = '';
            });
        }

        // Pasar datos al modal de cancelar patrocinio
        var modalCancelar = document.getElementById('modalCancelar');
        if (modalCancelar) {
            modalCancelar.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var idPatrocinio = button.getAttribute('data-id');
                var proyectoNombre = button.getAttribute('data-proyecto');
                var estado = button.getAttribute('data-estado');
                
                document.getElementById('id_patrocinio_cancelar').value = idPatrocinio;
                document.getElementById('proyecto_cancelar_nombre').textContent = proyectoNombre;
                
                // Cambiar el mensaje según el estado
                var mensajeAdicional = estado === 'aceptado' ? 
                    ' (Este patrocinio ya había sido aceptado por el autor)' : 
                    ' (Esta solicitud aún está pendiente de respuesta)';
                
                var titulo = document.getElementById('proyecto_cancelar_nombre');
                if (titulo && !titulo.innerHTML.includes(mensajeAdicional)) {
                    titulo.innerHTML = proyectoNombre + '<br><small class="text-muted">' + mensajeAdicional + '</small>';
                }
            });
        }

        // Pasar datos al modal de liberar proyecto
        var modalLiberar = document.getElementById('modalLiberarProyecto');
        if (modalLiberar) {
            modalLiberar.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var idArticulo = button.getAttribute('data-id-articulo');
                var proyectoNombre = button.getAttribute('data-proyecto');
                
                document.getElementById('id_articulo_liberar').value = idArticulo;
                document.getElementById('proyecto_liberar_nombre').textContent = proyectoNombre;
            });
        }
    </script>
    <script>
    function marcarNotificacion(id) {
        fetch('notificaciones/marcar_leida.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        });
    }

    function eliminarNotificacion(id, btnElement) {
        event.stopPropagation();
        
        if (!confirm('¿Eliminar esta notificación?')) return;
        
        const item = btnElement.closest('.notificacion-item');
        if (!item) return;
        
        const dropdownMenu = document.querySelector('.notificacion-dropdown');
        const badge = document.querySelector('.notificacion-badge');
        
        item.style.transition = 'all 0.2s ease';
        item.style.opacity = '0';
        item.style.transform = 'translateX(20px)';
        
        fetch('notificaciones/eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setTimeout(() => {
                    const divider = item.nextElementSibling;
                    item.remove();
                    if (divider && divider.tagName === 'HR') divider.remove();
                    
                    const notificacionesRestantes = document.querySelectorAll('.notificacion-item').length;
                    
                    if (notificacionesRestantes === 0) {
                        if (badge) badge.remove();
                        
                        if (dropdownMenu && !dropdownMenu.querySelector('.no-notificaciones')) {
                            const remainingDividers = dropdownMenu.querySelectorAll('hr');
                            remainingDividers.forEach(hr => hr.remove());
                            
                            const emptyMessage = document.createElement('li');
                            emptyMessage.className = 'no-notificaciones';
                            emptyMessage.innerHTML = '<i class="fas fa-inbox"></i>No hay notificaciones';
                            
                            const header = dropdownMenu.querySelector('.dropdown-header');
                            if (header) {
                                header.insertAdjacentElement('afterend', emptyMessage);
                            }
                        }
                    } else {
                        if (badge) badge.textContent = notificacionesRestantes;
                    }
                }, 200);
            } else {
                alert('Error al eliminar la notificación');
                item.style.opacity = '1';
                item.style.transform = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
            item.style.opacity = '1';
            item.style.transform = '';
        });
    }
    function verRespuestaAutor(btn) {
    var proyecto = btn.getAttribute('data-proyecto');
    var respuesta = btn.getAttribute('data-respuesta');
    
    document.getElementById('modal_respuesta_proyecto').innerHTML = 
        '<i class="fas fa-file-alt me-2 text-primary"></i> ' + proyecto;
    document.getElementById('modal_respuesta_texto').innerHTML = 
        '<i class="fas fa-quote-left me-2 text-muted"></i>' + respuesta.replace(/\n/g, '<br>');
}
    </script>
</body>
</html>