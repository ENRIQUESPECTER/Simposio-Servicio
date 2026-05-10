<?php
session_start();
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/notificaciones.php';
require_once '../includes/funciones.php';

if (!esta_logeado()) {
    header('Location: ../login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';


// ========== ASEGURAR QUE EL USUARIO TIENE PREFERENCIAS ==========
$stmt = $conexion->prepare("SELECT id_preferencia FROM preferencias_notificaciones WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();

if ($stmt->get_result()->num_rows == 0) {
    // Crear preferencias por defecto para este usuario
    $stmt_insert = $conexion->prepare("INSERT INTO preferencias_notificaciones (id_usuario, notificar_email, notificar_sistema) VALUES (?, 1, 1)");
    $stmt_insert->bind_param("i", $_SESSION['id_usuario']);
    $stmt_insert->execute();
}

// ========== PROCESAR GUARDADO DE PREFERENCIAS ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $notificar_email = isset($_POST['notificar_email']) ? 1 : 0;
    $notificar_sistema = isset($_POST['notificar_sistema']) ? 1 : 0;
    
    $stmt = $conexion->prepare("UPDATE preferencias_notificaciones SET notificar_email = ?, notificar_sistema = ? WHERE id_usuario = ?");
    $stmt->bind_param("iii", $notificar_email, $notificar_sistema, $_SESSION['id_usuario']);
    
    if ($stmt->execute()) {
        $mensaje = "Preferencias guardadas correctamente.";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "Error al guardar preferencias: " . $conexion->error;
        $tipo_mensaje = "danger";
    }
}

// ========== OBTENER PREFERENCIAS ACTUALES ==========
$stmt = $conexion->prepare("SELECT * FROM preferencias_notificaciones WHERE id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$preferencias = $stmt->get_result()->fetch_assoc();

// Si por algún motivo sigue siendo null, crear array por defecto
if (!$preferencias) {
    $preferencias = [
        'notificar_email' => 1,
        'notificar_sistema' => 1
    ];
}

// ========== OBTENER NOTIFICACIONES PARA EL NAVBAR ==========
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
    <title>Preferencias de Notificaciones - SIMPOSIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../Css/interfaz_usuario.css">
    <style>
        .switch-status {
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .switch-status.activo {
            color: #28a745;
        }
        .switch-status.inactivo {
            color: #dc3545;
        }
    </style>
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
                    <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="../convocatoria.php"><i class="fas fa-scroll me-1"></i>Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="../ponencias.php"><i class="fas fa-chalkboard me-1"></i>Ponencias</a></li>
                    <li class="nav-item"><a class="nav-link" href="../programa/index_programa.php"><i class="fas fa-calendar me-1"></i>Programa</a></li>
                    
                    <?php if (esta_logeado()): ?>
                        <?php if (es_empresa()): ?>
                            <li class="nav-item"><a class="nav-link" href="../patrocinar_proyectos.php"><i class="fas fa-hand-holding-usd me-1"></i>Patrocinar</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="../mis_proyectos.php"><i class="fas fa-project-diagram me-1"></i>Mis Proyectos</a></li>
                            <li class="nav-item"><a class="nav-link" href="../registrar_trabajos.php"><i class="fas fa-upload me-1"></i>Registrar Trabajo</a></li>
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
                                            <a href="marcar_todas.php" class="text-decoration-none small me-2">Marcar todas</a>
                                        <?php endif; ?>
                                        <a href="ver_todas.php" class="text-decoration-none small">Ver todas</a>
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
                                    <li class="dropdown-item notificacion-item" data-id="<?php echo $notif['id_notificacion']; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3 flex-shrink-0">
                                                <i class="fas <?php echo $notif['icono'] ?? 'fa-bell'; ?> 
                                                text-<?php echo $notif['tipo'] == 'success' ? 'success' : ($notif['tipo'] == 'danger' ? 'danger' : 'primary'); ?>">
                                                </i>
                                            </div>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                                                <div class="small text-muted">
                                                    <?php 
                                                    $mensaje_notif = htmlspecialchars($notif['mensaje']);
                                                    if (strlen($mensaje_notif) > 80) {
                                                        $mensaje_notif = substr($mensaje_notif, 0, 80) . '...';
                                                    }
                                                    echo $mensaje_notif;
                                                    ?>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo time_elapsed_string($notif['fecha_creacion']); ?>
                                                </div>
                                            </div>
                                            <div class="ms-2 flex-shrink-0">
                                                <?php if (!$notif['leida']): ?>
                                                    <span class="badge bg-primary rounded-pill" style="font-size: 0.6rem;">Nueva</span>
                                                <?php endif; ?>
                                                <button type="button" class="btn-eliminar" 
                                                        onclick="eliminarNotificacion(<?php echo $notif['id_notificacion']; ?>, this)"
                                                        title="Eliminar notificación">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if ($notif['enlace']): ?>
                                            <?php 
                                            $enlace_notif = $notif['enlace'];
                                            if (strpos($enlace_notif, '../') !== 0 && strpos($enlace_notif, 'http') !== 0 && strpos($enlace_notif, 'notificaciones/') !== 0) {
                                                $enlace_notif = '../' . $enlace_notif;
                                            }
                                            ?>
                                            <div class="mt-1">
                                                <a href="<?php echo htmlspecialchars($enlace_notif); ?>" class="small text-decoration-none" 
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
                                <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                                <?php if (es_empresa()): ?>
                                    <li><a class="dropdown-item" href="../patrocinar_proyectos.php"><i class="fas fa-hand-holding-usd me-2"></i>Patrocinar</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="../mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos</a></li>
                                <?php endif; ?>
                                <?php if (es_docente() && !es_empresa()): ?>
                                    <li><a class="dropdown-item" href="../revisiones.php"><i class="fas fa-tasks me-2"></i>Mis revisiones</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="preferencias.php"><i class="fas fa-bell me-2"></i>Preferencias</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                        
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="../login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="../registro.php"><i class="fas fa-user-plus me-1"></i>Registro</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div style="height: 76px;"></div>

    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Preferencias de Notificaciones</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($mensaje) && $mensaje): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                                <?php echo $mensaje; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notificar_sistema" id="notificar_sistema" value="1" 
                                           <?php echo ($preferencias['notificar_sistema'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificar_sistema">
                                        <i class="fas fa-bell me-2 text-primary"></i>
                                        Notificaciones en el sistema
                                    </label>
                                    <span class="switch-status <?php echo ($preferencias['notificar_sistema'] == 1) ? 'activo' : 'inactivo'; ?>">
                                        <i class="fas <?php echo ($preferencias['notificar_sistema'] == 1) ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                    </span>
                                    <div class="form-text">Recibir notificaciones dentro de la plataforma.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="notificar_email" id="notificar_email" value="1" 
                                           <?php echo ($preferencias['notificar_email'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificar_email">
                                        <i class="fas fa-envelope me-2 text-primary"></i>
                                        Notificaciones por email
                                    </label>
                                    <span class="switch-status <?php echo ($preferencias['notificar_email'] == 1) ? 'activo' : 'inactivo'; ?>">
                                        <i class="fas <?php echo ($preferencias['notificar_email'] == 1) ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                    </span>
                                    <div class="form-text">Recibir notificaciones en tu correo electrónico.</div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-tag me-2"></i>Tipos de notificaciones a recibir
                                </label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="tipo_patrocinios" checked disabled>
                                            <label class="form-check-label">Patrocinios</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="tipo_revisiones" checked disabled>
                                            <label class="form-check-label">Revisiones de trabajos</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="tipo_eventos" checked disabled>
                                            <label class="form-check-label">Eventos y recordatorios</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="tipo_sistema" checked disabled>
                                            <label class="form-check-label">Notificaciones del sistema</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text text-muted small mt-2">
                                    <i class="fas fa-info-circle me-1"></i>Por ahora todos los tipos están habilitados.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Guardar preferencias
                                </button>
                                <a href="../index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Volver
                                </a>
                            </div>
                        </form>
                    </div>
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
                    <h5 class="mb-3"><i class="fas fa-address-card me-2"></i><a class="text-white" href="../contactanos.php" style="text-decoration: none;">Contactanos</a></h5>
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
        function marcarNotificacion(id) {
            fetch('marcar_leida.php', {
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
            
            fetch('eliminar.php', {
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
        
        // Mostrar estado del switch en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const switches = document.querySelectorAll('.form-switch input');
            switches.forEach(sw => {
                const span = sw.closest('.form-switch').querySelector('.switch-status');
                if (span) {
                    const updateStatus = () => {
                        const isChecked = sw.checked;
                        span.innerHTML = `<i class="fas ${isChecked ? 'fa-toggle-on' : 'fa-toggle-off'}"></i>`;
                        span.className = `switch-status ${isChecked ? 'activo' : 'inactivo'}`;
                    };
                    sw.addEventListener('change', updateStatus);
                    updateStatus();
                }
            });
        });
    </script>
</body>
</html>