<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/notificaciones.php';
require_once 'includes/notificaciones.php';

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
                // ✅ AGREGAR ESTO: Obtener el id_usuario del autor
                $stmt_autor = $conexion->prepare("SELECT id_usuario FROM articulo WHERE id_articulo = ?");
                $stmt_autor->bind_param("i", $id_articulo);
                $stmt_autor->execute();
                $id_autor = $stmt_autor->get_result()->fetch_assoc()['id_usuario'];
                // Asegurar que la actividad sea visible
                $stmt3 = $conexion->prepare("UPDATE actividad_evento SET visible = 1 WHERE id_articulo = ?");
                $stmt3->bind_param("i", $id_articulo);
                $stmt3->execute();
                $mensaje = "Trabajo aprobado correctamente.";
                notificar_trabajo_aprobado($conexion, $id_articulo, $id_autor);
            } else { // rechazar
                $stmt2 = $conexion->prepare("UPDATE articulo SET estado = 'rechazado' WHERE id_articulo = ?");
                $stmt2->bind_param("i", $id_articulo);
                $stmt2->execute();
                $stmt3 = $conexion->prepare("UPDATE actividad_evento SET visible = 0 WHERE id_articulo = ?");
                $stmt3->bind_param("i", $id_articulo);
                $stmt3->execute();
                $mensaje = "Trabajo rechazado.";
                // ✅ AGREGAR ESTO: Obtener el id_usuario del autor para la notificación
                $stmt_autor = $conexion->prepare("SELECT id_usuario FROM articulo WHERE id_articulo = ?");
                $stmt_autor->bind_param("i", $id_articulo);
                $stmt_autor->execute();
                $id_autor = $stmt_autor->get_result()->fetch_assoc()['id_usuario'];
                notificar_trabajo_rechazado($conexion, $id_articulo, $id_autor);
                notificar_devolucion_correcciones($conexion, $id_articulo, $id_docente, "Se requieren cambios. Revisa los comentarios del revisor.");
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
    <title>Mis revisiones - Docente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <style>
        .container { margin-top: 100px; }
        .card-revision { border-left: 4px solid #D59F0F; transition: 0.3s; margin-bottom: 1.5rem; }
        .card-revision:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
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
    </script>
</body>
</html>