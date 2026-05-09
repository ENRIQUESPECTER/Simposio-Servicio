<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';
require_once 'includes/notificaciones.php';

$mensaje = '';
$tipo_mensaje = '';

// Obtener artículos aprobados que tengan actividad asignada
$sql = "
    SELECT 
        a.id_articulo,
        a.titulo,
        a.resumen,
        a.tipo_trabajo,
        a.categoria,
        a.estado,
        ae.id_actividad,
        ae.fecha,
        ae.hora_inicio,
        ae.hora_fin,
        ae.descripcion,
        ae.referencias,
        ae.archivo_pdf,
        s.nombre as salon_nombre,
        e.titulo as nombre_evento
    FROM articulo a
    INNER JOIN actividad_evento ae ON a.id_articulo = ae.id_articulo
    LEFT JOIN salones s ON ae.id_salon = s.id_salon
    INNER JOIN evento e ON a.id_evento = e.id_evento
    WHERE a.estado = 'aprobado' AND ae.visible = 1
    ORDER BY ae.fecha, ae.hora_inicio
";

$result = $conexion->query($sql);
if (!$result) {
    die("Error en la consulta principal: " . $conexion->error);
}

$articulos = [];
while ($row = $result->fetch_assoc()) {
    $articulos[] = $row;
}

// Para cada artículo, obtener autores, coautores e imágenes
foreach ($articulos as $key => $art) {
    $id = $art['id_articulo'];

    // Autores principales (alumnos con rol autor, docentes, empresa)
    $autores = [];
    // Alumnos autores
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
               a.matricula, a.carrera, NULL as especialidad, NULL as grado_academico, NULL as nombre_empresa
        FROM articulo_alumno aa
        JOIN alumno a ON aa.id_alumno = a.id_alumno
        JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE aa.id_articulo = ? AND aa.rol = 'autor'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_autores = $stmt->get_result();
    while ($row = $result_autores->fetch_assoc()) {
        $autores[] = $row;
    }

    // Docentes autores
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
               NULL as matricula, NULL as carrera, d.especialidad, d.grado_academico, NULL as nombre_empresa
        FROM articulo_docente ad
        JOIN docente d ON ad.id_docente = d.id_docente
        JOIN usuario u ON d.id_usuario = u.id_usuario
        WHERE ad.id_articulo = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_autores = $stmt->get_result();
    while ($row = $result_autores->fetch_assoc()) {
        $autores[] = $row;
    }

    // Empresa (si existe como autor)
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
               NULL as matricula, NULL as carrera, NULL as especialidad, NULL as grado_academico, e.nombre_empresa
        FROM articulo a
        LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
        LEFT JOIN empresa e ON u.id_usuario = e.id_usuario
        WHERE a.id_articulo = ? AND u.tipo_usuario = 'empresa'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_autores = $stmt->get_result();
    while ($row = $result_autores->fetch_assoc()) {
        $autores[] = $row;
    }

    $articulos[$key]['autores'] = $autores;

    // Coautores internos (alumnos con rol coautor)
    $coautores_internos = [];
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.apellidos, u.tipo_usuario,
               a.matricula, a.carrera
        FROM articulo_alumno aa
        JOIN alumno a ON aa.id_alumno = a.id_alumno
        JOIN usuario u ON a.id_usuario = u.id_usuario
        WHERE aa.id_articulo = ? AND aa.rol = 'coautor'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_coint = $stmt->get_result();
    while ($row = $result_coint->fetch_assoc()) {
        $coautores_internos[] = $row;
    }
    $articulos[$key]['coautores_internos'] = $coautores_internos;

    // Coautores externos
    $coautores_externos = [];
    $stmt = $conexion->prepare("SELECT * FROM coautor_externo WHERE id_articulo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_coext = $stmt->get_result();
    while ($row = $result_coext->fetch_assoc()) {
        $coautores_externos[] = $row;
    }
    $articulos[$key]['coautores_externos'] = $coautores_externos;

    // Imágenes
    $imagenes = [];
    $stmt = $conexion->prepare("SELECT * FROM proyecto_imagen WHERE id_articulo = ? ORDER BY es_principal DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result_img = $stmt->get_result();
    while ($row = $result_img->fetch_assoc()) {
        $imagenes[] = $row;
    }
    $articulos[$key]['imagenes'] = $imagenes;

    // Verificar si el usuario actual es autor (para mostrar botón de detalles)
    $es_autor = false;
    if (esta_logeado()) {
        foreach ($autores as $autor) {
            if ($autor['id_usuario'] == $_SESSION['id_usuario']) {
                $es_autor = true;
                break;
            }
        }
    }
    $articulos[$key]['es_autor'] = $es_autor;
}

// Agrupar por fecha para mostrarlos ordenados
$articulos_por_fecha = [];
foreach ($articulos as $art) {
    $fecha = $art['fecha'];
    if (!isset($articulos_por_fecha[$fecha])) {
        $articulos_por_fecha[$fecha] = [];
    }
    $articulos_por_fecha[$fecha][] = $art;
}
ksort($articulos_por_fecha);

// Colores para tipos
$colores_tipo = [
    'cartel'    => ['bg' => '#ffc107', 'icon' => 'fa-image'],
    'ponencia'  => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher'],
    'taller'    => ['bg' => '#28a745', 'icon' => 'fa-tools'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube']
];

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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <title>Ponencias - SIMPOSIO</title>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .ponencias-container { max-width: 1200px; margin: 100px auto; padding: 0 20px; }
        .ponencias-header { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; padding: 40px; border-radius: 20px; margin-bottom: 30px; text-align: center; }
        .fecha-titulo { background: white; padding: 15px 25px; border-radius: 50px; display: inline-block; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .fecha-titulo h3 { margin: 0; color: #293e6b; }
        .articulo-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 30px; transition: 0.3s; }
        .articulo-card:hover { transform: translateY(-5px); }
        .articulo-header { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; padding: 20px; position: relative; }
        .articulo-titulo { font-size: 1.3rem; font-weight: 600; margin-right: 100px; }
        .tipo-badge { position: absolute; top: 20px; right: 20px; padding: 5px 15px; border-radius: 20px; color: white; font-weight: 500; }
        .horario-info { background: #e8f5e9; border-radius: 15px; padding: 15px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; }
        .swiper-container { width: 100%; height: 300px; margin-bottom: 20px; border-radius: 15px; overflow: hidden; }
        .swiper-slide img { width: 100%; height: 100%; object-fit: cover; }
        .no-imagenes { height: 300px; background: #f8f9fa; border-radius: 15px; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #6c757d; border: 2px dashed #dee2e6; }
        .autores-info { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 15px; }
        .autores-titulo { font-weight: 600; color: #293e6b; border-bottom: 2px solid #293e6b; padding-bottom: 8px; margin-bottom: 15px; }
        .autor-principal { background: #fff3d6; border-left: 4px solid #D59F0F; padding: 12px; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .autor-badge { display: inline-block; background: white; border: 1px solid #dee2e6; padding: 8px 15px; border-radius: 25px; margin: 0 5px 8px 0; font-size: 0.9rem; }
        .autor-badge.alumno { border-left: 4px solid #17a2b8; }
        .autor-badge.docente { border-left: 4px solid #28a745; }
        .autor-badge.externo { border-left: 4px solid #6c757d; background: #f8f9fa; }
        .resumen { background: white; border-radius: 10px; padding: 15px; border: 1px solid #e9ecef; margin-top: 15px; max-height: 100px; overflow: hidden; position: relative; }
        .resumen.expandido { max-height: none; }
        .ver-mas { color: #293e6b; cursor: pointer; font-weight: 600; margin-top: 10px; display: inline-block; }
        .btn-ver-detalle { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; border: none; padding: 8px 20px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; }
        .icons-fondo {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
        }
    </style>
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

    <div class="ponencias-container">
        <div class="ponencias-header">
            <h1><i class="fas fa-chalkboard-teacher me-3"></i>Ponencias y Trabajos</h1>
            <p>Trabajos aprobados con horario asignado</p>
        </div>

        <?php if (empty($articulos)): ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher fa-5x text-muted"></i>
            <h3>No hay trabajos aprobados con horario</h3>
            <p>Próximamente se publicarán los trabajos del simposio.</p>
        </div>
        <?php else: ?>
            <?php foreach ($articulos_por_fecha as $fecha => $lista): ?>
            <div class="fecha-seccion">
                <div class="fecha-titulo">
                    <h3><i class="fas fa-calendar-alt me-2"></i><?php echo date('d \d\e F \d\e Y', strtotime($fecha)); ?></h3>
                </div>
                <?php foreach ($lista as $art): 
                    $tipo = strtolower($art['tipo_trabajo']);
                    $color = $colores_tipo[$tipo] ?? ['bg' => '#6c757d', 'icon' => 'fa-file'];
                ?>
                <div class="articulo-card">
                    <div class="articulo-header">
                        <div class="articulo-titulo"><?php echo htmlspecialchars($art['titulo']); ?></div>
                        <div class="tipo-badge" style="background: <?php echo $color['bg']; ?>">
                            <i class="fas <?php echo $color['icon']; ?> me-1"></i>
                            <?php echo ucfirst($tipo); ?>
                        </div>
                    </div>
                    <div class="p-4">
                        <!-- Carrusel de imágenes -->
                        <?php if (!empty($art['imagenes'])): ?>
                        <div class="swiper-container swiper-<?php echo $art['id_articulo']; ?>">
                            <div class="swiper-wrapper">
                                <?php foreach ($art['imagenes'] as $img): ?>
                                <div class="swiper-slide">
                                    <a href="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" data-lightbox="articulo-<?php echo $art['id_articulo']; ?>">
                                        <img src="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" alt="Imagen">
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                            <?php if (count($art['imagenes']) > 1): ?>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-imagenes">
                            <i class="fas fa-image fa-4x"></i>
                            <p>Sin imágenes disponibles</p>
                        </div>
                        <?php endif; ?>

                        <!-- Horario y salón -->
                        <div class="horario-info">
                            <div><i class="fas fa-clock me-2 icons-fondo"></i><?php echo substr($art['hora_inicio'],0,5); ?> - <?php echo substr($art['hora_fin'],0,5); ?></div>
                            <?php if (!empty($art['salon_nombre'])): ?>
                            <div><i class="fas fa-door-open me-2 icons-fondo"></i><?php echo htmlspecialchars($art['salon_nombre']); ?></div>
                            <?php endif; ?>
                            <div><i class="fas fa-calendar-week me-2 icons-fondo"></i><?php echo htmlspecialchars($art['nombre_evento']); ?></div>
                        </div>

                        <!-- Autores y coautores -->
                        <div class="autores-info">
                            <div class="autores-titulo">Autores y Coautores</div>
                            <!-- Autores principales -->
                            <?php if (!empty($art['autores'])): ?>
                                <?php foreach ($art['autores'] as $autor): ?>
                                <div class="autor-principal">
                                    <i class="fas fa-crown me-1" style="color:#D59F0F;"></i>
                                    <span class="fw-bold"><?php echo htmlspecialchars($autor['nombre'] . ' ' . ($autor['apellidos'] ?? '')); ?></span>
                                    <span class="badge bg-secondary"><?php echo ucfirst($autor['tipo_usuario']); ?></span>
                                    <?php if (!empty($autor['matricula'])): ?>
                                        <small class="text-muted">Mat: <?php echo $autor['matricula']; ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($autor['grado_academico'])): ?>
                                        <small><?php echo $autor['grado_academico']; ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Coautores internos -->
                            <?php if (!empty($art['coautores_internos'])): ?>
                            <div class="mt-3">
                                <h6 class="fw-bold">Coautores internos:</h6>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($art['coautores_internos'] as $co): ?>
                                    <span class="autor-badge <?php echo $co['tipo_usuario']; ?>">
                                        <i class="fas fa-user-graduate me-1"></i>
                                        <?php echo htmlspecialchars($co['nombre'] . ' ' . ($co['apellidos'] ?? '')); ?>
                                        <?php if (!empty($co['matricula'])): ?>
                                        <small>Mat: <?php echo $co['matricula']; ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Coautores externos -->
                            <?php if (!empty($art['coautores_externos'])): ?>
                            <div class="mt-3">
                                <h6 class="fw-bold">Coautores externos:</h6>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($art['coautores_externos'] as $ext): ?>
                                    <span class="autor-badge externo">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?php echo htmlspecialchars($ext['nombre']); ?>
                                        <?php if (!empty($ext['institucion'])): ?>
                                        <small><?php echo htmlspecialchars($ext['institucion']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Descripción, referencias y PDF -->
                        <?php if (!empty($art['descripcion'])): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold">Descripción</h6>
                            <p><?php echo nl2br(htmlspecialchars($art['descripcion'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($art['referencias'])): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold">Referencias</h6>
                            <p><?php echo nl2br(htmlspecialchars($art['referencias'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($art['archivo_pdf'])): ?>
                        <div class="mt-3">
                            <a href="<?php echo htmlspecialchars($art['archivo_pdf']); ?>" target="_blank" class="btn btn-info btn-sm">
                                <i class="fas fa-file-pdf me-2"></i>Ver PDF
                            </a>
                        </div>
                        <?php endif; ?>

                        <!-- Resumen expandible -->
                        <?php if (!empty($art['resumen'])): ?>
                        <div class="resumen" id="resumen-<?php echo $art['id_articulo']; ?>">
                            <p><?php echo nl2br(htmlspecialchars($art['resumen'])); ?></p>
                        </div>
                        <?php if (strlen($art['resumen']) > 300): ?>
                        <a class="ver-mas" onclick="toggleResumen(<?php echo $art['id_articulo']; ?>)">
                            <i class="fas fa-chevron-down me-1"></i>Ver más
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Botón para autores -->
                        <?php if ($art['es_autor']): ?>
                        <div class="text-end mt-3">
                            <a href="ver_proyecto.php?id=<?php echo $art['id_articulo']; ?>" class="btn-ver-detalle">
                                <i class="fas fa-eye me-2"></i>Ver detalles completos
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="d-flex justify-content-between">
            <a href="index.php" class="btn-ver-detalle"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        lightbox.option({ 'resizeDuration': 200, 'wrapAround': true, 'albumLabel': 'Imagen %1 de %2' });

        // Inicializar Swiper para cada artículo con imágenes
        <?php foreach ($articulos as $art): ?>
        <?php if (!empty($art['imagenes'])): ?>
        new Swiper('.swiper-<?php echo $art['id_articulo']; ?>', {
            loop: true,
            pagination: { el: '.swiper-pagination', clickable: true },
            navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
            autoplay: { delay: 5000, disableOnInteraction: false }
        });
        <?php endif; ?>
        <?php endforeach; ?>

        // Función para expandir resumen
        window.toggleResumen = function(id) {
            var resumen = document.getElementById('resumen-' + id);
            var btn = event.target;
            if (resumen.classList.contains('expandido')) {
                resumen.classList.remove('expandido');
                btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Ver más';
            } else {
                resumen.classList.add('expandido');
                btn.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Ver menos';
            }
        };
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
    </script>
</body>
</html>