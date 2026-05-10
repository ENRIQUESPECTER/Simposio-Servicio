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

$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$notificaciones = obtener_notificaciones($conexion, $_SESSION['id_usuario'], $offset, $por_pagina);

// Variables para el navbar
$total_notificaciones = contar_notificaciones_no_leidas($conexion, $_SESSION['id_usuario']);
$notificaciones_no_leidas = obtener_notificaciones_no_leidas($conexion, $_SESSION['id_usuario'], 5);
$ultimas_notificaciones = obtener_notificaciones($conexion, $_SESSION['id_usuario'], 0, 10);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Notificaciones - SIMPOSIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../Css/interfaz_usuario.css">
    <style>
        .btn-eliminar {
            background: transparent;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 0.8rem;
            transition: transform 0.2s;
            position: relative;
            z-index: 10;
        }
        .btn-eliminar:hover {
            color: #b02a37;
            transform: scale(1.2);
        }
        .no-notificaciones {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            list-style: none;
        }
        .no-notificaciones i {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
        }
        .list-group-item {
            transition: all 0.2s ease;
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
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="notificacionesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if ($total_notificaciones > 0): ?>
                                    <span class="badge bg-danger rounded-pill notificacion-badge" style="font-size: 0.7rem; margin-left: -5px; margin-top: -10px;"><?php echo $total_notificaciones; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notificacion-dropdown" style="width: 350px; max-height: 500px; overflow-y: auto;">
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
                                                text-<?php echo $notif['tipo'] == 'success' ? 'success' : ($notif['tipo'] == 'danger' ? 'danger' : 'primary'); ?>"></i>
                                            </div>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars(substr($notif['mensaje'], 0, 80)); ?>...
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
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-bell me-2"></i>Todas las notificaciones</h4>
                        <?php if ($total_notificaciones > 0): ?>
                            <a href="marcar_todas.php" class="btn btn-sm btn-light">
                                <i class="fas fa-check-double me-1"></i>Marcar todas como leídas
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($notificaciones->num_rows == 0): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <p class="text-muted">No hay notificaciones para mostrar</p>
                                <a href="../index.php" class="btn btn-primary">Volver al inicio</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php while ($notif = $notificaciones->fetch_assoc()): ?>
                                <div class="list-group-item <?php echo $notif['leida'] ? '' : 'list-group-item-light'; ?>">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <i class="fas <?php echo $notif['icono'] ?? 'fa-bell'; ?> fa-2x text-<?php echo $notif['tipo']; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notif['titulo']); ?></h6>
                                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($notif['fecha_creacion'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($notif['mensaje'])); ?></p>
                                            <div class="d-flex gap-2 mt-2">
                                                <?php if ($notif['enlace']): ?>
                                                    <?php 
                                                    // Ajustar ruta del enlace si es necesario
                                                    $enlace = $notif['enlace'];
                                                    // Si el enlace no empieza con ../ ni con http, agregar ../
                                                    if (strpos($enlace, '../') !== 0 && strpos($enlace, 'http') !== 0 && strpos($enlace, 'notificaciones/') !== 0) {
                                                        $enlace = '../' . $enlace;
                                                    }
                                                    ?>
                                                    <a href="<?php echo htmlspecialchars($enlace); ?>" class="btn btn-sm btn-outline-primary" 
                                                       onclick="marcarNotificacion(<?php echo $notif['id_notificacion']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>Ver detalles
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarNotificacion(<?php echo $notif['id_notificacion']; ?>, this)">
                                                    <i class="fas fa-trash-alt me-1"></i>Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Botón volver -->
                <div class="text-center mt-4">
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al inicio
                    </a>
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
        
        const item = btnElement.closest('.list-group-item');
        if (!item) return;
        
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
                    item.remove();
                    
                    // Actualizar badge del navbar
                    const badge = document.querySelector('.notificacion-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        const newCount = currentCount - 1;
                        if (newCount > 0) {
                            badge.textContent = newCount;
                        } else {
                            badge.remove();
                        }
                    }
                    
                    // Si no quedan notificaciones, mostrar mensaje
                    const listGroup = document.querySelector('.list-group');
                    if (listGroup) {
                        const remainingItems = listGroup.querySelectorAll('.list-group-item').length;
                        if (remainingItems === 0) {
                            const cardBody = document.querySelector('.card-body');
                            cardBody.innerHTML = `
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">No hay notificaciones para mostrar</p>
                                    <a href="../index.php" class="btn btn-primary">Volver al inicio</a>
                                </div>
                            `;
                        }
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