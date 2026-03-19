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

// Obtener información del usuario actual
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

// Obtener datos del proyecto
$stmt = $conexion->prepare("SELECT * FROM articulo WHERE id_articulo = ?");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$proyecto = $stmt->get_result()->fetch_assoc();

if (!$proyecto) {
    header('Location: mis_proyectos.php');
    exit;
}

// Verificar que el usuario sea autor (tiene permiso para editar)
$es_autor = false;
if ($id_especifico) {
    if ($id_especifico['tipo'] == 'alumno') {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo_alumno WHERE id_articulo = ? AND id_alumno = ? AND rol = 'autor'");
        $stmt->bind_param("ii", $id_proyecto, $id_especifico['id']);
    } elseif ($id_especifico['tipo'] == 'docente') {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo_docente WHERE id_articulo = ? AND id_docente = ?");
        $stmt->bind_param("ii", $id_proyecto, $id_especifico['id']);
    } elseif ($id_especifico['tipo'] == 'empresa') {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo WHERE id_articulo = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_proyecto, $_SESSION['id_usuario']);
    }
    if (isset($stmt)) {
        $stmt->execute();
        $es_autor = $stmt->get_result()->fetch_row()[0] > 0;
    }
}

if (!$es_autor) {
    $_SESSION['mensaje'] = "No tienes permiso para editar este proyecto.";
    $_SESSION['tipo_mensaje'] = "warning";
    header('Location: mis_proyectos.php');
    exit;
}

// Obtener participantes actuales (para mostrarlos en el formulario)
$participantes_internos = [];
// Alumnos
$stmt = $conexion->prepare("
    SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
           a.matricula, a.carrera, aa.rol
    FROM articulo_alumno aa
    JOIN alumno a ON aa.id_alumno = a.id_alumno
    JOIN usuario u ON a.id_usuario = u.id_usuario
    WHERE aa.id_articulo = ?
");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $participantes_internos[] = $row;
}
// Docentes
$stmt = $conexion->prepare("
    SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
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
    $participantes_internos[] = $row;
}

// Obtener coautores externos
$coautores_externos = [];
$stmt = $conexion->prepare("SELECT * FROM coautor_externo WHERE id_articulo = ? ORDER BY id_coautor");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $coautores_externos[] = $row;
}

// Obtener actividad (horario) asociada
$actividad = null;
$stmt = $conexion->prepare("SELECT * FROM actividad_evento WHERE id_articulo = ? LIMIT 1");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();

// Obtener imágenes
$imagenes = [];
$stmt = $conexion->prepare("SELECT * FROM proyecto_imagen WHERE id_articulo = ? ORDER BY es_principal DESC, fecha_subida DESC");
$stmt->bind_param("i", $id_proyecto);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $imagenes[] = $row;
}

// Obtener lista de posibles coautores internos
$posibles_coautores = [];
$stmt = $conexion->prepare("
    SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
           a.matricula, d.especialidad, e.nombre_empresa
    FROM usuario u
    LEFT JOIN alumno a ON u.id_usuario = a.id_usuario AND u.tipo_usuario = 'alumno'
    LEFT JOIN docente d ON u.id_usuario = d.id_usuario AND u.tipo_usuario = 'docente'
    LEFT JOIN empresa e ON u.id_usuario = e.id_usuario AND u.tipo_usuario = 'empresa'
    WHERE u.id_usuario != ?
    ORDER BY u.nombre
");
$stmt->bind_param("i", $_SESSION['id_usuario']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $posibles_coautores[] = $row;
}

// Obtener eventos para el selector
$eventos = [];
$result_eventos = $conexion->query("SELECT id_evento, titulo, fecha FROM evento WHERE fecha >= CURDATE() ORDER BY fecha");
while ($row = $result_eventos->fetch_assoc()) {
    $eventos[] = $row;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] == 'actualizar_proyecto') {
        // Recoger datos
        $id_evento = intval($_POST['id_evento'] ?? $proyecto['id_evento']);
        $tipo_trabajo = $_POST['tipo'] ?? $proyecto['tipo_trabajo'];
        $categoria = $_POST['categoria'] ?? $proyecto['categoria'];
        $titulo = trim($_POST['titulo'] ?? $proyecto['titulo']);
        $resumen = trim($_POST['resumen'] ?? $proyecto['resumen']);
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $coautores_internos = $_POST['coautores_internos'] ?? [];
        $coautores_externos_post = $_POST['coautores_externos'] ?? [];
        $eliminar_imagenes = $_POST['eliminar_imagenes'] ?? [];

        $errores = [];

        // Validaciones básicas
        if (empty($titulo)) $errores[] = "El título es obligatorio.";
        if (empty($categoria)) $errores[] = "Debe seleccionar una categoría.";
        if (empty($id_evento)) $errores[] = "Debe seleccionar un evento.";

        // Determinar si el tipo requiere horario (según mapeo en funciones.php)
        $id_tipo_actividad = tipo_trabajo_a_id_actividad($tipo_trabajo);
        $requiere_horario = !is_null($id_tipo_actividad);

        if ($requiere_horario && empty($hora_inicio)) {
            $errores[] = "Debe seleccionar un horario.";
        }

        if (empty($errores)) {
            $conexion->begin_transaction();
            try {
                // Actualizar artículo
                $stmt = $conexion->prepare("UPDATE articulo SET id_evento = ?, titulo = ?, resumen = ?, tipo_trabajo = ?, categoria = ? WHERE id_articulo = ?");
                $stmt->bind_param("issssi", $id_evento, $titulo, $resumen, $tipo_trabajo, $categoria, $id_proyecto);
                $stmt->execute();

                // --- Actualizar coautores internos ---
                // Eliminar todos excepto el autor principal según el tipo de usuario
                if ($id_especifico['tipo'] == 'alumno') {
                    $stmt = $conexion->prepare("DELETE FROM articulo_alumno WHERE id_articulo = ? AND id_alumno != ? AND rol = 'coautor'");
                    $stmt->bind_param("ii", $id_proyecto, $id_especifico['id']);
                    $stmt->execute();
                    $stmt = $conexion->prepare("DELETE FROM articulo_docente WHERE id_articulo = ?");
                    $stmt->bind_param("i", $id_proyecto);
                    $stmt->execute();
                } elseif ($id_especifico['tipo'] == 'docente') {
                    $stmt = $conexion->prepare("DELETE FROM articulo_docente WHERE id_articulo = ? AND id_docente != ?");
                    $stmt->bind_param("ii", $id_proyecto, $id_especifico['id']);
                    $stmt->execute();
                    $stmt = $conexion->prepare("DELETE FROM articulo_alumno WHERE id_articulo = ?");
                    $stmt->bind_param("i", $id_proyecto);
                    $stmt->execute();
                } else {
                    // Empresa: eliminar todas las relaciones
                    $stmt = $conexion->prepare("DELETE FROM articulo_alumno WHERE id_articulo = ?");
                    $stmt->bind_param("i", $id_proyecto);
                    $stmt->execute();
                    $stmt = $conexion->prepare("DELETE FROM articulo_docente WHERE id_articulo = ?");
                    $stmt->bind_param("i", $id_proyecto);
                    $stmt->execute();
                }

                // Insertar nuevos coautores internos
                foreach ($coautores_internos as $id_coautor_usuario) {
                    if (empty($id_coautor_usuario)) continue;
                    $stmt = $conexion->prepare("SELECT tipo_usuario FROM usuario WHERE id_usuario = ?");
                    $stmt->bind_param("i", $id_coautor_usuario);
                    $stmt->execute();
                    $tipo_coautor = $stmt->get_result()->fetch_assoc()['tipo_usuario'];

                    if ($tipo_coautor == 'alumno') {
                        $stmt2 = $conexion->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
                        $stmt2->bind_param("i", $id_coautor_usuario);
                        $stmt2->execute();
                        $id_alumno = $stmt2->get_result()->fetch_assoc()['id_alumno'];
                        if ($id_alumno) {
                            $stmt3 = $conexion->prepare("INSERT INTO articulo_alumno (id_articulo, id_alumno, rol) VALUES (?, ?, 'coautor')");
                            $stmt3->bind_param("ii", $id_proyecto, $id_alumno);
                            $stmt3->execute();
                        }
                    } elseif ($tipo_coautor == 'docente') {
                        $stmt2 = $conexion->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
                        $stmt2->bind_param("i", $id_coautor_usuario);
                        $stmt2->execute();
                        $id_docente = $stmt2->get_result()->fetch_assoc()['id_docente'];
                        if ($id_docente) {
                            $stmt3 = $conexion->prepare("INSERT INTO articulo_docente (id_articulo, id_docente) VALUES (?, ?)");
                            $stmt3->bind_param("ii", $id_proyecto, $id_docente);
                            $stmt3->execute();
                        }
                    }
                }

                // --- Actualizar coautores externos ---
                $stmt = $conexion->prepare("DELETE FROM coautor_externo WHERE id_articulo = ?");
                $stmt->bind_param("i", $id_proyecto);
                $stmt->execute();

                foreach ($coautores_externos_post as $ext) {
                    if (!empty($ext['nombre'])) {
                        $stmt = $conexion->prepare("INSERT INTO coautor_externo (id_articulo, nombre, rfc, email, institucion) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issss", $id_proyecto, $ext['nombre'], $ext['rfc'], $ext['email'], $ext['institucion']);
                        $stmt->execute();
                    }
                }

                // --- Actualizar actividad en agenda ---
                // Obtener fecha del evento seleccionado
                $fecha_evento = '';
                foreach ($eventos as $ev) {
                    if ($ev['id_evento'] == $id_evento) {
                        $fecha_evento = $ev['fecha'];
                        break;
                    }
                }

                if ($requiere_horario && !empty($hora_inicio)) {
                    // Calcular hora_fin según duración
                    $duracion = duracion_tipo_trabajo($tipo_trabajo);
                    $hora_fin = date("H:i:s", strtotime($hora_inicio) + $duracion * 60);

                    if ($actividad) {
                        // Actualizar actividad existente
                        $stmt = $conexion->prepare("UPDATE actividad_evento 
                            SET id_evento = ?, id_tipo = ?, titulo = ?, resumen = ?, fecha = ?, hora_inicio = ?, hora_fin = ? 
                            WHERE id_articulo = ?");
                        $stmt->bind_param("iisssssi", $id_evento, $id_tipo_actividad, $titulo, $resumen, $fecha_evento, $hora_inicio, $hora_fin, $id_proyecto);
                        $stmt->execute();
                    } else {
                        // Crear nueva actividad
                        $stmt = $conexion->prepare("INSERT INTO actividad_evento 
                            (id_evento, id_usuario, id_tipo, id_articulo, titulo, resumen, fecha, hora_inicio, hora_fin) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiiisssss", $id_evento, $_SESSION['id_usuario'], $id_tipo_actividad, $id_proyecto, $titulo, $resumen, $fecha_evento, $hora_inicio, $hora_fin);
                        $stmt->execute();
                    }
                } else {
                    // Si ya no requiere horario, eliminar la actividad si existe
                    if ($actividad) {
                        $stmt = $conexion->prepare("DELETE FROM actividad_evento WHERE id_articulo = ?");
                        $stmt->bind_param("i", $id_proyecto);
                        $stmt->execute();
                    }
                }

                // --- Eliminar imágenes seleccionadas ---
                foreach ($eliminar_imagenes as $id_imagen) {
                    // Obtener nombre del archivo
                    $stmt = $conexion->prepare("SELECT nombre_archivo FROM proyecto_imagen WHERE id_imagen = ? AND id_articulo = ?");
                    $stmt->bind_param("ii", $id_imagen, $id_proyecto);
                    $stmt->execute();
                    $img = $stmt->get_result()->fetch_assoc();
                    if ($img) {
                        $ruta = 'uploads/proyectos/' . $img['nombre_archivo'];
                        if (file_exists($ruta)) {
                            unlink($ruta);
                        }
                        $stmt2 = $conexion->prepare("DELETE FROM proyecto_imagen WHERE id_imagen = ?");
                        $stmt2->bind_param("i", $id_imagen);
                        $stmt2->execute();
                    }
                }

                // --- Subir nuevas imágenes ---
                if (!empty($_FILES['imagenes']['name'][0])) {
                    $carpeta = 'uploads/proyectos/';
                    if (!file_exists($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }
                    $es_principal = empty($imagenes); // Si no quedan imágenes, la primera será principal
                    foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['imagenes']['error'][$key] == 0) {
                            $nombre_original = $_FILES['imagenes']['name'][$key];
                            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                            $nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
                            $ruta = $carpeta . $nombre_archivo;
                            if (move_uploaded_file($tmp_name, $ruta)) {
                                $tipo_img = mime_content_type($tmp_name);
                                $tamaño = $_FILES['imagenes']['size'][$key];
                                $principal = $es_principal ? 1 : 0;
                                $stmt = $conexion->prepare("INSERT INTO proyecto_imagen (id_articulo, nombre_archivo, archivo_original, tipo_imagen, tamaño, es_principal) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("isssii", $id_proyecto, $nombre_archivo, $nombre_original, $tipo_img, $tamaño, $principal);
                                $stmt->execute();
                                $es_principal = false;
                            }
                        }
                    }
                }

                // --- Establecer imagen principal ---
                if (isset($_POST['imagen_principal']) && !empty($_POST['imagen_principal'])) {
                    $id_principal = intval($_POST['imagen_principal']);
                    $stmt = $conexion->prepare("UPDATE proyecto_imagen SET es_principal = 0 WHERE id_articulo = ?");
                    $stmt->bind_param("i", $id_proyecto);
                    $stmt->execute();
                    $stmt = $conexion->prepare("UPDATE proyecto_imagen SET es_principal = 1 WHERE id_imagen = ? AND id_articulo = ?");
                    $stmt->bind_param("ii", $id_principal, $id_proyecto);
                    $stmt->execute();
                }

                $conexion->commit();
                $mensaje = "Proyecto actualizado exitosamente.";
                $tipo_mensaje = "success";
                header("refresh:2;url=ver_proyecto.php?id=$id_proyecto");
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        } else {
            $mensaje = implode("<br>", $errores);
            $tipo_mensaje = "warning";
        }
    }
}

// ... (resto del HTML, que permanece igual al que te di antes) ...
// Colores para tipo de trabajo (para el badge)
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <link rel="stylesheet" href="Css/estilo1.css">
    <title>Editar Proyecto - SIMPOSIO</title>
    <style>
        /* Estilos similares a registrar_trabajos.php */
        .editar-container { max-width: 1200px; margin: 100px auto; padding: 0 20px; }
        .editar-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .editar-header { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; padding: 30px; position: relative; }
        .editar-header h2 { margin: 0; font-size: 2rem; padding-right: 100px; }
        .tipo-badge { position: absolute; top: 30px; right: 30px; padding: 10px 20px; border-radius: 30px; background: <?php echo $color_tipo['bg']; ?>; color: white; display: flex; align-items: center; gap: 10px; }
        .editar-body { padding: 40px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { font-weight: 600; color: #293e6b; display: block; margin-bottom: 8px; }
        .tipo-trabajo { display: grid; grid-template-columns: repeat(4,1fr); gap: 15px; }
        .tipo-btn { padding: 20px 10px; background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 15px; text-align: center; cursor: pointer; transition: 0.3s; }
        .tipo-btn input[type="radio"] { position: absolute; opacity: 0; }
        .tipo-btn i { font-size: 2rem; color: #293e6b; margin-bottom: 10px; display: block; }
        .tipo-btn.active { border-color: #293e6b; background: #e9ecef; transform: translateY(-3px); }
        .tipo-btn.active i { color: #D59F0F; }
        .horarios-container { background: #f8f9fa; border-radius: 15px; padding: 20px; border: 2px solid #dee2e6; display: none; }
        .horarios-container.mostrar { display: block; }
        .coautores-section { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-top: 20px; border: 2px solid #dee2e6; }
        .btn-add { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; margin-right: 10px; }
        .btn-remove { background: #dc3545; color: white; border: none; width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
        .coautor-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 15px; background: white; border-radius: 10px; border: 1px solid #dee2e6; }
        .coautor-externo-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; flex: 1; }
        .galeria-container { background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 25px; border: 2px solid #dee2e6; }
        .imagenes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 20px; margin-top: 20px; }
        .imagen-item { position: relative; border-radius: 10px; overflow: hidden; border: 2px solid #dee2e6; }
        .imagen-item.principal { border-color: #293e6b; border-width: 3px; }
        .imagen-item img { width: 100%; height: 150px; object-fit: cover; }
        .imagen-overlay { position: absolute; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display: flex; flex-direction: column; justify-content: center; align-items: center; opacity:0; transition: 0.3s; }
        .imagen-item:hover .imagen-overlay { opacity:1; }
        .btn-principal { background: #293e6b; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
        .btn-principal.activo { background: #28a745; }
        .acciones { display: flex; gap: 15px; justify-content: space-between; margin-top: 30px; }
        .imagen-actions {
    padding: 8px;
    background: #f8f9fa;
    border-radius: 0 0 10px 10px;
}
.imagen-item {
    border: 2px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    transition: 0.3s;
}
.imagen-item.principal {
    border-color: #293e6b;
    border-width: 3px;
}
.imagen-item.marcado-eliminar {
    border-color: #dc3545;
    background-color: #f8d7da;
}
.imagen-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}
.imagen-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #293e6b;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    z-index: 1;
}
    </style>
</head>
<body>
    <!-- Navbar personalizada (puedes incluir tu propia navbar) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary"> ... </nav>

    <div class="editar-container">
        <div class="editar-card">
            <div class="editar-header">
                <h2><i class="fas fa-edit me-3"></i>Editar Proyecto</h2>
                <div class="tipo-badge">
                    <i class="fas <?php echo $color_tipo['icon']; ?>"></i>
                    <?php echo $color_tipo['texto']; ?>
                </div>
            </div>
            <div class="editar-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="formEditar" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="actualizar_proyecto">

                    <!-- Evento -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt me-2"></i>Evento *</label>
                        <select class="form-select" name="id_evento" id="id_evento" required>
                            <option value="">Seleccione un evento...</option>
                            <?php foreach ($eventos as $ev): ?>
                            <option value="<?php echo $ev['id_evento']; ?>" data-fecha="<?php echo $ev['fecha']; ?>" <?php echo ($ev['id_evento'] == $proyecto['id_evento']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['titulo'] . ' (' . date('d/m/Y', strtotime($ev['fecha'])) . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tipo de trabajo -->
                    <div class="form-group">
                        <label><i class="fas fa-tag me-2"></i>Tipo de Trabajo *</label>
                        <div class="tipo-trabajo">
                            <?php
                            $tipos = ['cartel' => 'fa-image', 'ponencia' => 'fa-chalkboard-teacher', 'taller' => 'fa-tools', 'prototipo' => 'fa-cube'];
                            foreach ($tipos as $valor => $icono):
                                $checked = ($proyecto['tipo_trabajo'] == $valor) ? 'checked' : '';
                            ?>
                            <label class="tipo-btn <?php echo $checked ? 'active' : ''; ?>">
                                <input type="radio" name="tipo" value="<?php echo $valor; ?>" <?php echo $checked; ?> required>
                                <i class="fas <?php echo $icono; ?>"></i>
                                <span><?php echo ucfirst($valor); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Categoría -->
                    <div class="form-group">
                        <label for="categoria"><i class="fas fa-layer-group me-2"></i>Categoría *</label>
                        <select class="form-select" id="categoria" name="categoria" required>
                            <option value="">Seleccione...</option>
                            <option value="ENSEÑANZA DE LAS MATEMÁTICAS" <?php echo ($proyecto['categoria'] == 'ENSEÑANZA DE LAS MATEMÁTICAS') ? 'selected' : ''; ?>>ENSEÑANZA DE LAS MATEMÁTICAS</option>
                            <option value="MATEMÁTICAS APLICADAS" <?php echo ($proyecto['categoria'] == 'MATEMÁTICAS APLICADAS') ? 'selected' : ''; ?>>MATEMÁTICAS APLICADAS</option>
                            <option value="MATEMÁTICAS PURAS" <?php echo ($proyecto['categoria'] == 'MATEMÁTICAS PURAS') ? 'selected' : ''; ?>>MATEMÁTICAS PURAS</option>
                            <option value="ESTADÍSTICA" <?php echo ($proyecto['categoria'] == 'ESTADÍSTICA') ? 'selected' : ''; ?>>ESTADÍSTICA</option>
                            <option value="COMPUTACIÓN" <?php echo ($proyecto['categoria'] == 'COMPUTACIÓN') ? 'selected' : ''; ?>>COMPUTACIÓN</option>
                            <option value="FÍSICA" <?php echo ($proyecto['categoria'] == 'FÍSICA') ? 'selected' : ''; ?>>FÍSICA</option>
                            <option value="INGENIERÍA" <?php echo ($proyecto['categoria'] == 'INGENIERÍA') ? 'selected' : ''; ?>>INGENIERÍA</option>
                            <option value="ECONOMÍA" <?php echo ($proyecto['categoria'] == 'ECONOMÍA') ? 'selected' : ''; ?>>ECONOMÍA</option>
                        </select>
                    </div>

                    <!-- Título -->
                    <div class="form-group">
                        <label for="titulo"><i class="fas fa-heading me-2"></i>Título *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($proyecto['titulo']); ?>" required maxlength="200">
                    </div>

                    <!-- Resumen -->
                    <div class="form-group">
                        <label for="resumen"><i class="fas fa-align-left me-2"></i>Resumen</label>
                        <textarea class="form-control" id="resumen" name="resumen" rows="5"><?php echo htmlspecialchars($proyecto['resumen']); ?></textarea>
                    </div>

                    <!-- Horario (se llenará vía AJAX) -->
                    <?php
                        // Definir qué tipos de trabajo tienen horario (todos los que están en tipo_actividad)
                        $tipos_con_horario = ['ponencia', 'taller', 'cartel', 'prototipo'];
                        ?>
                        <div class="form-group" id="horario-group" style="display: <?php echo in_array($proyecto['tipo_trabajo'], $tipos_con_horario) ? 'block' : 'none'; ?>;">
                        <label><i class="fas fa-clock me-2"></i>Horario de presentación *</label>
                        <select class="form-select" name="hora_inicio" id="hora_inicio" <?php echo in_array($proyecto['tipo_trabajo'], ['ponencia','taller','cartel','prototipo']) ? 'required' : 'disabled'; ?>>
                            <option value="">Seleccione horario</option>
                            <?php if ($actividad): ?>
                            <option value="<?php echo $actividad['hora_inicio']; ?>" selected>
                                <?php echo substr($actividad['hora_inicio'],0,5); ?> (actual)
                            </option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Los horarios se muestran según la duración del tipo de trabajo.</small>
                    </div>

                    <!-- Sección de coautores (similar a registrar_trabajos.php) -->
                    <div class="coautores-section">
                        <h5><i class="fas fa-users me-2"></i>Coautores <small class="text-muted">(Opcional)</small></h5>
                        <ul class="nav nav-tabs" id="coautoresTab">
                            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#internos">Coautores Internos</a></li>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#externos">Coautores Externos</a></li>
                        </ul>
                        <div class="tab-content">
                            <!-- Internos -->
                            <div class="tab-pane fade show active" id="internos">
                                <div id="coautores-internos-list">
                                    <?php foreach ($participantes_internos as $p): 
                                        // Si es el autor principal, no mostrarlo en la lista de coautores (no se puede quitar)
                                        $es_autor_principal = false;
                                        if ($id_especifico['tipo'] == 'alumno' && isset($p['id_alumno']) && $p['id_alumno'] == $id_especifico['id'] && $p['rol'] == 'autor') {
                                            $es_autor_principal = true;
                                        } elseif ($id_especifico['tipo'] == 'docente' && isset($p['id_docente']) && $p['id_docente'] == $id_especifico['id']) {
                                            $es_autor_principal = true;
                                        }
                                        if (!$es_autor_principal):
                                    ?>
                                    <div class="coautor-item">
                                        <select class="form-select coautor-select" name="coautores_internos[]">
                                            <option value="">Seleccionar coautor...</option>
                                            <?php foreach ($posibles_coautores as $co): ?>
                                            <option value="<?php echo $co['id_usuario']; ?>" <?php echo ($co['id_usuario'] == $p['id_usuario']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($co['nombre'] . ' ' . ($co['apellidos'] ?? '') . ' (' . ucfirst($co['tipo_usuario']) . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()"><i class="fas fa-times"></i></button>
                                    </div>
                                    <?php endif; endforeach; ?>
                                </div>
                                <button type="button" class="btn-add" id="btn-add-coautor-interno"><i class="fas fa-plus-circle"></i> Agregar Coautor Interno</button>
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-internos"><i class="fas fa-times-circle"></i> Quitar Todos</button>
                            </div>
                            <!-- Externos -->
                            <div class="tab-pane fade" id="externos">
                                <div id="coautores-externos-list">
                                    <?php foreach ($coautores_externos as $ce): ?>
                                    <div class="coautor-item">
                                        <div class="coautor-externo-grid">
                                            <input type="text" class="form-control" name="coautores_externos[][nombre]" value="<?php echo htmlspecialchars($ce['nombre']); ?>" placeholder="Nombre completo">
                                            <input type="text" class="form-control" name="coautores_externos[][rfc]" value="<?php echo htmlspecialchars($ce['rfc'] ?? ''); ?>" placeholder="RFC">
                                            <input type="email" class="form-control" name="coautores_externos[][email]" value="<?php echo htmlspecialchars($ce['email'] ?? ''); ?>" placeholder="Email">
                                            <input type="text" class="form-control" name="coautores_externos[][institucion]" value="<?php echo htmlspecialchars($ce['institucion'] ?? ''); ?>" placeholder="Institución">
                                        </div>
                                        <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()"><i class="fas fa-times"></i></button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add" id="btn-add-coautor-externo"><i class="fas fa-plus-circle"></i> Agregar Coautor Externo</button>
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-externos"><i class="fas fa-times-circle"></i> Quitar Todos</button>
                            </div>
                        </div>
                    </div>

                    <!-- Galería de imágenes existentes -->
                    <?php if (!empty($imagenes)): ?>
                    <div class="galeria-container">
                        <h5><i class="fas fa-images me-2"></i>Imágenes actuales</h5>
                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-secondary" id="seleccionar-todas">Seleccionar todas</button>
                            <button type="button" class="btn btn-sm btn-secondary" id="deseleccionar-todas">Deseleccionar todas</button>
                        </div>
                        <div class="imagenes-grid">
                            <?php foreach ($imagenes as $img): ?>
                            <div class="imagen-item <?php echo $img['es_principal'] ? 'principal' : ''; ?>" data-id="<?php echo $img['id_imagen']; ?>">
                                <?php if ($img['es_principal']): ?>
                                    <span class="imagen-badge"><i class="fas fa-star me-1"></i>Principal</span>
                                <?php endif; ?>
                                <a href="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" data-lightbox="proyecto">
                                    <img src="uploads/proyectos/<?php echo $img['nombre_archivo']; ?>" alt="Imagen">
                                </a>
                                <div class="imagen-actions mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="eliminar_imagenes[]" value="<?php echo $img['id_imagen']; ?>" id="img_<?php echo $img['id_imagen']; ?>">
                                        <label class="form-check-label text-danger" for="img_<?php echo $img['id_imagen']; ?>">
                                            <i class="fas fa-trash-alt me-1"></i>Eliminar
                                        </label>
                                    </div>
                                    <button type="button" class="btn btn-sm <?php echo $img['es_principal'] ? 'btn-success' : 'btn-primary'; ?> mt-1 btn-principal" 
                                        onclick="setPrincipal(<?php echo $img['id_imagen']; ?>)" 
                                        <?php echo $img['es_principal'] ? 'disabled' : ''; ?>>
                                        <?php if ($img['es_principal']): ?>
                                            <i class="fas fa-check me-1"></i>Principal
                                        <?php else: ?>
                                            <i class="fas fa-star me-1"></i>Hacer principal
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="imagen_principal" id="imagen_principal" value="<?php 
                            $principal = array_filter($imagenes, fn($i) => $i['es_principal']);
                            $principal = reset($principal);
                            echo $principal['id_imagen'] ?? '';
                        ?>">
                    </div>
                    <?php endif; ?>

                    <!-- Subir nuevas imágenes -->
                    <div class="form-group">
                        <label><i class="fas fa-plus-circle me-2"></i>Agregar nuevas imágenes</label>
                        <input type="file" class="form-control" name="imagenes[]" accept="image/*" multiple>
                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máx 5MB por imagen.</small>
                        <div id="vista-previa" class="row mt-3"></div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="acciones">
                        <a href="ver_proyecto.php?id=<?php echo $id_proyecto; ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>

                <!-- Formulario oculto para eliminar (opcional) -->
                <form method="POST" id="formEliminar" style="display:none;">
                    <input type="hidden" name="accion" value="eliminar_proyecto">
                </form>
            </div>
        </div>
    </div>

    <!-- Templates para coautores -->
    <template id="coautor-interno-template">
        <div class="coautor-item">
            <select class="form-select coautor-select" name="coautores_internos[]">
                <option value="">Seleccionar coautor...</option>
                <?php foreach ($posibles_coautores as $co): ?>
                <option value="<?php echo $co['id_usuario']; ?>">
                    <?php echo htmlspecialchars($co['nombre'] . ' ' . ($co['apellidos'] ?? '') . ' (' . ucfirst($co['tipo_usuario']) . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()"><i class="fas fa-times"></i></button>
        </div>
    </template>
    <template id="coautor-externo-template">
        <div class="coautor-item">
            <div class="coautor-externo-grid">
                <input type="text" class="form-control" name="coautores_externos[][nombre]" placeholder="Nombre completo">
                <input type="text" class="form-control" name="coautores_externos[][rfc]" placeholder="RFC">
                <input type="email" class="form-control" name="coautores_externos[][email]" placeholder="Email">
                <input type="text" class="form-control" name="coautores_externos[][institucion]" placeholder="Institución">
            </div>
            <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()"><i class="fas fa-times"></i></button>
        </div>
    </template>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('.coautor-select').select2({ theme: 'bootstrap-5', placeholder: 'Buscar coautor...', allowClear: true });

            // Resaltar tipo de trabajo
            $('.tipo-btn').on('click', function() {
                $('.tipo-btn').removeClass('active');
                $(this).addClass('active');
                var tipo = $(this).find('input').val();
                // Tipos que requieren horario (debe coincidir con $tipos_con_horario)
                var tiposConHorario = ['ponencia', 'taller', 'cartel', 'prototipo'];
                if (tiposConHorario.includes(tipo)) {
                    $('#horario-group').show();
                    $('#hora_inicio').prop('required', true).prop('disabled', false);
                    cargarHorarios(); // Recargar horarios disponibles para este tipo
                } else {
                    $('#horario-group').hide();
                    $('#hora_inicio').prop('required', false).prop('disabled', true);
                }
            });

            // Cargar horarios al cambiar evento
            $('#id_evento').change(cargarHorarios);

            function cargarHorarios() {
                var id_evento = $('#id_evento').val();
                var tipo = $('input[name="tipo"]:checked').val();
                if (id_evento && tipo) {
                    var $select = $('#hora_inicio');
                    $select.prop('disabled', true).html('<option>Cargando horarios...</option>');
                    $.getJSON('ajax/horarios_disponibles.php', { id_evento: id_evento, tipo_trabajo: tipo }, function(data) {
                        var valorActual = $select.data('actual') || '<?php echo $actividad['hora_inicio'] ?? ''; ?>';
                        $select.empty().append('<option value="">Seleccione horario</option>');
                        if (data.length > 0) {
                            $.each(data, function(i, hora) {
                                var selected = (hora === valorActual) ? 'selected' : '';
                                $select.append('<option value="' + hora + '" ' + selected + '>' + hora + '</option>');
                            });
                            $select.prop('disabled', false);
                        } else {
                            $select.append('<option value="">No hay horarios disponibles</option>');
                            $select.prop('disabled', true);
                        }
                    }).fail(function() {
                        $select.html('<option>Error al cargar horarios</option>');
                    });
                }
            }

            // Vista previa de imágenes
            $('#imagenes').change(function(e) {
                var $preview = $('#vista-previa');
                $preview.empty();
                var files = e.target.files;
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    if (file.type.startsWith('image/')) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            $preview.append('<div class="col-md-3 col-6 mb-3"><div class="card"><img src="' + e.target.result + '" class="card-img-top" style="height:150px; object-fit:cover;"><div class="card-body p-2"><p class="small text-truncate">' + file.name + '</p></div></div></div>');
                        }
                        reader.readAsDataURL(file);
                    }
                }
            });

            // Botones para agregar coautores
            $('#btn-add-coautor-interno').click(function() {
                var template = document.getElementById('coautor-interno-template').content.cloneNode(true);
                document.getElementById('coautores-internos-list').appendChild(template);
                $('.coautor-select').last().select2({ theme: 'bootstrap-5', placeholder: 'Buscar coautor...', allowClear: true });
            });
            $('#btn-add-coautor-externo').click(function() {
                var template = document.getElementById('coautor-externo-template').content.cloneNode(true);
                document.getElementById('coautores-externos-list').appendChild(template);
            });
            $('#btn-remove-all-internos').click(function() {
                if (confirm('¿Eliminar todos los coautores internos?')) $('#coautores-internos-list').empty();
            });
            $('#btn-remove-all-externos').click(function() {
                if (confirm('¿Eliminar todos los coautores externos?')) $('#coautores-externos-list').empty();
            });

            // Establecer imagen principal
            window.setPrincipal = function(id) {
                $('#imagen_principal').val(id);
                $('.imagen-item').removeClass('principal').each(function() {
                    $(this).find('.btn-principal').removeClass('activo').prop('disabled', false).html('<i class="fas fa-star me-1"></i>Hacer principal');
                    $(this).find('.imagen-badge').remove();
                });
                $('.imagen-item:has(input[value="'+id+'"])').addClass('principal').append('<span class="imagen-badge"><i class="fas fa-star me-1"></i>Principal</span>')
                    .find('.btn-principal').addClass('activo').prop('disabled', true).html('<i class="fas fa-check me-1"></i>Principal');
                $('.imagen-checkbox input').each(function() {
                    $(this).prop('disabled', $(this).val() == id);
                });
            };
        });

        // Resaltar imágenes marcadas para eliminar
            $(document).on('change', 'input[name="eliminar_imagenes[]"]', function() {
                $(this).closest('.imagen-item').toggleClass('marcado-eliminar', this.checked);
            });

// Seleccionar / deseleccionar todas
            $('#seleccionar-todas').click(function() {
                $('input[name="eliminar_imagenes[]"]:not(:disabled)').prop('checked', true).trigger('change');
            });
            $('#deseleccionar-todas').click(function() {
                $('input[name="eliminar_imagenes[]"]').prop('checked', false).trigger('change');
            });

            
    </script>
</body>
</html> 