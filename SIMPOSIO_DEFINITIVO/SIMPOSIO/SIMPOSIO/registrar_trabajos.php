<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';

// Verificar que el usuario esté logueado
if (!esta_logeado()) {
    header('Location: login.php');
    exit;
}

if (es_empresa()) {
    header('Location: index.html');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}


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

// Obtener eventos activos (futuros o todos)
$eventos = [];
$result_eventos = $conexion->query("SELECT id_evento, titulo, fecha FROM evento WHERE fecha >= CURDATE() ORDER BY fecha");
while ($row = $result_eventos->fetch_assoc()) {
    $eventos[] = $row;
}

// Obtener salones disponibles
$salones = obtener_salones($conexion);

// Posibles coautores (todos los usuarios excepto el actual)
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


// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recuperar datos
    $id_evento = intval($_POST['id_evento'] ?? 0);
    $tipo_trabajo = $_POST['tipo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $resumen = trim($_POST['resumen'] ?? '');
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $id_salon = !empty($_POST['id_salon']) ? intval($_POST['id_salon']) : null;
    $descripcion = trim($_POST['descripcion'] ?? '');
    $referencias = trim($_POST['referencias'] ?? '');
    $coautores_internos = $_POST['coautores_internos'] ?? [];
    $coautores_externos = $_POST['coautores_externos'] ?? [];

    $errores = [];

    // Validaciones básicas
    if (empty($titulo)) $errores[] = "El título es obligatorio.";
    if (empty($tipo_trabajo)) $errores[] = "Debe seleccionar un tipo de trabajo.";
    if (empty($categoria)) $errores[] = "Debe seleccionar una categoría.";
    if (empty($id_evento)) $errores[] = "Debe seleccionar un evento.";
    if (empty($hora_inicio)) $errores[] = "Debe seleccionar un horario.";

    if (empty($errores)) {
        // Obtener duración y calcular hora_fin
        $duracion = duracion_tipo_trabajo($tipo_trabajo);
        $hora_fin = date("H:i:s", strtotime($hora_inicio) + $duracion * 60);

        // Obtener fecha del evento
        $fecha_evento = '';
        foreach ($eventos as $e) {
            if ($e['id_evento'] == $id_evento) {
                $fecha_evento = $e['fecha'];
                break;
            }
        }

        // 1. Validar horario base (sin considerar salón)
        $disponibles = obtener_horarios_disponibles($conexion, $id_evento, $duracion);
        if (!in_array($hora_inicio, $disponibles)) {
            $errores[] = "El horario seleccionado no está disponible.";
        }

        // 2. Validar salón (si se seleccionó)
        if ($id_salon) {
            $disponibles_salon = obtener_horarios_disponibles($conexion, $id_evento, $duracion, $id_salon);
            if (!in_array($hora_inicio, $disponibles_salon)) {
                $errores[] = "El salón seleccionado no está disponible en ese horario (considerando todos los eventos).";
            }
        }

        if (empty($errores)) {
            // Iniciar transacción
            $conexion->begin_transaction();

            try {
                // Insertar en articulo
                $stmt = $conexion->prepare("INSERT INTO articulo (id_evento, id_usuario, titulo, resumen, tipo_trabajo, categoria) VALUES (?, ?, ?, ?, ?, ?)");
                $id_usuario_autor = $_SESSION['id_usuario'];
                $stmt->bind_param("iissss", $id_evento, $id_usuario_autor, $titulo, $resumen, $tipo_trabajo, $categoria);
                $stmt->execute();
                $id_articulo = $conexion->insert_id;

                // Insertar autor principal
                if ($id_especifico) {
                    if ($id_especifico['tipo'] == 'alumno') {
                        $stmt = $conexion->prepare("INSERT INTO articulo_alumno (id_articulo, id_alumno, rol) VALUES (?, ?, 'autor')");
                        $stmt->bind_param("ii", $id_articulo, $id_especifico['id']);
                        $stmt->execute();
                    } elseif ($id_especifico['tipo'] == 'docente') {
                        $stmt = $conexion->prepare("INSERT INTO articulo_docente (id_articulo, id_docente) VALUES (?, ?)");
                        $stmt->bind_param("ii", $id_articulo, $id_especifico['id']);
                        $stmt->execute();
                    }
                }

                // Insertar coautores internos
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
                            $stmt3->bind_param("ii", $id_articulo, $id_alumno);
                            $stmt3->execute();
                        }
                    } elseif ($tipo_coautor == 'docente') {
                        $stmt2 = $conexion->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
                        $stmt2->bind_param("i", $id_coautor_usuario);
                        $stmt2->execute();
                        $id_docente = $stmt2->get_result()->fetch_assoc()['id_docente'];
                        if ($id_docente) {
                            $stmt3 = $conexion->prepare("INSERT INTO articulo_docente (id_articulo, id_docente) VALUES (?, ?)");
                            $stmt3->bind_param("ii", $id_articulo, $id_docente);
                            $stmt3->execute();
                        }
                    }
                }

                // Insertar coautores externos (con todos los campos)
                // Insertar coautores externos
                foreach ($coautores_externos as $ext) {
                    if (!empty($ext['nombre'])) {
                        $nombre = $ext['nombre'];
                        $rfc = !empty($ext['rfc']) ? $ext['rfc'] : null;
                        $email = !empty($ext['email']) ? $ext['email'] : null;
                        $institucion = !empty($ext['institucion']) ? $ext['institucion'] : null;
                        $stmt = $conexion->prepare("INSERT INTO coautor_externo (id_articulo, nombre, rfc, email, institucion) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issss", $id_articulo, $nombre, $rfc, $email, $institucion);
                        $stmt->execute();
                    }
                }

                // Subir PDF
                $ruta_pdf = null;
                if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] == 0) {
                    $carpeta_pdf = 'uploads/actividades/';
                    if (!file_exists($carpeta_pdf)) {
                        mkdir($carpeta_pdf, 0777, true);
                    }
                    $nombre_pdf = uniqid() . '_' . time() . '_' . basename($_FILES['archivo_pdf']['name']);
                    $ruta_pdf = $carpeta_pdf . $nombre_pdf;
                    move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $ruta_pdf);
                }

                // Insertar en actividad_evento (incluyendo descripción, referencias, PDF)
                $id_tipo_actividad = tipo_trabajo_a_id_actividad($tipo_trabajo);
                if ($id_tipo_actividad) {
                    $stmt = $conexion->prepare("INSERT INTO actividad_evento 
                        (id_evento, id_usuario, id_tipo, id_articulo, titulo, resumen, descripcion, referencias, archivo_pdf, fecha, hora_inicio, hora_fin, id_salon) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiiissssssssi", $id_evento, $_SESSION['id_usuario'], $id_tipo_actividad, $id_articulo, $titulo, $resumen, $descripcion, $referencias, $ruta_pdf, $fecha_evento, $hora_inicio, $hora_fin, $id_salon);
                    $stmt->execute();
                }

                // Subir imágenes
                if (!empty($_FILES['imagenes']['name'][0])) {
                    $carpeta = 'uploads/proyectos/';
                    if (!file_exists($carpeta)) {
                        mkdir($carpeta, 0777, true);
                    }
                    $es_principal = true;
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
                                $stmt->bind_param("isssii", $id_articulo, $nombre_archivo, $nombre_original, $tipo_img, $tamaño, $principal);
                                $stmt->execute();
                                $es_principal = false;
                            }
                        }
                    }
                }

                $conexion->commit();
                $mensaje = "Trabajo registrado exitosamente.";
                $tipo_mensaje = "success";
                header("refresh:2;url=ver_proyecto.php?id=$id_articulo");
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = "Error al registrar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    }

    if (!empty($errores)) {
        $mensaje = implode("<br>", $errores);
        $tipo_mensaje = "warning";
    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Trabajo - SIMPOSIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    
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
                    <li class="nav-item"><a class="nav-link" href="mis_proyectos.php"><i class="fas fa-project-diagram me-1"></i>Mis Proyectos</a></li>
                    <li class="nav-item"><a class="nav-link" href="registrar_trabajos.php"><i class="fas fa-upload me-1"></i>Registrar Trabajo</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="mis_proyectos.php"><i class="fas fa-project-diagram me-2"></i>Mis Proyectos</a></li>
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

    <div class="registro-container">
        <div class="registro-card">
            <div class="registro-header">
                <h2><i class="fas fa-upload me-3"></i>Registrar Nuevo Trabajo</h2>
            </div>
            <div class="registro-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo $mensaje; ?>
                </div>
                <?php endif; ?>
                

                <form method="POST" id="formRegistro" enctype="multipart/form-data">
                    <!-- Evento -->
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt me-2"></i>Evento *</label>
                        <select class="form-select" name="id_evento" id="id_evento" required>
                            <option value="">Seleccione un evento...</option>
                            <?php foreach ($eventos as $ev): ?>
                            <option value="<?php echo $ev['id_evento']; ?>" data-fecha="<?php echo $ev['fecha']; ?>">
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
                            ?>
                            <label class="tipo-btn">
                                <input type="radio" name="tipo" value="<?php echo $valor; ?>" required>
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
                            <option value="ENSEÑANZA DE LAS MATEMÁTICAS">ENSEÑANZA DE LAS MATEMÁTICAS</option>
                            <option value="CIBERSEGURIDAD">CIBERSEGURIDAD</option>
                            <option value="MATEMÁTICAS PURAS">MATEMÁTICAS PURAS</option>
                            <option value="ESTADÍSTICA">ESTADÍSTICA</option>
                            <option value="COMPUTACIÓN">COMPUTACIÓN</option>
                            <option value="INTELIGENCIA ARTIFICIAL">INTELIGENCIA ARTIFICIAL</option>
                            <option value="INGENIERÍA">INGENIERÍA</option>
                            <option value="MINERIA DE DATOS">MINERIA DE DATOS</option>
                        </select>
                    </div>

                    <!-- Título -->
                    <div class="form-group">
                        <label for="titulo"><i class="fas fa-heading me-2"></i>Título *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="200">
                    </div>

                    <!-- Resumen -->
                    <div class="form-group">
                        <label for="resumen"><i class="fas fa-align-left me-2"></i>Resumen</label>
                        <textarea class="form-control" id="resumen" name="resumen" rows="5"></textarea>
                    </div>

                    <!-- Horario (se llenará vía AJAX) -->
                    <div class="form-group" id="horario-group" style="display: none;">
                        <label><i class="fas fa-clock me-2"></i>Horario de presentación *</label>
                        <select class="form-select" name="hora_inicio" id="hora_inicio" required disabled>
                            <option value="">Primero seleccione evento y tipo</option>
                        </select>
                        <small class="text-muted">Los horarios se muestran según la duración del tipo de trabajo.</small>
                    </div>

                    <!-- Selector de salón -->
                    <div class="form-group" id="salon-group" style="display: none;">
                        <label for="id_salon"><i class="fas fa-door-open me-2"></i>Salón *</label>
                        <select class="form-select" id="salon" name="id_salon" required>
                            <option value="">Seleccione un salón</option>
                            <?php foreach ($salones as $salon): ?>
                            <option value="<?php echo $salon['id_salon']; ?>" <?php echo (isset($_POST['id_salon']) && $_POST['id_salon'] == $salon['id_salon']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($salon['nombre']); ?>
                                <?php if (!empty($salon['ubicacion'])): ?> - <?php echo htmlspecialchars($salon['ubicacion']); ?><?php endif; ?>
                                (Cap. <?php echo $salon['capacidad']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Descripción (siempre visible) -->
                    <div class="form-group">
                        <label for="descripcion"><i class="fas fa-align-left me-2"></i>Descripción (opcional)</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                    </div>

                    <!-- Referencias (siempre visible) -->
                    <div class="form-group">
                        <label for="referencias"><i class="fas fa-book me-2"></i>Referencias (opcional)</label>
                        <textarea class="form-control" id="referencias" name="referencias" rows="3"><?php echo isset($_POST['referencias']) ? htmlspecialchars($_POST['referencias']) : ''; ?></textarea>
                    </div>

                    <!-- Archivo PDF (siempre visible) -->
                    <div class="form-group">
                        <label for="archivo_pdf"><i class="fas fa-file-pdf me-2"></i>Archivo PDF (opcional)</label>
                        <input type="file" class="form-control" id="archivo_pdf" name="archivo_pdf" accept=".pdf,application/pdf">
                        <small class="text-muted">Tamaño máximo: 10 MB.</small>
                    </div>

                    <!-- Sección de Coautores (OPCIONAL) -->
                    <div class="coautores-section">
                        <h5><i class="fas fa-users me-2"></i>Coautores <small class="text-muted">(Opcional)</small></h5>
                        
                        <ul class="nav nav-tabs" id="coautoresTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="internos-tab" data-bs-toggle="tab" data-bs-target="#internos" type="button" role="tab">
                                    <i class="fas fa-user-friends me-2"></i>Coautores Internos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="externos-tab" data-bs-toggle="tab" data-bs-target="#externos" type="button" role="tab">
                                    <i class="fas fa-user-tie me-2"></i>Coautores Externos
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="coautoresTabContent">
                            <!-- Coautores Internos -->
                            <div class="tab-pane fade show active" id="internos" role="tabpanel">
                                <div id="coautores-internos-list">
                                    <!-- Los coautores internos se agregarán aquí -->
                                </div>
                                
                                <button type="button" class="btn-add" id="btn-add-coautor-interno">
                                    <i class="fas fa-plus-circle"></i>
                                    Agregar Coautor Interno
                                </button>
                                
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-internos">
                                    <i class="fas fa-times-circle"></i>
                                    Quitar Todos
                                </button>
                                
                                <small class="text-muted d-block mt-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Coautores que ya están registrados en el sistema (alumnos, docentes o empresas).
                                </small>
                            </div>
                            
                            <!-- Coautores Externos -->
                            <div class="tab-pane fade" id="externos" role="tabpanel">
                                <div id="coautores-externos-list">
                                    <!-- Los coautores externos se agregarán aquí -->
                                </div>
                                
                                <button type="button" class="btn-add" id="btn-add-coautor-externo">
                                    <i class="fas fa-plus-circle"></i>
                                    Agregar Coautor Externo
                                </button>
                                
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-externos">
                                    <i class="fas fa-times-circle"></i>
                                    Quitar Todos
                                </button>
                                
                                <small class="text-muted d-block mt-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    RFC debe tener formato: 4 letras + 6 dígitos + 3 dígitos (ej: ABCD123456XYZ)
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Subir imágenes (OPCIONAL) -->
                    <div class="form-group mt-4">
                        <label><i class="fas fa-images me-2"></i>Imágenes del proyecto <small class="text-muted">(Opcional)</small></label>
                        <div class="imagenes-container">
                            <div class="mb-3">
                                <input type="file" class="form-control" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/gif" multiple>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Puedes seleccionar múltiples imágenes. Formatos permitidos: JPG, PNG, GIF. 
                                    Tamaño máximo: 5MB por imagen. La primera imagen será la principal.
                                </small>
                            </div>
                            
                            <!-- Vista previa de imágenes -->
                            <div id="vista-previa" class="row mt-3"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Registrar Trabajo</button>
                    <a href="mis_proyectos.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <!-- Templates -->
    <template id="coautor-interno-template">
        <div class="coautor-item">
            <select class="form-select coautor-select" name="coautores_internos[]">
                <option value="">Seleccionar coautor...</option>
                <?php foreach($posibles_coautores as $coautor): ?>
                <option value="<?php echo $coautor['id_usuario']; ?>">
                    <?php 
                    $tipo = ucfirst($coautor['tipo_usuario']);
                    $info = '';
                    if ($coautor['tipo_usuario'] == 'alumno' && !empty($coautor['matricula'])) {
                        $info = " - Mat: {$coautor['matricula']}";
                    } elseif ($coautor['tipo_usuario'] == 'docente' && !empty($coautor['especialidad'])) {
                        $info = " - {$coautor['especialidad']}";
                    } elseif ($coautor['tipo_usuario'] == 'empresa' && !empty($coautor['nombre_empresa'])) {
                        $info = " - {$coautor['nombre_empresa']}";
                    }
                    echo htmlspecialchars($coautor['nombre'] . ' ' . $coautor['apellidos'] . " ($tipo$info)");
                    ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>

    <template id="coautor-externo-template">
        <div class="coautor-item">
            <div class="coautor-externo-grid">
                <input type="text" class="form-control coautor-nombre" name="coautores_externos[INDEX][nombre]" placeholder="Nombre completo">
                <input type="text" class="form-control coautor-rfc" name="coautores_externos[INDEX][rfc]" placeholder="RFC (opcional)" maxlength="13">
                <input type="email" class="form-control coautor-email" name="coautores_externos[INDEX][email]" placeholder="Email (opcional)">
                <input type="text" class="form-control coautor-institucion" name="coautores_externos[INDEX][institucion]" placeholder="Institución (opcional)">
            </div>
            <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // ========== FUNCIONES DE HORARIOS Y SALONES ==========
    window.cargarHorarios = function() {
        var id_evento = $('#id_evento').val();
        var tipo = $('input[name="tipo"]:checked').val();
        if (!id_evento || !tipo) {
            $('#horario-group, #salon-group').hide();
            return;
        }
        var $select = $('#hora_inicio');
        $select.prop('disabled', true).html('<option>Cargando horarios...</option>');
        $.getJSON('ajax/horarios_disponibles.php', { id_evento: id_evento, tipo_trabajo: tipo }, function(data) {
            $select.empty().append('<option value="">Seleccione horario</option>');
            if (data.length) {
                $.each(data, function(i, hora) {
                    $select.append('<option value="' + hora + '">' + hora + '</option>');
                });
                $select.prop('disabled', false);
                $('#horario-group').show();
                if ($select.val()) window.cargarSalones($select.val());
                else $('#salon-group').hide();
            } else {
                $select.append('<option value="">No hay horarios disponibles</option>');
                $select.prop('disabled', true);
                $('#horario-group, #salon-group').show();
            }
        }).fail(() => {
            $select.html('<option>Error al cargar horarios</option>');
            $('#horario-group, #salon-group').show();
        });
    };

    window.cargarSalones = function(hora) {
        var id_evento = $('#id_evento').val();
        var tipo = $('input[name="tipo"]:checked').val();
        if (!id_evento || !tipo || !hora) {
            $('#salon-group').hide();
            return;
        }
        var $salon = $('#salon');
        $salon.prop('disabled', true).html('<option>Cargando salones...</option>');
        $.getJSON('ajax/horarios_disponibles.php', { id_evento: id_evento, tipo_trabajo: tipo, hora: hora }, function(data) {
            $salon.empty().append('<option value="">Seleccione un salón</option>');
            if (data.length) {
                $.each(data, function(i, s) {
                    $salon.append('<option value="' + s.id_salon + '">' + s.nombre + '</option>');
                });
                $salon.prop('disabled', false);
                $('#salon-group').show();
            } else {
                $salon.append('<option value="">No hay salones disponibles</option>');
                $salon.prop('disabled', true);
                $('#salon-group').show();
            }
        }).fail(() => {
            $salon.html('<option>Error al cargar salones</option>');
            $('#salon-group').show();
        });
    };

    // ========== EVENTOS ==========
    $('#id_evento, input[name="tipo"]').on('change', cargarHorarios);
    $('#hora_inicio').on('change', function() {
        var hora = $(this).val();
        if (hora) window.cargarSalones(hora);
        else $('#salon-group').hide();
    });

    // Resaltar tipo de trabajo
    $('.tipo-btn').on('click', function() {
        $('.tipo-btn').removeClass('active');
        $(this).addClass('active');
        var tipo = $(this).find('input').val();
        var tiposConHorario = ['ponencia', 'taller', 'cartel', 'prototipo'];
        if (tiposConHorario.includes(tipo)) {
            $('#horario-group').show();
            $('#hora_inicio').prop('required', true);
            cargarHorarios();
        } else {
            $('#horario-group, #salon-group').hide();
            $('#hora_inicio').prop('required', false);
        }
    });

    // ========== COAUTORES ==========
    // Inicializar Select2 en los selects de coautores
    $('.coautor-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar coautor...',
        allowClear: true
    });

    // Agregar coautor interno
    $('#btn-add-coautor-interno').click(function() {
        var template = document.getElementById('coautor-interno-template').content.cloneNode(true);
        $('#coautores-internos-list').append(template);
        $('.coautor-select').last().select2({
            theme: 'bootstrap-5',
            placeholder: 'Buscar coautor...',
            allowClear: true
        });
    });

    // Agregar coautor externo
    // Contador para índices de coautores externos
    let coautorExternoIndex = 0;

    $('#btn-add-coautor-externo').click(function() {
        var template = document.getElementById('coautor-externo-template').content.cloneNode(true);
        // Reemplazar INDEX por el número actual en los nombres de los inputs
        $(template).find('input').each(function() {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace('INDEX', coautorExternoIndex));
            }
        });
        $('#coautores-externos-list').append(template);
        coautorExternoIndex++;
    });

    // Quitar todos los coautores internos
    $('#btn-remove-all-internos').click(function() {
        if (confirm('¿Eliminar todos los coautores internos?')) {
            $('#coautores-internos-list').empty();
        }
    });

    // Quitar todos los coautores externos
    $('#btn-remove-all-externos').click(function() {
        if (confirm('¿Eliminar todos los coautores externos?')) {
            $('#coautores-externos-list').empty();
        }
    });

    // ========== VISTA PREVIA DE IMÁGENES ==========
    $('#imagenes').on('change', function(e) {
        var $preview = $('#vista-previa');
        $preview.empty();
        var files = e.target.files;
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = (function(f) {
                    return function(e) {
                        $preview.append('<div class="col-md-3 col-6 mb-3"><div class="card"><img src="' + e.target.result + '" class="card-img-top" style="height:150px; object-fit:cover;"><div class="card-body p-2"><p class="small text-truncate">' + f.name + '</p></div></div></div>');
                    };
                })(file);
                reader.readAsDataURL(file);
            }
        }
    });

    // ========== ENVÍO DEL FORMULARIO ==========
    $('#formRegistro').on('submit', function(e) {
        console.log('Enviando formulario...');
        return true;
    });

    // Inicialización (si estamos editando)
    if ($('#id_evento').val() && $('input[name="tipo"]:checked').val()) {
        cargarHorarios();
        var horaActual = $('#hora_inicio').data('actual');
        if (horaActual) setTimeout(() => cargarSalones(horaActual), 500);
    }
});
</script>
<script>
document.getElementById('formRegistro').addEventListener('submit', function(e) {
    console.log('Evento submit capturado');
    // No cancelamos el envío
});
</script>
</body>
</html>