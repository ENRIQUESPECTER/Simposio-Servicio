<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';

// Verificar si el usuario está logueado
if (!esta_logeado()) {
    header('Location: login.php');
    exit;
}

$id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id_proyecto) {
    header('Location: mis_proyectos.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del proyecto
$stmt = $conexion->prepare("
    SELECT a.*, e.titulo as nombre_evento, e.fecha as fecha_evento
    FROM articulo a
    LEFT JOIN evento e ON a.id_evento = e.id_evento
    WHERE a.id_articulo = ?
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$proyecto = $stmt->get_result()->fetch_assoc();

if (!$proyecto) {
    header('Location: mis_proyectos.php');
    exit;
}

// Obtener participantes internos (alumnos y docentes)
$participantes = [];

// Alumnos
$stmt = $conexion->prepare("
    SELECT u.nombre, u.apellidos, u.correo, u.tipo_usuario,
           a.matricula, a.carrera, aa.rol
    FROM articulo_alumno aa
    JOIN alumno a ON aa.id_alumno = a.id_alumno
    JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE aa.id_articulo = ?
    ORDER BY aa.rol DESC, u.nombre
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $participantes[] = $row;
}

// Docentes
$stmt = $conexion->prepare("
    SELECT u.nombre, u.apellidos, u.correo, u.tipo_usuario,
           d.especialidad, d.grado_academico, 'autor' as rol
    FROM articulo_docente ad
    JOIN docente d ON ad.id_docente = d.id_docente
    JOIN usuario u ON d.id_usuario = u.id_usuario
    WHERE ad.id_articulo = ?
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $participantes[] = $row;
}

// Si hay id_usuario en articulo (empresa), agregarlo
if ($proyecto['id_usuario']) {
    $stmt = $conexion->prepare("
        SELECT u.nombre, u.apellidos, u.correo, u.tipo_usuario,
               e.nombre_empresa
        FROM usuario u
        LEFT JOIN empresa e ON u.id_usuario = e.id_usuario
        WHERE u.id_usuario = ?
    ");
    $stmt->bind_param("i", $proyecto['id_usuario']);
    $stmt->execute();
    $empresa = $stmt->get_result()->fetch_assoc();
    if ($empresa) {
        $empresa['rol'] = 'autor';
        $participantes[] = $empresa;
    }
}

// Obtener coautores externos
$coautores_externos = [];
$stmt = $conexion->prepare("SELECT * FROM coautor_externo WHERE id_articulo = ? ORDER BY nombre");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $coautores_externos[] = $row;
}

// Obtener actividad (horario) asociada
$actividad = null;
$stmt = $conexion->prepare("
    SELECT ae.*, s.nombre as salon 
    FROM actividad_evento ae 
    LEFT JOIN salones s ON ae.id_salon = s.id_salon 
    WHERE ae.id_articulo = ? 
    LIMIT 1
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();

// Obtener imágenes
$imagenes = [];
$stmt = $conexion->prepare("
    SELECT * FROM proyecto_imagen 
    WHERE id_articulo = ? 
    ORDER BY es_principal DESC, fecha_subida DESC
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $imagenes[] = $row;
}

// Determinar el tipo de trabajo para estilos
$tipo_trabajo = strtolower($proyecto['tipo_trabajo'] ?? 'ponencia');
$colores_tipo = [
    'cartel'    => ['bg' => '#ffc107', 'icon' => 'fa-image', 'texto' => 'Cartel'],
    'ponencia'  => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher', 'texto' => 'Ponencia'],
    'taller'    => ['bg' => '#28a745', 'icon' => 'fa-tools', 'texto' => 'Taller'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube', 'texto' => 'Prototipo']
];
$color_tipo = $colores_tipo[$tipo_trabajo] ?? $colores_tipo['ponencia'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <link rel="stylesheet" href="Css/estilo1.css">
    <title>Ver Proyecto - SIMPOSIO</title>
    <style>
        .proyecto-container { max-width: 1000px; margin: 100px auto; padding: 0 20px; }
        .proyecto-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .proyecto-header { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; padding: 30px; position: relative; }
        .proyecto-header h2 { margin: 0; font-size: 2rem; padding-right: 100px; }
        .tipo-badge { position: absolute; top: 30px; right: 30px; padding: 10px 20px; border-radius: 30px; background: <?php echo $color_tipo['bg']; ?>; color: white; display: flex; align-items: center; gap: 10px; }
        .proyecto-body { padding: 40px; }
        .info-seccion { background: #f8f9fa; border-radius: 15px; padding: 25px; margin-bottom: 25px; border: 1px solid #e9ecef; }
        .info-seccion h4 { color: #293e6b; margin-bottom: 20px; border-bottom: 2px solid #293e6b; padding-bottom: 10px; }
        .participante-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #293e6b; }
        .participante-nombre { font-weight: 600; color: #293e6b; }
        .rol-autor { background: #293e6b; color: white; display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem; }
        .rol-coautor { background: #6c757d; color: white; display: inline-block; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem; }
        .imagen-principal { width: 100%; height: 400px; object-fit: cover; border-radius: 15px; cursor: pointer; border: 3px solid #293e6b; }
        .imagenes-secundarias { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px,1fr)); gap: 15px; margin-top: 20px; }
        .imagen-secundaria { width: 100%; height: 120px; object-fit: cover; border-radius: 10px; cursor: pointer; border: 2px solid #dee2e6; }
        .no-imagenes { background: #f8f9fa; border-radius: 15px; padding: 40px; text-align: center; color: #6c757d; border: 2px dashed #dee2e6; }
        .horario-card { background: #e8f5e9; border-radius: 10px; padding: 15px; border-left: 4px solid #28a745; }
        .btn-volver { background: #6c757d; color: white; padding: 12px 30px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .btn-editar { background: #ffc107; color: black; padding: 12px 30px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <!-- Aquí va tu navbar personalizada -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary"> ... </nav>

    <div class="proyecto-container">
        <div class="proyecto-card">
            <div class="proyecto-header">
                <h2><i class="fas fa-file-alt me-3"></i><?php echo htmlspecialchars($proyecto['titulo']); ?></h2>
                <div class="tipo-badge">
                    <i class="fas <?php echo $color_tipo['icon']; ?>"></i>
                    <?php echo $color_tipo['texto']; ?>
                </div>
            </div>
            <div class="proyecto-body">
                <!-- Galería -->
                <div class="info-seccion">
                    <h4><i class="fas fa-images me-2"></i>Galería</h4>
                    <?php if (empty($imagenes)): ?>
                        <div class="no-imagenes">
                            <i class="fas fa-image"></i>
                            <h5>Sin imágenes</h5>
                        </div>
                    <?php else: 
                        $principal = array_filter($imagenes, fn($img) => $img['es_principal']);
                        $principal = reset($principal) ?: $imagenes[0];
                        $otras = array_filter($imagenes, fn($img) => $img['id_imagen'] != $principal['id_imagen']);
                    ?>
                        <a href="uploads/proyectos/<?php echo $principal['nombre_archivo']; ?>" data-lightbox="proyecto">
                            <img src="uploads/proyectos/<?php echo $principal['nombre_archivo']; ?>" class="imagen-principal">
                        </a>
                        <?php if (!empty($otras)): ?>
                        <div class="imagenes-secundarias">
                            <?php foreach ($otras as $img): ?>
                            <a href="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" data-lightbox="proyecto">
                                <img src="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" class="imagen-secundaria">
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Información general -->
                <div class="info-seccion">
                    <h4><i class="fas fa-info-circle me-2"></i>Información general</h4>
                    <p><strong>ID:</strong> #<?php echo $proyecto['id_articulo']; ?></p>
                    <p><strong>Evento:</strong> <?php echo htmlspecialchars($proyecto['nombre_evento'] ?? 'No asignado'); ?></p>
                    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($proyecto['categoria'] ?? 'Sin categoría'); ?></p>
                    <p><strong>Fecha de registro:</strong> <?php echo date('d/m/Y H:i', strtotime($proyecto['fecha_registro'])); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge bg-<?php echo $proyecto['estado'] == 'aprobado' ? 'success' : ($proyecto['estado'] == 'rechazado' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($proyecto['estado']); ?>
                        </span>
                    </p>
                </div>

                <!-- Horario (si existe) -->
                <?php if ($actividad): ?>
                <div class="info-seccion">
                    <h4><i class="fas fa-clock me-2"></i>Horario de presentación</h4>
                    <div class="horario-card">
                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($actividad['fecha'])); ?></p>
                        <p><strong>Hora:</strong> <?php echo substr($actividad['hora_inicio'],0,5); ?> - <?php echo substr($actividad['hora_fin'],0,5); ?></p>
                        <?php if (!empty($actividad['salon'])): ?>
                        <p><strong>Salón:</strong> <?php echo htmlspecialchars($actividad['salon']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resumen -->
                <div class="info-seccion">
                    <h4><i class="fas fa-align-left me-2"></i>Resumen</h4>
                    <p><?php echo nl2br(htmlspecialchars($proyecto['resumen'] ?? 'Sin resumen')); ?></p>
                </div>

                <!-- Participantes internos -->
                <?php if (!empty($participantes)): ?>
                <div class="info-seccion">
                    <h4><i class="fas fa-users me-2"></i>Participantes</h4>
                    <?php foreach ($participantes as $p): ?>
                    <div class="participante-card">
                        <div class="participante-nombre">
                            <i class="fas fa-<?php echo $p['tipo_usuario'] == 'alumno' ? 'user-graduate' : ($p['tipo_usuario'] == 'docente' ? 'chalkboard-teacher' : 'building'); ?> me-2"></i>
                            <?php echo htmlspecialchars($p['nombre'] . ' ' . ($p['apellidos'] ?? '')); ?>
                            <span class="badge bg-secondary"><?php echo ucfirst($p['tipo_usuario']); ?></span>
                        </div>
                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($p['correo']); ?></p>
                        <?php if ($p['tipo_usuario'] == 'alumno' && !empty($p['matricula'])): ?>
                            <p class="mb-1"><i class="fas fa-id-card me-2"></i>Matrícula: <?php echo htmlspecialchars($p['matricula']); ?></p>
                        <?php elseif ($p['tipo_usuario'] == 'docente' && !empty($p['grado_academico'])): ?>
                            <p class="mb-1"><i class="fas fa-award me-2"></i><?php echo htmlspecialchars($p['grado_academico']); ?></p>
                        <?php elseif ($p['tipo_usuario'] == 'empresa' && !empty($p['nombre_empresa'])): ?>
                            <p class="mb-1"><i class="fas fa-briefcase me-2"></i><?php echo htmlspecialchars($p['nombre_empresa']); ?></p>
                        <?php endif; ?>
                        <span class="<?php echo ($p['rol'] ?? 'autor') == 'autor' ? 'rol-autor' : 'rol-coautor'; ?>">
                            <i class="fas fa-<?php echo ($p['rol'] ?? 'autor') == 'autor' ? 'crown' : 'user-friends'; ?> me-1"></i>
                            <?php echo ucfirst($p['rol'] ?? 'autor'); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Coautores externos -->
                <?php if (!empty($coautores_externos)): ?>
                <div class="info-seccion">
                    <h4><i class="fas fa-user-tie me-2"></i>Coautores externos</h4>
                    <?php foreach ($coautores_externos as $ce): ?>
                    <div class="participante-card" style="border-left-color: #6c757d;">
                        <p><strong><?php echo htmlspecialchars($ce['nombre']); ?></strong></p>
                        <?php if (!empty($ce['email'])): ?>
                        <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($ce['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($ce['institucion'])): ?>
                        <p><i class="fas fa-university me-2"></i><?php echo htmlspecialchars($ce['institucion']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="d-flex justify-content-between">
                    <a href="mis_proyectos.php" class="btn-volver"><i class="fas fa-arrow-left me-2"></i>Volver</a>
                    <a href="editar_proyecto.php?id=<?php echo $id_proyecto; ?>" class="btn-editar"><i class="fas fa-edit me-2"></i>Editar</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        lightbox.option({ 'resizeDuration': 200, 'wrapAround': true, 'albumLabel': 'Imagen %1 de %2' });
    </script>
</body>
</html>