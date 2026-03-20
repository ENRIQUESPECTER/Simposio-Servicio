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
    $id_salon = intval($_POST['id_salon'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $referencias = trim($_POST['referencias'] ?? '');
    // Archivo PDF se maneja aparte
    $coautores_internos = $_POST['coautores_internos'] ?? [];
    $coautores_externos = $_POST['coautores_externos'] ?? [];

    $errores = [];

    // Validaciones básicas
    if (empty($titulo)) $errores[] = "El título es obligatorio.";
    if (empty($tipo_trabajo)) $errores[] = "Debe seleccionar un tipo de trabajo.";
    if (empty($categoria)) $errores[] = "Debe seleccionar una categoría.";
    if (empty($id_evento)) $errores[] = "Debe seleccionar un evento.";
    if (empty($hora_inicio)) $errores[] = "Debe seleccionar un horario.";

    // Si no hay errores, proceder
    if (empty($errores)) {
        // Obtener duración y calcular hora_fin
        $duracion = duracion_tipo_trabajo($tipo_trabajo);
        $hora_fin = date("H:i:s", strtotime($hora_inicio) + $duracion * 60);

        // Verificar disponibilidad (por si acaso, aunque el AJAX ya debería filtrar)
        // Obtener fecha del evento
        $fecha_evento = '';
        foreach ($eventos as $e) {
            if ($e['id_evento'] == $id_evento) {
                $fecha_evento = $e['fecha'];
                break;
            }
        }

        // Verificar disponibilidad del horario en el salón seleccionado (en todos los eventos)
        $disponibles = obtener_horarios_disponibles($conexion, $id_evento, $duracion, $id_salon);
        if (!in_array($hora_inicio, $disponibles)) {
            $errores[] = "El horario seleccionado no está disponible para el salón elegido.";
        } else {
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
                    // Para empresa ya se guardó id_usuario en articulo
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

                // Insertar coautores externos
                foreach ($coautores_externos as $ext) {
                    if (!empty($ext['nombre'])) {
                        $stmt = $conexion->prepare("INSERT INTO coautor_externo (id_articulo, nombre, rfc, email, institucion) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issss", $id_articulo, $ext['nombre'], $ext['rfc'], $ext['email'], $ext['institucion']);
                        $stmt->execute();
                    }
                }

                // Manejo del archivo PDF
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

                // Insertar en actividad_evento (si aplica)
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
                                $stmt = $conexion->prepare("INSERT INTO proyecto_imagen (id_articulo, nombre_archivo, archivo_original, tipo_imagen, tamaño, es_principal) VALUES (?, ?, ?, ?, ?, ?)");
                                $tipo_img = mime_content_type($tmp_name);
                                $tamaño = $_FILES['imagenes']['size'][$key];
                                $principal = $es_principal ? 1 : 0;
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
                // Redirigir después de 2 segundos
                header("refresh:2;url=ver_proyecto.php?id=$id_articulo");
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje = "Error al registrar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } else {
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
    <link rel="stylesheet" href="Css/estilo1.css">
    <style>
        /* (puedes copiar los estilos que ya tenías de parte 1) */
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
        
        .colorazul { background-color: #293e6b !important; }
        .colordorado { background-color: #D59F0F !important; }
        
        .registro-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .registro-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .registro-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .registro-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .registro-body {
            padding: 40px;
        }
        
        /* Panel de límites */
        .limites-panel {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        
        .limites-panel h4 {
            color: #293e6b;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .limite-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .limite-item:last-child {
            border-bottom: none;
        }
        
        .limite-nombre {
            font-weight: 600;
            color: #495057;
        }
        
        .limite-valor {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .progreso {
            width: 150px;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .barra-progreso {
            height: 100%;
            background: linear-gradient(90deg, #293e6b, #D59F0F);
            transition: width 0.3s ease;
        }
        
        .contador {
            font-weight: 600;
            color: #293e6b;
            min-width: 50px;
            text-align: right;
        }
        
        /* Grupos de formulario */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #293e6b;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
            color: #212529;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #293e6b;
            box-shadow: 0 0 0 0.2rem rgba(41, 62, 107, 0.25);
            background-color: white;
        }
        
        /* Tipo de trabajo */
        .tipo-trabajo {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .tipo-btn {
            position: relative;
            padding: 20px 10px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .tipo-btn input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .tipo-btn i {
            font-size: 2rem;
            color: #293e6b;
            margin-bottom: 10px;
            display: block;
        }
        
        .tipo-btn span {
            display: block;
            font-weight: 600;
            color: #495057;
        }
        
        .tipo-btn.active {
            border-color: #293e6b;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(41, 62, 107, 0.2);
        }
        
        .tipo-btn.active i {
            color: #D59F0F;
        }
        
        /* Horarios */
        .horarios-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #dee2e6;
            display: none;
        }
        
        .horarios-container.mostrar {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fecha-grupo {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
        }
        
        .fecha-grupo h5 {
            color: #293e6b;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .horario-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .horario-item {
            position: relative;
        }
        
        .horario-item input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .horario-item label {
            display: block;
            padding: 12px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            margin: 0;
        }
        
        .horario-item input[type="radio"]:checked + label {
            border-color: #293e6b;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 62, 107, 0.2);
        }
        
        .horario-item label:hover {
            border-color: #293e6b;
            transform: translateY(-2px);
        }
        
        .horario-hora {
            font-weight: 600;
            color: #293e6b;
            margin-bottom: 5px;
        }
        
        .horario-salon {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        /* Coautores */
        .coautores-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border: 2px solid #dee2e6;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 600;
            border: none;
            padding: 10px 20px;
            margin-right: 5px;
            border-radius: 10px 10px 0 0;
            background: #e9ecef;
        }
        
        .nav-tabs .nav-link:hover {
            color: #293e6b;
            background: #dee2e6;
        }
        
        .nav-tabs .nav-link.active {
            color: #293e6b;
            background: white;
            border: 2px solid #dee2e6;
            border-bottom-color: white;
            transform: translateY(2px);
        }
        
        .tab-pane {
            padding: 20px;
            background: white;
            border-radius: 0 10px 10px 10px;
            border: 2px solid #dee2e6;
            border-top: none;
        }
        
        .coautor-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .coautor-item select,
        .coautor-item input {
            flex: 1;
        }
        
        .coautor-externo-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            flex: 1;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-remove:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .btn-add {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
            margin-top: 15px;
        }
        
        .btn-add:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Vista previa imágenes */
        .imagenes-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border: 2px dashed #293e6b;
            text-align: center;
        }
        
        .vista-previa-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .badge-principal {
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
        
        /* Acciones */
        .acciones {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 62, 107, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        /* Mejoras de contraste */
        .form-control::placeholder {
            color: #6c757d;
            opacity: 1;
        }
        
        select.form-control option {
            color: #212529;
            background: white;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            background-color: white;
            border: 2px solid #dee2e6;
            color: #212529;
        }
        
        /* Espaciado */
        .mt-4 {
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .registro-body {
                padding: 20px;
            }
            
            .tipo-trabajo {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .horario-grid {
                grid-template-columns: 1fr;
            }
            
            .coautor-item {
                flex-direction: column;
            }
            
            .coautor-externo-grid {
                grid-template-columns: 1fr;
                width: 100%;
            }
            
            .acciones {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

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
                            <option value="MATEMÁTICAS APLICADAS">MATEMÁTICAS APLICADAS</option>
                            <option value="MATEMÁTICAS APLICADAS">MATEMÁTICAS PURA</option>
                            <option value="MATEMÁTICAS APLICADAS">ESTADISTICA</option>
                            <option value="MATEMÁTICAS APLICADAS">COMPUTACION</option>
                            <option value="MATEMÁTICAS APLICADAS">FISICA</option>
                            <option value="MATEMÁTICAS APLICADAS">INGENIERIA</option>
                            <option value="MATEMÁTICAS APLICADAS">ECONOMIA</option>
                            <!-- ... resto de categorías ... -->
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
                    <div class="form-group">
                        <label for="id_salon"><i class="fas fa-door-open me-2"></i>Salón *</label>
                        <select class="form-select" id="id_salon" name="id_salon" required>
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
                <?php foreach($posibles_coautores_internos as $coautor): ?>
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
                <input type="text" class="form-control" name="coautores_externos[][nombre]" 
                       placeholder="Nombre completo">
                <input type="text" class="form-control" name="coautores_externos[][rfc]" 
                       placeholder="RFC (opcional)" maxlength="13" 
                       pattern="[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}" 
                       title="Formato: 4 letras + 6 dígitos + 3 dígitos">
                <input type="email" class="form-control" name="coautores_externos[][email]" 
                       placeholder="Email (opcional)">
                <input type="text" class="form-control" name="coautores_externos[][institucion]" 
                       placeholder="Institución (opcional)">
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
            // Cuando cambie el evento o el tipo de trabajo, actualizar horarios
            function cargarHorarios() {
                var id_evento = $('#id_evento').val();
                var tipo = $('input[name="tipo"]:checked').val();
                if (id_evento && tipo) {
                    $.getJSON('ajax/horarios_disponibles.php', { id_evento: id_evento, tipo_trabajo: tipo }, function(data) {
                        var $select = $('#hora_inicio');
                        $select.empty().append('<option value="">Seleccione horario</option>');
                        if (data.length > 0) {
                            $.each(data, function(i, hora) {
                                $select.append('<option value="' + hora + '">' + hora + '</option>');
                            });
                            $('#horario-group').show();
                            $select.prop('disabled', false);
                        } else {
                            $select.append('<option value="">No hay horarios disponibles</option>');
                            $('#horario-group').show();
                            $select.prop('disabled', true);
                        }
                    });
                } else {
                    $('#horario-group').hide();
                }
            }

            $('#id_evento').change(cargarHorarios);
            $('input[name="tipo"]').change(cargarHorarios);

            // Inicializar Select2 para coautores (si se usa)
            $('.coautor-select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Buscar coautor...',
                allowClear: true
            });

            // Botones para agregar coautores (copiar de parte 1)
            // ...
        });
        // Coautores internos
            document.getElementById('btn-add-coautor-interno').addEventListener('click', function() {
                const template = document.getElementById('coautor-interno-template');
                if (template) {
                    const clone = template.content.cloneNode(true);
                    document.getElementById('coautores-internos-list').appendChild(clone);
                    
                    setTimeout(function() {
                        $('.coautor-select').last().select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Buscar coautor...',
                            allowClear: true,
                            width: '100%'
                        });
                    }, 100);
                }
            });

            // Quitar todos los coautores internos
            document.getElementById('btn-remove-all-internos').addEventListener('click', function() {
                if (confirm('¿Está seguro de eliminar todos los coautores internos?')) {
                    document.getElementById('coautores-internos-list').innerHTML = '';
                }
            });

            // Coautores externos
            document.getElementById('btn-add-coautor-externo').addEventListener('click', function() {
                const template = document.getElementById('coautor-externo-template');
                if (template) {
                    const clone = template.content.cloneNode(true);
                    document.getElementById('coautores-externos-list').appendChild(clone);
                }
            });

            // Quitar todos los coautores externos
            document.getElementById('btn-remove-all-externos').addEventListener('click', function() {
                if (confirm('¿Está seguro de eliminar todos los coautores externos?')) {
                    document.getElementById('coautores-externos-list').innerHTML = '';
                }
            });
            // Resaltar tipo de trabajo seleccionado
            $('.tipo-btn').on('click', function() {
                $('.tipo-btn').removeClass('active');
                $(this).addClass('active');
            });
            // Al cargar la página, marcar el que esté preseleccionado
            $(document).ready(function() {
                $('input[name="tipo"]:checked').closest('.tipo-btn').addClass('active');
            });
    </script>
</body>
</html>