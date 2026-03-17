<?php
session_start();
include 'conexion.php';

// Verificar si la conexión es con PDO o mysqli
if (!isset($pdo) && isset($conexion)) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del usuario logueado (si existe)
$usuario_actual = null;
$id_usuario_actual = null;
if (isset($_SESSION['id_usuario'])) {
    try {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, tipo_usuario FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['id_usuario']]);
        $usuario_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_usuario_actual = $_SESSION['id_usuario'];
    } catch (PDOException $e) {
        error_log("Error al obtener usuario actual: " . $e->getMessage());
    }
}

// Obtener todas las ponencias con sus horarios e imágenes
try {
    $sql = "
        SELECT 
            a.id_articulo,
            a.titulo,
            a.resumen,
            a.categoria,
            a.tipo_trabajo,
            a.fecha_registro,
            h.fecha as horario_fecha,
            h.hora_inicio,
            h.hora_fin,
            h.salon,
            (
                SELECT COUNT(*) 
                FROM proyecto_imagen pi 
                WHERE pi.id_proyecto = a.id_articulo
            ) as total_imagenes,
            (
                SELECT nombre_archivo 
                FROM proyecto_imagen pi 
                WHERE pi.id_proyecto = a.id_articulo AND pi.es_principal = 1 
                LIMIT 1
            ) as imagen_principal
        FROM articulo a
        LEFT JOIN horario_ponencia h ON a.id_articulo = h.id_proyecto
        WHERE a.tipo_trabajo = 'ponencia'
        ORDER BY h.fecha ASC, h.hora_inicio ASC
    ";
    
    $stmt = $pdo->query($sql);
    $ponencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada ponencia, obtener sus datos específicos
    foreach ($ponencias as $key => $ponencia) {
        // Obtener todas las imágenes
        $stmt = $pdo->prepare("
            SELECT id_imagen, nombre_archivo, es_principal 
            FROM proyecto_imagen 
            WHERE id_proyecto = ? 
            ORDER BY es_principal DESC, fecha_subida ASC
        ");
        $stmt->execute([$ponencia['id_articulo']]);
        $ponencias[$key]['imagenes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener autores principales (alumnos con rol autor)
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'alumno' as tipo, 'autor' as rol, a.matricula, a.carrera
            FROM proyecto_alumno pa
            JOIN alumno a ON pa.id_alumno = a.id_alumno
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE pa.id_proyecto = ? AND pa.rol = 'autor'
        ");
        $stmt->execute([$ponencia['id_articulo']]);
        $autores_alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener autores principales (docentes)
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'docente' as tipo, 'autor' as rol, d.especialidad, d.grado_academico
            FROM proyecto_docente pd
            JOIN docente d ON pd.id_docente = d.id_docente
            JOIN usuario u ON d.id_usuario = u.id_usuario
            WHERE pd.id_proyecto = ?
        ");
        $stmt->execute([$ponencia['id_articulo']]);
        $autores_docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar si hay algún usuario de tipo empresa asociado a este artículo
        // Nota: Como no hay id_usuario en articulo, buscamos si existe algún registro en proyecto_alumno o proyecto_docente
        // que corresponda a un usuario de tipo empresa
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'empresa' as tipo, 'autor' as rol, e.nombre_empresa
            FROM usuario u
            JOIN empresa e ON u.id_usuario = e.id_usuario
            WHERE u.id_usuario IN (
                SELECT id_usuario FROM alumno WHERE id_alumno IN (
                    SELECT id_alumno FROM proyecto_alumno WHERE id_proyecto = ?
                )
                UNION
                SELECT id_usuario FROM docente WHERE id_docente IN (
                    SELECT id_docente FROM proyecto_docente WHERE id_proyecto = ?
                )
            ) AND u.tipo_usuario = 'empresa'
        ");
        $stmt->execute([$ponencia['id_articulo'], $ponencia['id_articulo']]);
        $autores_empresa = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener coautores internos (alumnos con rol coautor)
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'alumno' as tipo, 'coautor' as rol, a.matricula, a.carrera
            FROM proyecto_alumno pa
            JOIN alumno a ON pa.id_alumno = a.id_alumno
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE pa.id_proyecto = ? AND pa.rol = 'coautor'
        ");
        $stmt->execute([$ponencia['id_articulo']]);
        $coautores_internos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener coautores externos
        $stmt = $pdo->prepare("
            SELECT ce.id_coautor, ce.nombre, ce.email, ce.institucion, 'externo' as tipo
            FROM coautor_externo ce
            WHERE ce.id_proyecto = ?
            ORDER BY ce.nombre
        ");
        $stmt->execute([$ponencia['id_articulo']]);
        $coautores_externos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar todos los autores principales
        $autores_principales = array_merge($autores_alumnos, $autores_docentes, $autores_empresa);
        
        // Si no se encontraron autores principales, intentar obtener cualquier participante como autor
        if (empty($autores_principales)) {
            // Intentar obtener alumnos como autores (sin importar rol)
            $stmt = $pdo->prepare("
                SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'alumno' as tipo, 'autor' as rol
                FROM proyecto_alumno pa
                JOIN alumno a ON pa.id_alumno = a.id_alumno
                JOIN usuario u ON a.id_usuario = u.id_usuario
                WHERE pa.id_proyecto = ? 
                LIMIT 1
            ");
            $stmt->execute([$ponencia['id_articulo']]);
            $alumno_autor = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($alumno_autor)) {
                $autores_principales = $alumno_autor;
            } else {
                // Intentar obtener docentes
                $stmt = $pdo->prepare("
                    SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, 'docente' as tipo, 'autor' as rol
                    FROM proyecto_docente pd
                    JOIN docente d ON pd.id_docente = d.id_docente
                    JOIN usuario u ON d.id_usuario = u.id_usuario
                    WHERE pd.id_proyecto = ? 
                    LIMIT 1
                ");
                $stmt->execute([$ponencia['id_articulo']]);
                $docente_autor = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $autores_principales = $docente_autor;
            }
        }
        
        $ponencias[$key]['autores_principales'] = $autores_principales;
        $ponencias[$key]['coautores_internos'] = $coautores_internos;
        $ponencias[$key]['coautores_externos'] = $coautores_externos;
        
        // Verificar si el usuario actual es autor
        $ponencias[$key]['es_autor'] = false;
        if ($id_usuario_actual) {
            foreach ($autores_principales as $autor) {
                if (isset($autor['id_usuario']) && $autor['id_usuario'] == $id_usuario_actual) {
                    $ponencias[$key]['es_autor'] = true;
                    break;
                }
            }
            
            // Si no es autor principal, verificar si es coautor interno
            if (!$ponencias[$key]['es_autor']) {
                foreach ($coautores_internos as $coautor) {
                    if (isset($coautor['id_usuario']) && $coautor['id_usuario'] == $id_usuario_actual) {
                        $ponencias[$key]['es_autor'] = true; // También puede ver detalles si es coautor
                        break;
                    }
                }
            }
        }
    }
    
} catch (PDOException $e) {
    $mensaje = "Error al cargar las ponencias: " . $e->getMessage();
    $tipo_mensaje = "danger";
    error_log("Error en ponencias.php: " . $e->getMessage());
    $ponencias = [];
}

// Agrupar ponencias por fecha
$ponencias_por_fecha = [];
foreach ($ponencias as $ponencia) {
    if (!empty($ponencia['horario_fecha'])) {
        $fecha = $ponencia['horario_fecha'];
        if (!isset($ponencias_por_fecha[$fecha])) {
            $ponencias_por_fecha[$fecha] = [];
        }
        $ponencias_por_fecha[$fecha][] = $ponencia;
    } else {
        // Ponencias sin horario asignado
        if (!isset($ponencias_por_fecha['sin_asignar'])) {
            $ponencias_por_fecha['sin_asignar'] = [];
        }
        $ponencias_por_fecha['sin_asignar'][] = $ponencia;
    }
}

// Ordenar fechas
ksort($ponencias_por_fecha);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Lightbox para imágenes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <!-- Swiper CSS para carrusel -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="estilo1.css">
    <title>Ponencias - SIMPOSIO FESC C4</title>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        #mainNav {
            background-color: #293e6b !important;
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand { 
            color: white !important; 
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .nav-link { 
            color: white !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover { 
            color: #ffd700 !important;
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: #ffd700 !important;
            font-weight: 600;
        }
        
        .ponencias-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .ponencias-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .ponencias-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .ponencias-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .fecha-seccion {
            margin-bottom: 40px;
        }
        
        .fecha-titulo {
            background: white;
            padding: 15px 25px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .fecha-titulo h3 {
            margin: 0;
            color: #293e6b;
            font-weight: 600;
        }
        
        .fecha-titulo i {
            color: #D59F0F;
            margin-right: 10px;
        }
        
        .ponencia-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .ponencia-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .ponencia-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .ponencia-titulo {
            font-size: 1.3rem;
            font-weight: 600;
            margin-right: 100px;
        }
        
        .ponencia-categoria {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #D59F0F;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .ponencia-body {
            padding: 20px;
        }
        
        /* Carrusel de imágenes */
        .swiper-container {
            width: 100%;
            height: 300px;
            margin-bottom: 20px;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .swiper-slide {
            text-align: center;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .swiper-pagination-bullet-active {
            background: #293e6b !important;
        }
        
        .swiper-button-next,
        .swiper-button-prev {
            color: white !important;
            background: rgba(41, 62, 107, 0.7);
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 20px;
        }
        
        .swiper-button-next:hover,
        .swiper-button-prev:hover {
            background: #293e6b;
        }
        
        .no-imagenes {
            height: 300px;
            background: #f8f9fa;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
            margin-bottom: 20px;
        }
        
        .no-imagenes i {
            font-size: 4rem;
            margin-bottom: 10px;
            color: #dee2e6;
        }
        
        /* Información de horario */
        .horario-info {
            background: #e8f5e9;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .horario-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .horario-item i {
            color: #28a745;
            font-size: 1.2rem;
        }
        
        .horario-item span {
            font-weight: 500;
        }
        
        .horario-hora {
            background: white;
            padding: 5px 15px;
            border-radius: 20px;
            border: 1px solid #17a2b8;
        }
        
        .horario-salon {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        /* Autores */
        .autores-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .autores-titulo {
            font-weight: 600;
            color: #293e6b;
            margin-bottom: 15px;
            font-size: 1.1rem;
            border-bottom: 2px solid #293e6b;
            padding-bottom: 8px;
        }
        
        .autor-principal {
            background: linear-gradient(135deg, #fff9e6, #fff3d6);
            border-left: 4px solid #D59F0F;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .autor-nombre {
            font-weight: 600;
            color: #293e6b;
            font-size: 1.1rem;
        }
        
        .badge-autor-principal {
            background: #D59F0F;
            color: white;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-tipo-autor {
            background: #6c757d;
            color: white;
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 15px;
            margin-left: 8px;
        }
        
        .autor-badge {
            display: inline-block;
            background: white;
            border: 1px solid #dee2e6;
            padding: 8px 15px;
            border-radius: 25px;
            margin: 0 5px 8px 0;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        
        .autor-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .autor-badge i {
            margin-right: 5px;
        }
        
        .autor-badge.alumno {
            border-left: 4px solid #17a2b8;
        }
        
        .autor-badge.alumno i {
            color: #17a2b8;
        }
        
        .autor-badge.docente {
            border-left: 4px solid #28a745;
        }
        
        .autor-badge.docente i {
            color: #28a745;
        }
        
        .autor-badge.externo {
            border-left: 4px solid #6c757d;
            background: #f8f9fa;
        }
        
        .autor-badge.externo i {
            color: #6c757d;
        }
        
        .autor-badge small {
            display: block;
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 3px;
            font-style: italic;
        }
        
        .coautores-seccion {
            margin-top: 15px;
        }
        
        .coautores-seccion h6 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.95rem;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        
        /* Resumen */
        .resumen {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #e9ecef;
            margin-top: 15px;
            max-height: 100px;
            overflow: hidden;
            position: relative;
        }
        
        .resumen p {
            margin: 0;
            color: #495057;
            line-height: 1.6;
        }
        
        .resumen.expandido {
            max-height: none;
        }
        
        .ver-mas {
            color: #293e6b;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }
        
        .ver-mas:hover {
            text-decoration: underline;
        }
        
        .btn-ver-detalle {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .btn-ver-detalle:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 62, 107, 0.3);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #293e6b;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .ponencias-header h1 {
                font-size: 2rem;
            }
            
            .swiper-container {
                height: 200px;
            }
            
            .horario-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .ponencia-titulo {
                margin-right: 0;
                margin-bottom: 40px;
            }
            
            .ponencia-categoria {
                top: auto;
                bottom: 20px;
                right: 20px;
            }
            
            .autor-principal {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
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
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="convocatoria.php"><i class="fas fa-scroll me-1"></i>Convocatoria</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="ponencias.php"><i class="fas fa-chalkboard-teacher me-1"></i>Ponencias</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="programa.php"><i class="fas fa-calendar-alt me-1"></i>Programa</a>
                    </li>
                    <?php if(isset($_SESSION['id_usuario'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                                <li><a class="dropdown-item" href="mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos</a></li>
                                <li><a class="dropdown-item" href="registrar_trabajos.php"><i class="fas fa-upload me-2"></i>Subir Trabajo</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="registro.php"><i class="fas fa-user-plus me-1"></i>Registro</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Espaciado para el menú fijo -->
    <div style="height: 76px;"></div>

    <div class="ponencias-container">
        <div class="ponencias-header">
            <h1><i class="fas fa-chalkboard-teacher me-3"></i>Ponencias del Simposio</h1>
            <p>Descubre las investigaciones y trabajos que se presentarán en el Congreso Internacional de Matemáticas</p>
        </div>

        <?php if($mensaje): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
            <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if(empty($ponencias)): ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <h3>No hay ponencias registradas</h3>
            <p>Próximamente se publicarán las ponencias del simposio.</p>
        </div>
        <?php else: ?>
            <!-- Ponencias agrupadas por fecha -->
            <?php foreach($ponencias_por_fecha as $fecha => $ponencias_dia): ?>
                <?php if($fecha != 'sin_asignar'): ?>
                <div class="fecha-seccion">
                    <div class="fecha-titulo">
                        <h3>
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('d \d\e F \d\e Y', strtotime($fecha)); ?>
                        </h3>
                    </div>
                    
                    <?php foreach($ponencias_dia as $ponencia): ?>
                    <div class="ponencia-card">
                        <div class="ponencia-header">
                            <div class="ponencia-titulo"><?php echo htmlspecialchars($ponencia['titulo']); ?></div>
                            <div class="ponencia-categoria">
                                <i class="fas fa-tag me-1"></i>
                                <?php echo htmlspecialchars($ponencia['categoria'] ?? 'Sin categoría'); ?>
                            </div>
                        </div>
                        
                        <div class="ponencia-body">
                            <!-- Carrusel de imágenes -->
                            <?php if(!empty($ponencia['imagenes'])): ?>
                            <div class="swiper-container swiper-<?php echo $ponencia['id_articulo']; ?>">
                                <div class="swiper-wrapper">
                                    <?php foreach($ponencia['imagenes'] as $imagen): ?>
                                    <div class="swiper-slide">
                                        <a href="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" data-lightbox="ponencia-<?php echo $ponencia['id_articulo']; ?>" data-title="<?php echo htmlspecialchars($ponencia['titulo']); ?>">
                                            <img src="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" alt="Imagen de la ponencia">
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Paginación -->
                                <div class="swiper-pagination"></div>
                                <!-- Botones de navegación (solo si hay más de una imagen) -->
                                <?php if(count($ponencia['imagenes']) > 1): ?>
                                <div class="swiper-button-next"></div>
                                <div class="swiper-button-prev"></div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="no-imagenes">
                                <i class="fas fa-image"></i>
                                <p>Sin imágenes disponibles</p>
                            </div>
                            <?php endif; ?>

                            <!-- Información de horario -->
                            <?php if(!empty($ponencia['horario_fecha'])): ?>
                            <div class="horario-info">
                                <div class="horario-item">
                                    <i class="fas fa-clock"></i>
                                    <span class="horario-hora">
                                        <?php echo date('H:i', strtotime($ponencia['hora_inicio'])); ?> - 
                                        <?php echo date('H:i', strtotime($ponencia['hora_fin'])); ?>
                                    </span>
                                </div>
                                <?php if(!empty($ponencia['salon'])): ?>
                                <div class="horario-item">
                                    <i class="fas fa-door-open"></i>
                                    <span class="horario-salon"><?php echo htmlspecialchars($ponencia['salon']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Autores - SECCIÓN CORREGIDA -->
                            <div class="autores-info">
                                <div class="autores-titulo">
                                    <i class="fas fa-users me-2"></i>Autores y Coautores
                                </div>
                                
                                <!-- Autores Principales -->
                                <?php if(!empty($ponencia['autores_principales'])): ?>
                                    <?php foreach($ponencia['autores_principales'] as $autor): ?>
                                    <div class="autor-principal">
                                        <span class="autor-nombre">
                                            <i class="fas fa-crown me-1" style="color: #D59F0F;"></i>
                                            <?php echo htmlspecialchars($autor['nombre'] . ' ' . ($autor['apellidos'] ?? '')); ?>
                                        </span>
                                        <span class="badge-autor-principal">
                                            <?php 
                                            if ($autor['tipo'] == 'alumno') echo 'ALUMNO';
                                            elseif ($autor['tipo'] == 'docente') echo 'DOCENTE';
                                            elseif ($autor['tipo'] == 'empresa') echo 'EMPRESA';
                                            ?>
                                        </span>
                                        <?php if(!empty($autor['matricula'])): ?>
                                            <small class="text-muted ms-2">Matrícula: <?php echo $autor['matricula']; ?></small>
                                        <?php endif; ?>
                                        <?php if(!empty($autor['grado_academico'])): ?>
                                            <small class="text-muted ms-2"><?php echo $autor['grado_academico']; ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <!-- Coautores Internos -->
                                <?php if(!empty($ponencia['coautores_internos'])): ?>
                                <div class="coautores-seccion">
                                    <h6><i class="fas fa-user-friends me-2"></i>Coautores Internos:</h6>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach($ponencia['coautores_internos'] as $coautor): ?>
                                        <span class="autor-badge <?php echo $coautor['tipo']; ?>">
                                            <i class="fas fa-user-graduate"></i>
                                            <?php echo htmlspecialchars($coautor['nombre'] . ' ' . ($coautor['apellidos'] ?? '')); ?>
                                            <?php if(!empty($coautor['matricula'])): ?>
                                            <small>Matrícula: <?php echo $coautor['matricula']; ?></small>
                                            <?php endif; ?>
                                            <?php if(!empty($coautor['carrera'])): ?>
                                            <small><?php echo $coautor['carrera']; ?></small>
                                            <?php endif; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Coautores Externos -->
                                <?php if(!empty($ponencia['coautores_externos'])): ?>
                                <div class="coautores-seccion">
                                    <h6><i class="fas fa-user-tie me-2"></i>Coautores Externos:</h6>
                                    <div class="d-flex flex-wrap">
                                        <?php foreach($ponencia['coautores_externos'] as $externo): ?>
                                        <span class="autor-badge externo">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($externo['nombre']); ?>
                                            <?php if(!empty($externo['institucion'])): ?>
                                            <small><?php echo htmlspecialchars($externo['institucion']); ?></small>
                                            <?php endif; ?>
                                            <?php if(!empty($externo['email'])): ?>
                                            <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($externo['email']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Mensaje si no hay autores -->
                                <?php if(empty($ponencia['autores_principales']) && empty($ponencia['coautores_internos']) && empty($ponencia['coautores_externos'])): ?>
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay información de autores disponible
                                </p>
                                <?php endif; ?>
                            </div>

                            <!-- Resumen -->
                            <?php if(!empty($ponencia['resumen'])): ?>
                            <div class="resumen" id="resumen-<?php echo $ponencia['id_articulo']; ?>">
                                <p><?php echo nl2br(htmlspecialchars($ponencia['resumen'])); ?></p>
                            </div>
                            <?php if(strlen($ponencia['resumen']) > 300): ?>
                            <a class="ver-mas" onclick="toggleResumen(<?php echo $ponencia['id_articulo']; ?>)">
                                <i class="fas fa-chevron-down me-1"></i>Ver más
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>

                            <!-- Botón ver detalle (solo para el autor) -->
                            <?php if($ponencia['es_autor']): ?>
                            <div class="text-end mt-3">
                                <a href="ver_proyecto.php?id=<?php echo $ponencia['id_articulo']; ?>" class="btn-ver-detalle">
                                    <i class="fas fa-eye me-2"></i>Ver detalles completos
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <!-- Ponencias sin horario asignado -->
            <?php if(isset($ponencias_por_fecha['sin_asignar'])): ?>
            <div class="fecha-seccion">
                <div class="fecha-titulo" style="background: #f8f9fa;">
                    <h3>
                        <i class="fas fa-clock"></i>
                        Ponencias por asignar horario
                    </h3>
                </div>
                
                <?php foreach($ponencias_por_fecha['sin_asignar'] as $ponencia): ?>
                <div class="ponencia-card">
                    <div class="ponencia-header">
                        <div class="ponencia-titulo"><?php echo htmlspecialchars($ponencia['titulo']); ?></div>
                        <div class="ponencia-categoria">
                            <i class="fas fa-tag me-1"></i>
                            <?php echo htmlspecialchars($ponencia['categoria'] ?? 'Sin categoría'); ?>
                        </div>
                    </div>
                    
                    <div class="ponencia-body">
                        <!-- Carrusel de imágenes -->
                        <?php if(!empty($ponencia['imagenes'])): ?>
                        <div class="swiper-container swiper-<?php echo $ponencia['id_articulo']; ?>">
                            <div class="swiper-wrapper">
                                <?php foreach($ponencia['imagenes'] as $imagen): ?>
                                <div class="swiper-slide">
                                    <a href="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" data-lightbox="ponencia-<?php echo $ponencia['id_articulo']; ?>" data-title="<?php echo htmlspecialchars($ponencia['titulo']); ?>">
                                        <img src="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" alt="Imagen de la ponencia">
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                            <?php if(count($ponencia['imagenes']) > 1): ?>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-imagenes">
                            <i class="fas fa-image"></i>
                            <p>Sin imágenes disponibles</p>
                        </div>
                        <?php endif; ?>

                        <!-- Autores - SECCIÓN CORREGIDA (para sin asignar) -->
                        <div class="autores-info">
                            <div class="autores-titulo">
                                <i class="fas fa-users me-2"></i>Autores y Coautores
                            </div>
                            
                            <!-- Autores Principales -->
                            <?php if(!empty($ponencia['autores_principales'])): ?>
                                <?php foreach($ponencia['autores_principales'] as $autor): ?>
                                <div class="autor-principal">
                                    <span class="autor-nombre">
                                        <i class="fas fa-crown me-1" style="color: #D59F0F;"></i>
                                        <?php echo htmlspecialchars($autor['nombre'] . ' ' . ($autor['apellidos'] ?? '')); ?>
                                    </span>
                                    <span class="badge-autor-principal">
                                        <?php 
                                        if ($autor['tipo'] == 'alumno') echo 'ALUMNO';
                                        elseif ($autor['tipo'] == 'docente') echo 'DOCENTE';
                                        elseif ($autor['tipo'] == 'empresa') echo 'EMPRESA';
                                        ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Coautores Internos -->
                            <?php if(!empty($ponencia['coautores_internos'])): ?>
                            <div class="coautores-seccion">
                                <h6><i class="fas fa-user-friends me-2"></i>Coautores Internos:</h6>
                                <div class="d-flex flex-wrap">
                                    <?php foreach($ponencia['coautores_internos'] as $coautor): ?>
                                    <span class="autor-badge <?php echo $coautor['tipo']; ?>">
                                        <i class="fas fa-user-graduate"></i>
                                        <?php echo htmlspecialchars($coautor['nombre'] . ' ' . ($coautor['apellidos'] ?? '')); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Coautores Externos -->
                            <?php if(!empty($ponencia['coautores_externos'])): ?>
                            <div class="coautores-seccion">
                                <h6><i class="fas fa-user-tie me-2"></i>Coautores Externos:</h6>
                                <div class="d-flex flex-wrap">
                                    <?php foreach($ponencia['coautores_externos'] as $externo): ?>
                                    <span class="autor-badge externo">
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($externo['nombre']); ?>
                                        <?php if(!empty($externo['institucion'])): ?>
                                        <small><?php echo htmlspecialchars($externo['institucion']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Resumen -->
                        <?php if(!empty($ponencia['resumen'])): ?>
                        <div class="resumen" id="resumen-<?php echo $ponencia['id_articulo']; ?>">
                            <p><?php echo nl2br(htmlspecialchars($ponencia['resumen'])); ?></p>
                        </div>
                        <?php if(strlen($ponencia['resumen']) > 300): ?>
                        <a class="ver-mas" onclick="toggleResumen(<?php echo $ponencia['id_articulo']; ?>)">
                            <i class="fas fa-chevron-down me-1"></i>Ver más
                        </a>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Botón ver detalle (solo para el autor) -->
                        <?php if($ponencia['es_autor']): ?>
                        <div class="text-end mt-3">
                            <a href="ver_proyecto.php?id=<?php echo $ponencia['id_articulo']; ?>" class="btn-ver-detalle">
                                <i class="fas fa-eye me-2"></i>Ver detalles completos
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="colorazul text-white mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">
                        <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
                    </h5>
                    <p class="text-white-50">Congreso Internacional sobre la Enseñanza y Aplicación de las Matemáticas</p>
                    <p class="text-white-50">
                        <i class="fas fa-map-marker-alt me-2"></i>FES Cuautitlán, UNAM
                    </p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">
                        <i class="fas fa-address-card me-2"></i>Contacto
                    </h5>
                    <p class="text-white-50">
                        <i class="fas fa-envelope me-2"></i>info@simposiofesc.com
                    </p>
                    <p class="text-white-50">
                        <i class="fas fa-phone me-2"></i>(55) 1234-5678
                    </p>
                    <p class="text-white-50">
                        <i class="fas fa-clock me-2"></i>Lun-Vie: 9:00 - 18:00
                    </p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">
                        <i class="fas fa-share-alt me-2"></i>Síguenos
                    </h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white fs-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white fs-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white fs-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white fs-3"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <hr class="border-white-50">
            <div class="text-center">
                <p class="mb-0 text-white-50">
                    <i class="far fa-copyright me-2"></i>
                    <?php echo date('Y'); ?> Congreso Internacional de Matemáticas. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configuración de Lightbox
            lightbox.option({
                'resizeDuration': 200,
                'wrapAround': true,
                'albumLabel': 'Imagen %1 de %2',
                'fadeDuration': 300
            });

            // Inicializar Swiper para cada ponencia con imágenes
            <?php foreach($ponencias as $ponencia): ?>
            <?php if(!empty($ponencia['imagenes'])): ?>
            new Swiper('.swiper-<?php echo $ponencia['id_articulo']; ?>', {
                loop: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
            });
            <?php endif; ?>
            <?php endforeach; ?>
        });

        // Función para expandir/contraer resumen
        function toggleResumen(id) {
            const resumen = document.getElementById('resumen-' + id);
            const btn = event.target;
            
            if (resumen.classList.contains('expandido')) {
                resumen.classList.remove('expandido');
                btn.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Ver más';
            } else {
                resumen.classList.add('expandido');
                btn.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Ver menos';
            }
        }
    </script>
</body>
</html>