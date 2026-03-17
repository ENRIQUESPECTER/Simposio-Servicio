<?php
session_start();
include 'conexion.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

// Verificar si la conexión es con PDO o mysqli
if (!isset($pdo) && isset($conexion)) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$bd;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

$id_proyecto = $_GET['id'] ?? 0;
$mensaje = '';
$tipo_mensaje = '';

if (!$id_proyecto) {
    header('Location: mis_proyectos.php');
    exit;
}

// Inicializar variables
$proyecto = null;
$participantes_internos = [];
$coautores_externos = [];
$imagenes = [];
$horario_asignado = null;

// Obtener información del proyecto
try {
    // Obtener datos del artículo
    $stmt = $pdo->prepare("SELECT * FROM articulo WHERE id_articulo = ?");
    $stmt->execute([$id_proyecto]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proyecto) {
        header('Location: mis_proyectos.php');
        exit;
    }
    
    // Obtener participantes internos (alumnos y docentes)
    try {
        // Obtener alumnos participantes
        $stmt = $pdo->prepare("
            SELECT u.nombre, u.apellidos, u.correo, pa.rol, a.matricula, a.carrera,
                   'alumno' as tipo_usuario
            FROM proyecto_alumno pa
            JOIN alumno a ON pa.id_alumno = a.id_alumno
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE pa.id_proyecto = ?
            ORDER BY pa.rol DESC, u.nombre
        ");
        $stmt->execute([$id_proyecto]);
        $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener docentes participantes
        $stmt = $pdo->prepare("
            SELECT u.nombre, u.apellidos, u.correo, 'autor' as rol, 
                   d.especialidad, d.grado_academico, 'docente' as tipo_usuario
            FROM proyecto_docente pd
            JOIN docente d ON pd.id_docente = d.id_docente
            JOIN usuario u ON d.id_usuario = u.id_usuario
            WHERE pd.id_proyecto = ?
        ");
        $stmt->execute([$id_proyecto]);
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener empresa si existe
        $stmt = $pdo->prepare("
            SELECT u.nombre, u.apellidos, u.correo, 'autor' as rol,
                   e.nombre_empresa, 'empresa' as tipo_usuario
            FROM articulo a
            JOIN usuario u ON a.id_usuario = u.id_usuario
            LEFT JOIN empresa e ON u.id_usuario = e.id_usuario
            WHERE a.id_articulo = ? AND u.tipo_usuario = 'empresa'
        ");
        $stmt->execute([$id_proyecto]);
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar resultados
        $participantes_internos = array_merge($alumnos, $docentes, $empresas);
        
    } catch (PDOException $e) {
        error_log("Error al obtener participantes: " . $e->getMessage());
        $participantes_internos = [];
    }
    
    // Obtener coautores externos
    try {
        $stmt = $pdo->prepare("SELECT * FROM coautor_externo WHERE id_proyecto = ? ORDER BY nombre");
        $stmt->execute([$id_proyecto]);
        $coautores_externos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener coautores externos: " . $e->getMessage());
        $coautores_externos = [];
    }
    
    // Obtener horario asignado si es ponencia
    if ($proyecto['tipo_trabajo'] == 'ponencia') {
        try {
            $stmt = $pdo->prepare("
                SELECT h.*, CONCAT(h.fecha, ' ', h.hora_inicio, ' - ', h.hora_fin) as horario_completo
                FROM horario_ponencia h 
                WHERE h.id_proyecto = ?
            ");
            $stmt->execute([$id_proyecto]);
            $horario_asignado = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener horario: " . $e->getMessage());
            $horario_asignado = null;
        }
    }
    
    // Obtener imágenes del proyecto
    try {
        $stmt = $pdo->prepare("SELECT * FROM proyecto_imagen WHERE id_proyecto = ? ORDER BY es_principal DESC, fecha_subida DESC");
        $stmt->execute([$id_proyecto]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener imágenes: " . $e->getMessage());
        $imagenes = [];
    }
    
} catch (PDOException $e) {
    $mensaje = "Error al cargar el proyecto: " . $e->getMessage();
    $tipo_mensaje = "danger";
}

// Determinar el tipo de trabajo para los estilos
$tipo_trabajo = strtolower($proyecto['tipo_trabajo'] ?? 'ponencia');
$colores_tipo = [
    'cartel' => ['bg' => '#ffc107', 'icon' => 'fa-image', 'texto' => 'Cartel'],
    'ponencia' => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher', 'texto' => 'Ponencia'],
    'taller' => ['bg' => '#28a745', 'icon' => 'fa-tools', 'texto' => 'Taller'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube', 'texto' => 'Prototipo']
];
$color_tipo = $colores_tipo[$tipo_trabajo] ?? $colores_tipo['ponencia'];
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
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="estilo1.css">
    <title>Ver Proyecto - SIMPOSIO FESC C4</title>
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
        }
        
        .proyecto-container {
            max-width: 1000px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .proyecto-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .proyecto-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .proyecto-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
            padding-right: 100px;
        }
        
        .tipo-badge {
            position: absolute;
            top: 30px;
            right: 30px;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            background: <?php echo $color_tipo['bg']; ?>;
            color: white;
        }
        
        .proyecto-body {
            padding: 40px;
        }
        
        /* Galería de imágenes */
        .galeria-container {
            margin-bottom: 30px;
        }
        
        .galeria-titulo {
            color: #293e6b;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .imagen-principal {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            cursor: pointer;
            transition: transform 0.3s ease;
            border: 3px solid #293e6b;
        }
        
        .imagen-principal:hover {
            transform: scale(1.02);
        }
        
        .imagenes-secundarias {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .imagen-secundaria {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
        }
        
        .imagen-secundaria:hover {
            transform: scale(1.05);
            border-color: #293e6b;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .no-imagenes {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }
        
        .no-imagenes i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        /* Secciones de información */
        .info-seccion {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .info-seccion h4 {
            color: #293e6b;
            margin-bottom: 20px;
            font-weight: 600;
            border-bottom: 2px solid #293e6b;
            padding-bottom: 10px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            display: inline-block;
        }
        
        .info-value {
            color: #212529;
        }
        
        .participante-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #293e6b;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .participante-nombre {
            font-weight: 600;
            color: #293e6b;
            margin-bottom: 5px;
        }
        
        .participante-detalle {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 3px;
        }
        
        .participante-rol {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .rol-autor {
            background: #293e6b;
            color: white;
        }
        
        .rol-coautor {
            background: #6c757d;
            color: white;
        }
        
        .badge-tipo-usuario {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .badge-alumno {
            background: #17a2b8;
            color: white;
        }
        
        .badge-docente {
            background: #28a745;
            color: white;
        }
        
        .badge-empresa {
            background: #ffc107;
            color: #000;
        }
        
        .resumen-texto {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e9ecef;
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .horario-card {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #28a745;
            margin-bottom: 20px;
        }
        
        .horario-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .horario-fecha {
            font-weight: 600;
            color: #28a745;
        }
        
        .horario-hora {
            background: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .horario-salon {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .coautor-externo-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
            border-left: 4px solid #6c757d;
        }
        
        .coautor-externo-nombre {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .coautor-externo-detalle {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .btn-volver {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-volver:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-editar {
            background: #ffc107;
            color: #000;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-editar:hover {
            background: #e0a800;
            transform: translateY(-2px);
            color: #000;
        }
        
        .acciones {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .proyecto-header h2 {
                font-size: 1.5rem;
                padding-right: 0;
                margin-bottom: 60px;
            }
            
            .tipo-badge {
                position: static;
                width: fit-content;
                margin-top: 15px;
            }
            
            .imagen-principal {
                height: 250px;
            }
            
            .imagenes-secundarias {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-label {
                display: block;
                margin-bottom: 5px;
            }
            
            .horario-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .acciones {
                flex-direction: column;
            }
            
            .btn-volver, .btn-editar {
                width: 100%;
                justify-content: center;
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
                        <a class="nav-link" href="mis_proyectos.php"><i class="fas fa-folder-open me-1"></i>Mis Proyectos</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="proyecto-container">
        <div class="proyecto-card">
            <div class="proyecto-header">
                <h2>
                    <i class="fas fa-file-alt me-3"></i>
                    <?php echo htmlspecialchars($proyecto['titulo'] ?? 'Sin título'); ?>
                </h2>
                <div class="tipo-badge">
                    <i class="fas <?php echo $color_tipo['icon']; ?>"></i>
                    <?php echo $color_tipo['texto']; ?>
                </div>
            </div>
            
            <div class="proyecto-body">
                <?php if($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
                <?php endif; ?>

                <!-- Galería de imágenes -->
                <div class="galeria-container">
                    <h4 class="galeria-titulo">
                        <i class="fas fa-images me-2"></i>
                        Galería de imágenes
                    </h4>
                    
                    <?php if(empty($imagenes)): ?>
                    <div class="no-imagenes">
                        <i class="fas fa-image"></i>
                        <h5>No hay imágenes disponibles</h5>
                        <p class="mb-0">Este proyecto no tiene imágenes asociadas</p>
                    </div>
                    <?php else: ?>
                        <?php 
                        $imagen_principal = array_filter($imagenes, function($img) { return $img['es_principal']; });
                        $imagen_principal = reset($imagen_principal) ?: $imagenes[0];
                        $otras_imagenes = array_filter($imagenes, function($img) use ($imagen_principal) {
                            return $img['id_imagen'] != $imagen_principal['id_imagen'];
                        });
                        ?>
                        
                        <!-- Imagen principal -->
                        <a href="uploads/proyectos/<?php echo $imagen_principal['nombre_archivo']; ?>" data-lightbox="proyecto" data-title="<?php echo htmlspecialchars($proyecto['titulo']); ?>">
                            <img src="uploads/proyectos/<?php echo $imagen_principal['nombre_archivo']; ?>" 
                                 alt="Imagen principal" 
                                 class="imagen-principal">
                        </a>
                        
                        <!-- Imágenes secundarias -->
                        <?php if(!empty($otras_imagenes)): ?>
                        <div class="imagenes-secundarias">
                            <?php foreach($otras_imagenes as $imagen): ?>
                            <a href="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" data-lightbox="proyecto" data-title="<?php echo htmlspecialchars($proyecto['titulo']); ?>">
                                <img src="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" 
                                     alt="Imagen del proyecto" 
                                     class="imagen-secundaria">
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Información general -->
                <div class="info-seccion">
                    <h4><i class="fas fa-info-circle me-2"></i>Información general</h4>
                    
                    <div class="info-item">
                        <span class="info-label">ID del proyecto:</span>
                        <span class="info-value">#<?php echo $proyecto['id_articulo']; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Categoría:</span>
                        <span class="info-value"><?php echo htmlspecialchars($proyecto['categoria'] ?? 'Sin categoría'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Fecha de registro:</span>
                        <span class="info-value">
                            <?php 
                            if (isset($proyecto['fecha_registro'])) {
                                echo date('d/m/Y H:i', strtotime($proyecto['fecha_registro']));
                            } else {
                                echo 'No disponible';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Horario de ponencia (si aplica) -->
                <?php if($proyecto['tipo_trabajo'] == 'ponencia' && $horario_asignado): ?>
                <div class="info-seccion">
                    <h4><i class="fas fa-clock me-2"></i>Horario de la ponencia</h4>
                    <div class="horario-card">
                        <div class="horario-info">
                            <span class="horario-fecha">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo date('d/m/Y', strtotime($horario_asignado['fecha'])); ?>
                            </span>
                            <span class="horario-hora">
                                <i class="fas fa-clock me-2"></i>
                                <?php echo date('H:i', strtotime($horario_asignado['hora_inicio'])); ?> - 
                                <?php echo date('H:i', strtotime($horario_asignado['hora_fin'])); ?>
                            </span>
                            <?php if(!empty($horario_asignado['salon'])): ?>
                            <span class="horario-salon">
                                <i class="fas fa-door-open me-2"></i>
                                <?php echo htmlspecialchars($horario_asignado['salon']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resumen -->
                <div class="info-seccion">
                    <h4><i class="fas fa-align-left me-2"></i>Resumen</h4>
                    <div class="resumen-texto">
                        <?php echo nl2br(htmlspecialchars($proyecto['resumen'] ?? 'Sin resumen disponible')); ?>
                    </div>
                </div>

                <!-- Participantes Internos -->
                <div class="info-seccion">
                    <h4><i class="fas fa-users me-2"></i>Participantes</h4>
                    
                    <?php if(!empty($participantes_internos)): ?>
                        <?php foreach($participantes_internos as $p): ?>
                        <div class="participante-card">
                            <div class="participante-nombre">
                                <?php if($p['tipo_usuario'] == 'alumno'): ?>
                                    <i class="fas fa-user-graduate me-2"></i>
                                <?php elseif($p['tipo_usuario'] == 'docente'): ?>
                                    <i class="fas fa-chalkboard-teacher me-2"></i>
                                <?php elseif($p['tipo_usuario'] == 'empresa'): ?>
                                    <i class="fas fa-building me-2"></i>
                                <?php endif; ?>
                                
                                <?php echo htmlspecialchars($p['nombre'] . ' ' . ($p['apellidos'] ?? '')); ?>
                                
                                <span class="badge-tipo-usuario badge-<?php echo $p['tipo_usuario']; ?>">
                                    <?php echo ucfirst($p['tipo_usuario']); ?>
                                </span>
                            </div>
                            
                            <div class="participante-detalle">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($p['correo']); ?>
                            </div>
                            
                            <?php if($p['tipo_usuario'] == 'alumno'): ?>
                                <?php if(!empty($p['matricula'])): ?>
                                <div class="participante-detalle">
                                    <i class="fas fa-id-card me-2"></i>Matrícula: <?php echo htmlspecialchars($p['matricula']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($p['carrera'])): ?>
                                <div class="participante-detalle">
                                    <i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($p['carrera']); ?>
                                </div>
                                <?php endif; ?>
                            <?php elseif($p['tipo_usuario'] == 'docente'): ?>
                                <?php if(!empty($p['grado_academico'])): ?>
                                <div class="participante-detalle">
                                    <i class="fas fa-award me-2"></i><?php echo htmlspecialchars($p['grado_academico']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($p['especialidad'])): ?>
                                <div class="participante-detalle">
                                    <i class="fas fa-flask me-2"></i><?php echo htmlspecialchars($p['especialidad']); ?>
                                </div>
                                <?php endif; ?>
                            <?php elseif($p['tipo_usuario'] == 'empresa' && !empty($p['nombre_empresa'])): ?>
                                <div class="participante-detalle">
                                    <i class="fas fa-briefcase me-2"></i><?php echo htmlspecialchars($p['nombre_empresa']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <span class="participante-rol <?php echo (isset($p['rol']) && $p['rol'] == 'autor') ? 'rol-autor' : 'rol-coautor'; ?>">
                                <i class="fas <?php echo (isset($p['rol']) && $p['rol'] == 'autor') ? 'fa-crown' : 'fa-user-friends'; ?> me-1"></i>
                                <?php echo isset($p['rol']) ? ucfirst($p['rol']) : 'Autor'; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if(empty($participantes_internos)): ?>
                        <p class="text-muted">No hay participantes internos registrados</p>
                    <?php endif; ?>
                </div>

                <!-- Coautores Externos -->
                <?php if(!empty($coautores_externos)): ?>
                <div class="info-seccion">
                    <h4><i class="fas fa-user-tie me-2"></i>Coautores Externos</h4>
                    
                    <?php foreach($coautores_externos as $ce): ?>
                    <div class="coautor-externo-card">
                        <div class="coautor-externo-nombre">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($ce['nombre']); ?>
                        </div>
                        
                        <?php if(!empty($ce['rfc'])): ?>
                        <div class="coautor-externo-detalle">
                            <i class="fas fa-id-card me-2"></i>RFC: <?php echo htmlspecialchars($ce['rfc']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($ce['email'])): ?>
                        <div class="coautor-externo-detalle">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($ce['email']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($ce['institucion'])): ?>
                        <div class="coautor-externo-detalle">
                            <i class="fas fa-university me-2"></i><?php echo htmlspecialchars($ce['institucion']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Acciones -->
                <div class="acciones">
                    <a href="mis_proyectos.php" class="btn-volver">
                        <i class="fas fa-arrow-left me-2"></i>
                        Volver a mis proyectos
                    </a>
                    <a href="editar_proyecto.php?id=<?php echo $id_proyecto; ?>" class="btn-editar">
                        <i class="fas fa-edit me-2"></i>
                        Editar proyecto
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        // Configuración de Lightbox
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Imagen %1 de %2',
            'fadeDuration': 300,
            'imageFadeDuration': 300
        });
    </script>
</body>
</html>