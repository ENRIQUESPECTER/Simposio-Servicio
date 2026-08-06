<?php
session_start();
include 'config/database.php';
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
require_once '../includes/notificaciones.php';

// Verificar si el usuario está logueado
if (!esta_logeado()) {
    header('Location: ../login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del usuario
$stmt = $conexion->prepare("SELECT u.*, 
    a.id_alumno, a.matricula, a.carrera, a.semestre,
    d.id_docente, d.especialidad, d.grado_academico,
    e.id_empresa, e.nombre_empresa, e.sector
    FROM usuario u
    LEFT JOIN alumno a ON u.id_usuario = a.id_usuario
    LEFT JOIN docente d ON u.id_usuario = d.id_usuario
    LEFT JOIN empresa e ON u.id_usuario = e.id_usuario
    WHERE u.id_usuario = ?");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

if (!$usuario) {
    die("Usuario no encontrado");
}

$tipo_usuario = $usuario['tipo_usuario'];
$id_especifico = obtener_id_especifico($usuario);

$eventos = [];
$sql = "SELECT id_evento, titulo, fecha FROM evento WHERE fecha>= CURDATE() ORDER BY fecha";
$result_eventos = $conexion->query($sql);
while ($row = $result_eventos->fetch_assoc()) {
    $eventos[] = $row;
}

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
// Notificaciones para el navbar
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
    <title>Solicitar Constancia</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="../Css/interfaz_usuario.css"></link>
    <style>
        /* Estilos mínimos (puedes adaptarlos) */
        body { max-width: 600px; margin: 2rem auto; padding: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: bold; margin-bottom: 0.3rem; }
        input, select { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #003366; color: white; padding: 0.7rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; }
        .campo-rol { display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 5px; }
        .mensaje { margin-top: 1rem; padding: 1rem; border-radius: 4px; }
        .exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
                            <!-- EMPRESA: enlace a Patrocinar -->
                            <li class="nav-item"><a class="nav-link" href="patrocinar_proyectos.php"><i class="fas fa-hand-holding-usd me-2"></i>Patrocinar</a></li>
                    <?php endif; ?>
                    <?php if (es_alumno() || es_docente()): ?>
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
                                        <a href="../notificaciones/marcar_todas.php" class="text-decoration-none small me-2">Marcar todas</a>
                                    <?php endif; ?>
                                    <a href="../notificaciones/ver_todas.php" class="text-decoration-none small">Ver todas</a>
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
                            <?php // Después de contar revisiones de docente, añadir:
                                if (esta_logeado()) {
                                    $stmt_notif = $conexion->prepare("SELECT COUNT(DISTINCT a.id_articulo) 
                                        FROM articulo a 
                                        JOIN revision_detalles rd ON a.id_articulo = rd.id_articulo 
                                        WHERE a.id_usuario = ? AND a.estado = 'rechazado'");
                                    $stmt_notif->bind_param("i", $_SESSION['id_usuario']);
                                    $stmt_notif->execute();
                                    $notif_count = $stmt_notif->get_result()->fetch_row()[0];
                                    if ($notif_count > 0) {
                                        echo '<span class="badge bg-danger rounded-pill ms-1">' . $notif_count . '</span>';
                                    }
                                } 
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                            <?php if (es_alumno() || es_docente()): ?>
                            <li><a class="dropdown-item" href="../mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos
                                    <?php // Después de contar revisiones de docente, añadir:
                                        if (esta_logeado()) {
                                            $stmt_notif = $conexion->prepare("SELECT COUNT(DISTINCT a.id_articulo) 
                                                FROM articulo a 
                                                JOIN revision_detalles rd ON a.id_articulo = rd.id_articulo 
                                                WHERE a.id_usuario = ? AND a.estado = 'rechazado'");
                                            $stmt_notif->bind_param("i", $_SESSION['id_usuario']);
                                            $stmt_notif->execute();
                                            $notif_count = $stmt_notif->get_result()->fetch_row()[0];
                                            if ($notif_count > 0) {
                                                echo '<span class="badge bg-danger rounded-pill ms-1">' . $notif_count . '</span>';
                                            }
                                        } 
                                    ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (es_docente()): ?>
                                <li class="nav-item">
                                    <a class="dropdown-item" href="../revisiones.php"><i class="fas fa-tasks me-1"></i>Mis revisiones
                                    <?php if ($revisiones_pendientes > 0): ?>
                                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $revisiones_pendientes; ?></span>
                                    <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="../notificaciones/preferencias.php"><i class="fas fa-bell me-2"></i>Preferencias</a></li>
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
    <h2 style="margin-top: 6rem;">Solicitar Constancia de Participación</h2>
    <form id="formSolicitud" method="POST" action="solicitar_constancia.php">
        <div class="form-group">
            <label>Nombre Completo *</label>
            <input type="text" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre']. " " .$usuario['apellidos']); ?>" required>
        </div>
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>
        </div>
        <div class="form-group">
            <label>Evento en el que Participo</label>
            <select name="evento" required>
                <option value="">Seleccione</option>
                <?php foreach($eventos as $ev): ?>
                <option value="<?php echo $ev['titulo'] ?>">
                    <?php echo htmlspecialchars($ev['titulo'] . ' (' . date('d/m/Y', strtotime($ev['fecha'])). ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!--<div id="campoPonente" class="campo-rol">
            <div class="form-group">
                <label>Nombre de la conferencia</label>
                <input type="text" name="nombre_conferencia">
            </div>
        </div>
        <div id="campoStaff" class="campo-rol">
            <div class="form-group">
                <label>Rol desempeñado (Staff)</label>
                <input type="text" name="rol_staff">
            </div>
        </div>-->

        <div class="form-group">
            <label>Fecha de participación *</label>
            <input type="date" name="fecha_participacion" required>
        </div>

        <button type="submit">Enviar Solicitud</button>
    </form>
    <div id="mensaje"></div>

    <script>
        const rolSelect = document.querySelector('select[name="rol"]');
        const campoPonente = document.getElementById('campoPonente');
        const campoStaff = document.getElementById('campoStaff');

        rolSelect.addEventListener('change', function() {
            campoPonente.style.display = 'none';
            campoStaff.style.display = 'none';
            if (this.value === 'ponente') campoPonente.style.display = 'block';
            if (this.value === 'staff') campoStaff.style.display = 'block';
        });

        document.getElementById('formSolicitud').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const response = await fetch('solicitar_constancia.php', { method: 'POST', body: formData });
            const data = await response.json();
            const msgDiv = document.getElementById('mensaje');
            msgDiv.innerHTML = `<div class="mensaje ${data.success ? 'exito' : 'error'}">${data.message}</div>`;
            if (data.success) this.reset();
            setTimeout(() => msgDiv.innerHTML = '', 5000);
        });
    </script>

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