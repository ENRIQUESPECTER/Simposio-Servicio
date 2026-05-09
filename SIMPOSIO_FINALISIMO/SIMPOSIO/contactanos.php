<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';
require_once 'includes/notificaciones.php';

$revisiones_pendientes = 0;
if (es_docente()) {
    $stmt = $conexion->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
    $stmt->bind_param("i", $_SESSION['id_usuario']);
    $stmt->execute();
    $docente = $stmt->get_result()->fetch_assoc();
    if ($docente) {
        $revisiones_pendientes = contar_revisiones_docente($conexion, $docente['id_docente']);
    }
}

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    
    <!-- Estilos externos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="Css/contactanos.css">
    <link rel="stylesheet" href="Css/interfaz_usuario.css"> 
    <script src="Js/funciones.js"></script>
    <title>SIMPOSIO FESC C4 - Contacto</title>
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

    <!-- Encabezado de la página -->
    <div class="page-header" style="margin-top: 4rem;">
        <div class="container text-center">
            <h1 class="display-4 fw-bold">Contáctanos</h1>
            <p class="lead">Estamos aquí para ayudarte y responder a tus preguntas</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Información de contacto -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="contact-info-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>Dirección</h4>
                    <p class="mb-0">Av. Universidad 3000</p>
                    <p class="mb-0">Copilco Universidad, Coyoacán</p>
                    <p>04510 Ciudad de México, CDMX</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="contact-info-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h4>Teléfono</h4>
                    <p class="mb-0">+52 (55) 5622 1234</p>
                    <p class="mb-0">+52 (55) 5622 5678</p>
                    <p>Lunes a Viernes: 9:00 - 18:00</p>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="contact-info-card text-center">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h4>Email</h4>
                    <p class="mb-0">contacto@simposiofesc.unam.mx</p>
                    <p class="mb-0">proyectos@fesc.unam.mx</p>
                    <p>atención@simposiofesc.mx</p>
                </div>
            </div>
        </div>

        <!-- Formulario de contacto y mapa -->
        <div class="row">
            
            <div>
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3756.482182231608!2d-99.18973539999999!3d19.6920852!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85d21fe02541babb%3A0x13d9c1b986e25ecc!2sFacultad%20de%20Estudios%20Superiores%20Cuautitl%C3%A1n!5e0!3m2!1ses-419!2smx!4v1774544060686!5m2!1ses-419!2smx"
                        width="100%" 
                        height="450" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                </div>
                <div class="mt-4 text-center">
                    <h5>Síguenos en redes sociales</h5>
                    <div class="mt-3">
                        <a href="https://www.facebook.com/fescunamoficial/about?locale=es_LA" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://x.com/FESC_UNAM?fbclid=IwY2xjawQyQHxleHRuA2FlbQIxMABicmlkETFvUEhaR0VMQmo5UEQ1b0M0c3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHunbJB2FGEliNtdbtCRQ5rraIYqxrw-P_F1GfK3vbH2iH1LCVWqhSXpl2LP7_aem_vLlrun1rax8EMbKR0qgxBQ" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/fescunamoficial?fbclid=IwY2xjawQyQnJleHRuA2FlbQIxMABicmlkETFjOU9lY2lsNWhBREVmV1Nxc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHvwGr8ZN8ksdMDFGCUCpjhMbJJW9cbvuMXJ5qhpo6m2tuK4zV1DqLw3vk0vB_aem_XcaPSOTLV8iGNi3yf750EQ" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.youtube.com/@bombocho543" class="social-icon"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección de preguntas frecuentes -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="text-center mb-4">Preguntas frecuentes</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                ¿Cómo puedo participar en el simposio?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Puedes participar como ponente, asistente o expositor de proyectos. Para más información, contáctanos a través del formulario o envía un correo a proyectos@fesc.unam.mx.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                ¿Cuáles son los costos de inscripción?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Los costos varían según la categoría de participación. Estudiantes de la UNAM tienen descuentos especiales. Contáctanos para obtener información detallada sobre tarifas y becas disponibles.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                ¿Las empresas pueden patrocinar el evento?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Sí, contamos con diferentes niveles de patrocinio para empresas interesadas en apoyar el simposio. Pueden contactarnos directamente para conocer los beneficios y paquetes disponibles.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingFour">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                ¿Cómo puedo presentar mi proyecto en la Red Universitaria?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Para presentar tu proyecto, debes registrarte en nuestra plataforma y seguir las guías de participación. Puedes encontrar más información en la sección "Red Universitaria de Proyectos" o contactarnos directamente.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

<!-- Scripts -->
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