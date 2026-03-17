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

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT u.*, 
                      a.id_alumno, a.matricula, a.carrera, a.semestre,
                      d.id_docente, d.especialidad, d.grado_academico,
                      e.id_empresa, e.nombre_empresa, e.sector
                      FROM usuario u
                      LEFT JOIN alumno a ON u.id_usuario = a.id_usuario
                      LEFT JOIN docente d ON u.id_usuario = d.id_usuario
                      LEFT JOIN empresa e ON u.id_usuario = e.id_usuario
                      WHERE u.id_usuario = ?");
$stmt->execute([$_SESSION['id_usuario']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$tipo_usuario = $usuario['tipo_usuario'];

// Inicializar variables para evitar errores
$participantes_internos = [];
$coautores_externos = [];
$imagenes = [];
$horario_asignado = null;
$horarios_disponibles = [];
$posibles_coautores_internos = [];

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
    
    // Verificar que el usuario sea propietario del proyecto
    $es_propietario = false;
    
    if ($tipo_usuario == 'alumno' && isset($usuario['id_alumno'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM proyecto_alumno WHERE id_proyecto = ? AND id_alumno = ? AND rol = 'autor'");
        $stmt->execute([$id_proyecto, $usuario['id_alumno']]);
        $es_propietario = $stmt->fetchColumn() > 0;
    } elseif ($tipo_usuario == 'docente' && isset($usuario['id_docente'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM proyecto_docente WHERE id_proyecto = ? AND id_docente = ?");
        $stmt->execute([$id_proyecto, $usuario['id_docente']]);
        $es_propietario = $stmt->fetchColumn() > 0;
    } elseif ($tipo_usuario == 'empresa') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM articulo WHERE id_articulo = ? AND id_usuario = ?");
        $stmt->execute([$id_proyecto, $_SESSION['id_usuario']]);
        $es_propietario = $stmt->fetchColumn() > 0;
    }
    
    if (!$es_propietario && !isset($_SESSION['es_admin'])) {
        header('Location: mis_proyectos.php');
        exit;
    }
    
    // Obtener autores y coautores internos (alumnos y docentes)
    try {
        // Obtener alumnos participantes
        $stmt = $pdo->prepare("
            SELECT pa.*, u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
                   a.matricula, a.carrera, NULL as especialidad, NULL as grado_academico
            FROM proyecto_alumno pa
            JOIN alumno a ON pa.id_alumno = a.id_alumno
            JOIN usuario u ON a.id_usuario = u.id_usuario
            WHERE pa.id_proyecto = ?
        ");
        $stmt->execute([$id_proyecto]);
        $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener docentes participantes
        $stmt = $pdo->prepare("
            SELECT pd.*, u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
                   NULL as matricula, NULL as carrera, d.especialidad, d.grado_academico
            FROM proyecto_docente pd
            JOIN docente d ON pd.id_docente = d.id_docente
            JOIN usuario u ON d.id_usuario = u.id_usuario
            WHERE pd.id_proyecto = ?
        ");
        $stmt->execute([$id_proyecto]);
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combinar resultados
        $participantes_internos = array_merge($alumnos, $docentes);
        
    } catch (PDOException $e) {
        error_log("Error al obtener participantes: " . $e->getMessage());
        $participantes_internos = [];
    }
    
    // Obtener coautores externos
    try {
        $stmt = $pdo->prepare("SELECT * FROM coautor_externo WHERE id_proyecto = ?");
        $stmt->execute([$id_proyecto]);
        $coautores_externos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener coautores externos: " . $e->getMessage());
        $coautores_externos = [];
    }
    
    // Obtener horario asignado si es ponencia
    if ($proyecto['tipo_trabajo'] == 'ponencia') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM horario_ponencia WHERE id_proyecto = ?");
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
    
    // Obtener lista de posibles coautores internos
    try {
        $stmt = $pdo->prepare("
            SELECT u.id_usuario, u.nombre, u.apellidos, u.correo, u.tipo_usuario,
                   a.matricula, d.especialidad, e.nombre_empresa
            FROM usuario u
            LEFT JOIN alumno a ON u.id_usuario = a.id_usuario AND u.tipo_usuario = 'alumno'
            LEFT JOIN docente d ON u.id_usuario = d.id_usuario AND u.tipo_usuario = 'docente'
            LEFT JOIN empresa emp ON u.id_usuario = emp.id_usuario AND u.tipo_usuario = 'empresa'
            WHERE u.id_usuario != ?
            ORDER BY u.nombre
        ");
        $stmt->execute([$_SESSION['id_usuario']]);
        $posibles_coautores_internos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener posibles coautores: " . $e->getMessage());
        $posibles_coautores_internos = [];
    }
    
    // Obtener horarios disponibles (incluyendo el actual si está ocupado)
    if ($proyecto['tipo_trabajo'] == 'ponencia') {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM horario_ponencia 
                WHERE estado = 'disponible' OR id_proyecto = ?
                ORDER BY fecha, hora_inicio
            ");
            $stmt->execute([$id_proyecto]);
            $horarios_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener horarios disponibles: " . $e->getMessage());
            $horarios_disponibles = [];
        }
    }
    
} catch (PDOException $e) {
    $mensaje = "Error al cargar el proyecto: " . $e->getMessage();
    $tipo_mensaje = "danger";
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    try {
        $pdo->beginTransaction();
        
        if ($_POST['accion'] == 'actualizar_proyecto') {
            // Actualizar datos básicos
            $titulo = $_POST['titulo'] ?? '';
            $categoria = $_POST['categoria'] ?? '';
            $resumen = $_POST['resumen'] ?? '';
            $tipo_trabajo = $_POST['tipo'] ?? $proyecto['tipo_trabajo'];
            
            $stmt = $pdo->prepare("UPDATE articulo SET titulo = ?, categoria = ?, resumen = ?, tipo_trabajo = ? WHERE id_articulo = ?");
            $stmt->execute([$titulo, $categoria, $resumen, $tipo_trabajo, $id_proyecto]);
            
            // Actualizar coautores internos
            if (isset($_POST['coautores_internos'])) {
                // Eliminar coautores internos actuales (excepto el autor principal si aplica)
                if ($tipo_usuario == 'alumno') {
                    $stmt = $pdo->prepare("DELETE FROM proyecto_alumno WHERE id_proyecto = ? AND id_alumno != ?");
                    $stmt->execute([$id_proyecto, $usuario['id_alumno']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM proyecto_docente WHERE id_proyecto = ?");
                    $stmt->execute([$id_proyecto]);
                } elseif ($tipo_usuario == 'docente') {
                    $stmt = $pdo->prepare("DELETE FROM proyecto_docente WHERE id_proyecto = ? AND id_docente != ?");
                    $stmt->execute([$id_proyecto, $usuario['id_docente']]);
                    
                    $stmt = $pdo->prepare("DELETE FROM proyecto_alumno WHERE id_proyecto = ?");
                    $stmt->execute([$id_proyecto]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM proyecto_alumno WHERE id_proyecto = ?");
                    $stmt->execute([$id_proyecto]);
                    
                    $stmt = $pdo->prepare("DELETE FROM proyecto_docente WHERE id_proyecto = ?");
                    $stmt->execute([$id_proyecto]);
                }
                
                // Insertar nuevos coautores internos
                if (!empty($_POST['coautores_internos'])) {
                    foreach ($_POST['coautores_internos'] as $coautor_id) {
                        if (!empty($coautor_id)) {
                            $stmt = $pdo->prepare("SELECT tipo_usuario FROM usuario WHERE id_usuario = ?");
                            $stmt->execute([$coautor_id]);
                            $tipo_coautor = $stmt->fetchColumn();
                            
                            if ($tipo_coautor == 'alumno') {
                                $stmt2 = $pdo->prepare("SELECT id_alumno FROM alumno WHERE id_usuario = ?");
                                $stmt2->execute([$coautor_id]);
                                $id_coautor_especifico = $stmt2->fetchColumn();
                                
                                if ($id_coautor_especifico) {
                                    $stmt3 = $pdo->prepare("INSERT INTO proyecto_alumno (id_proyecto, id_alumno, rol) VALUES (?, ?, 'coautor')");
                                    $stmt3->execute([$id_proyecto, $id_coautor_especifico]);
                                }
                                
                            } elseif ($tipo_coautor == 'docente') {
                                $stmt2 = $pdo->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
                                $stmt2->execute([$coautor_id]);
                                $id_coautor_especifico = $stmt2->fetchColumn();
                                
                                if ($id_coautor_especifico) {
                                    $stmt3 = $pdo->prepare("INSERT INTO proyecto_docente (id_proyecto, id_docente) VALUES (?, ?)");
                                    $stmt3->execute([$id_proyecto, $id_coautor_especifico]);
                                }
                            }
                        }
                    }
                }
            }
            
            // Actualizar coautores externos
            if (isset($_POST['coautores_externos'])) {
                // Eliminar coautores externos actuales
                $stmt = $pdo->prepare("DELETE FROM coautor_externo WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                // Insertar nuevos coautores externos
                if (!empty($_POST['coautores_externos'])) {
                    $stmt = $pdo->prepare("INSERT INTO coautor_externo (id_proyecto, nombre, rfc, email, institucion) VALUES (?, ?, ?, ?, ?)");
                    foreach ($_POST['coautores_externos'] as $coautor) {
                        if (!empty($coautor['nombre'])) {
                            $stmt->execute([
                                $id_proyecto,
                                $coautor['nombre'],
                                $coautor['rfc'] ?? null,
                                $coautor['email'] ?? null,
                                $coautor['institucion'] ?? null
                            ]);
                        }
                    }
                }
            }
            
            // Actualizar horario si es ponencia
            if ($proyecto['tipo_trabajo'] == 'ponencia' && isset($_POST['horario'])) {
                $nuevo_horario_id = $_POST['horario'];
                
                // Si el horario es diferente al actual
                if (!$horario_asignado || $horario_asignado['id_horario'] != $nuevo_horario_id) {
                    // Liberar horario anterior
                    if ($horario_asignado) {
                        $stmt = $pdo->prepare("UPDATE horario_ponencia SET id_proyecto = NULL, estado = 'disponible' WHERE id_horario = ?");
                        $stmt->execute([$horario_asignado['id_horario']]);
                    }
                    
                    // Ocupar nuevo horario
                    $stmt = $pdo->prepare("UPDATE horario_ponencia SET id_proyecto = ?, estado = 'ocupado' WHERE id_horario = ? AND estado = 'disponible'");
                    $stmt->execute([$id_proyecto, $nuevo_horario_id]);
                }
            }
            
            // Procesar nuevas imágenes
            if (!empty($_FILES['imagenes']['name'][0])) {
                $carpeta_uploads = 'uploads/proyectos/';
                
                if (!file_exists($carpeta_uploads)) {
                    mkdir($carpeta_uploads, 0777, true);
                }
                
                $es_principal = empty($imagenes);
                
                foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['imagenes']['error'][$key] == 0) {
                        $nombre_original = $_FILES['imagenes']['name'][$key];
                        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                        $nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
                        $ruta_destino = $carpeta_uploads . $nombre_archivo;
                        
                        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif'];
                        $tipo_archivo = mime_content_type($tmp_name);
                        $tamaño_maximo = 5 * 1024 * 1024;
                        
                        if (in_array($tipo_archivo, $tipos_permitidos) && $_FILES['imagenes']['size'][$key] <= $tamaño_maximo) {
                            if (move_uploaded_file($tmp_name, $ruta_destino)) {
                                $stmt_img = $pdo->prepare("
                                    INSERT INTO proyecto_imagen 
                                    (id_proyecto, nombre_archivo, archivo_original, tipo_imagen, tamaño, es_principal) 
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt_img->execute([
                                    $id_proyecto,
                                    $nombre_archivo,
                                    $nombre_original,
                                    $tipo_archivo,
                                    $_FILES['imagenes']['size'][$key],
                                    $es_principal ? 1 : 0
                                ]);
                                $es_principal = false;
                            }
                        }
                    }
                }
            }
            
            // Procesar eliminación de imágenes
            if (isset($_POST['eliminar_imagenes'])) {
                foreach ($_POST['eliminar_imagenes'] as $id_imagen) {
                    $stmt = $pdo->prepare("SELECT nombre_archivo FROM proyecto_imagen WHERE id_imagen = ? AND id_proyecto = ?");
                    $stmt->execute([$id_imagen, $id_proyecto]);
                    $imagen = $stmt->fetch();
                    
                    if ($imagen) {
                        $ruta_archivo = 'uploads/proyectos/' . $imagen['nombre_archivo'];
                        if (file_exists($ruta_archivo)) {
                            unlink($ruta_archivo);
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM proyecto_imagen WHERE id_imagen = ? AND id_proyecto = ?");
                        $stmt->execute([$id_imagen, $id_proyecto]);
                    }
                }
            }
            
            // Establecer imagen principal
            if (isset($_POST['imagen_principal'])) {
                $stmt = $pdo->prepare("UPDATE proyecto_imagen SET es_principal = 0 WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                $stmt = $pdo->prepare("UPDATE proyecto_imagen SET es_principal = 1 WHERE id_imagen = ? AND id_proyecto = ?");
                $stmt->execute([$_POST['imagen_principal'], $id_proyecto]);
            }
            
            $pdo->commit();
            $mensaje = "Proyecto actualizado exitosamente";
            $tipo_mensaje = "success";
            
            // Recargar datos actualizados
            header("refresh:2;url=ver_proyecto.php?id=$id_proyecto");
            
        } elseif ($_POST['accion'] == 'eliminar_proyecto') {
            // Eliminar imágenes físicas
            foreach ($imagenes as $imagen) {
                $ruta_archivo = 'uploads/proyectos/' . $imagen['nombre_archivo'];
                if (file_exists($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }
            
            // Liberar horario si es ponencia
            if ($horario_asignado) {
                $stmt = $pdo->prepare("UPDATE horario_ponencia SET id_proyecto = NULL, estado = 'disponible' WHERE id_horario = ?");
                $stmt->execute([$horario_asignado['id_horario']]);
            }
            
            // Eliminar el proyecto
            $stmt = $pdo->prepare("DELETE FROM articulo WHERE id_articulo = ?");
            $stmt->execute([$id_proyecto]);
            
            $pdo->commit();
            
            $_SESSION['mensaje'] = "Proyecto eliminado exitosamente";
            $_SESSION['tipo_mensaje'] = "success";
            header('Location: mis_proyectos.php');
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al actualizar el proyecto: " . $e->getMessage();
        $tipo_mensaje = "danger";
        error_log("Error en edición: " . $e->getMessage());
    }
}

// Determinar el tipo de trabajo para los estilos
$tipo_trabajo = strtolower($proyecto['tipo_trabajo'] ?? 'ponencia');
$colores_tipo = [
    'cartel' => ['bg' => '#ffc107', 'icon' => 'fa-image'],
    'ponencia' => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher'],
    'taller' => ['bg' => '#28a745', 'icon' => 'fa-tools'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube']
];
$color_tipo = $colores_tipo[$tipo_trabajo] ?? $colores_tipo['ponencia'];

// Agrupar horarios por fecha
$horarios_por_fecha = [];
if (!empty($horarios_disponibles)) {
    foreach ($horarios_disponibles as $horario) {
        $fecha = $horario['fecha'];
        if (!isset($horarios_por_fecha[$fecha])) {
            $horarios_por_fecha[$fecha] = [];
        }
        $horarios_por_fecha[$fecha][] = $horario;
    }
}
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
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <!-- Lightbox -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="estilo1.css">
    <title>Editar Proyecto - SIMPOSIO FESC C4</title>
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
        
        .colorazul { background-color: #293e6b !important; }
        .colordorado { background-color: #D59F0F !important; }
        
        .editar-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .editar-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .editar-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 30px;
            position: relative;
        }
        
        .editar-header h2 {
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
        
        .editar-body {
            padding: 40px;
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
        
        .horario-actual {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-top: 5px;
            display: inline-block;
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
        
        /* Galería de imágenes */
        .galeria-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid #dee2e6;
        }
        
        .imagenes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .imagen-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background: white;
            border: 2px solid #dee2e6;
        }
        
        .imagen-item.principal {
            border-color: #293e6b;
            border-width: 3px;
        }
        
        .imagen-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .imagen-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            gap: 10px;
        }
        
        .imagen-item:hover .imagen-overlay {
            opacity: 1;
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
        
        .imagen-checkbox {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .imagen-checkbox input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .btn-principal {
            background: #293e6b;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-principal:hover:not(:disabled) {
            background: #1a2b4a;
            transform: scale(1.05);
        }
        
        .btn-principal.activo {
            background: #28a745;
        }
        
        .btn-principal:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Acciones */
        .acciones {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .acciones-izquierda, .acciones-derecha {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 62, 107, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .editar-header h2 {
                font-size: 1.5rem;
                padding-right: 0;
                margin-bottom: 60px;
            }
            
            .tipo-badge {
                position: static;
                width: fit-content;
                margin-top: 15px;
            }
            
            .tipo-trabajo {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .horario-grid {
                grid-template-columns: 1fr;
            }
            
            .imagenes-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .acciones-izquierda, .acciones-derecha {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
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

    <div class="editar-container">
        <div class="editar-card">
            <div class="editar-header">
                <h2>
                    <i class="fas fa-edit me-3"></i>
                    Editar Proyecto
                </h2>
                <div class="tipo-badge">
                    <i class="fas <?php echo $color_tipo['icon']; ?>"></i>
                    <?php echo ucfirst($tipo_trabajo); ?>
                </div>
            </div>
            
            <div class="editar-body">
                <?php if($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                    <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="formEditar" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="actualizar_proyecto">
                    
                    <!-- Tipo de trabajo -->
                    <div class="form-group">
                        <label><i class="fas fa-tag me-2"></i>Tipo de Trabajo *</label>
                        <div class="tipo-trabajo">
                            <label class="tipo-btn <?php echo $tipo_trabajo == 'cartel' ? 'active' : ''; ?>">
                                <input type="radio" name="tipo" value="cartel" <?php echo $tipo_trabajo == 'cartel' ? 'checked' : ''; ?>>
                                <i class="fas fa-image"></i>
                                <span>Cartel</span>
                            </label>
                            <label class="tipo-btn <?php echo $tipo_trabajo == 'ponencia' ? 'active' : ''; ?>">
                                <input type="radio" name="tipo" value="ponencia" <?php echo $tipo_trabajo == 'ponencia' ? 'checked' : ''; ?>>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Ponencia</span>
                            </label>
                            <label class="tipo-btn <?php echo $tipo_trabajo == 'taller' ? 'active' : ''; ?>">
                                <input type="radio" name="tipo" value="taller" <?php echo $tipo_trabajo == 'taller' ? 'checked' : ''; ?>>
                                <i class="fas fa-tools"></i>
                                <span>Taller</span>
                            </label>
                            <label class="tipo-btn <?php echo $tipo_trabajo == 'prototipo' ? 'active' : ''; ?>">
                                <input type="radio" name="tipo" value="prototipo" <?php echo $tipo_trabajo == 'prototipo' ? 'checked' : ''; ?>>
                                <i class="fas fa-cube"></i>
                                <span>Prototipo</span>
                            </label>
                        </div>
                    </div>

                    <!-- Categoría -->
                    <div class="form-group">
                        <label for="categoria"><i class="fas fa-layer-group me-2"></i>Categoría *</label>
                        <select class="form-select" id="categoria" name="categoria" required>
                            <option value="">Seleccione una categoría...</option>
                            <option value="ENSEÑANZA DE LAS MATEMÁTICAS" <?php echo ($proyecto['categoria'] ?? '') == 'ENSEÑANZA DE LAS MATEMÁTICAS' ? 'selected' : ''; ?>>ENSEÑANZA DE LAS MATEMÁTICAS</option>
                            <option value="MATEMÁTICAS APLICADAS" <?php echo ($proyecto['categoria'] ?? '') == 'MATEMÁTICAS APLICADAS' ? 'selected' : ''; ?>>MATEMÁTICAS APLICADAS</option>
                            <option value="MATEMÁTICAS PURAS" <?php echo ($proyecto['categoria'] ?? '') == 'MATEMÁTICAS PURAS' ? 'selected' : ''; ?>>MATEMÁTICAS PURAS</option>
                            <option value="ESTADÍSTICA" <?php echo ($proyecto['categoria'] ?? '') == 'ESTADÍSTICA' ? 'selected' : ''; ?>>ESTADÍSTICA</option>
                            <option value="COMPUTACIÓN" <?php echo ($proyecto['categoria'] ?? '') == 'COMPUTACIÓN' ? 'selected' : ''; ?>>COMPUTACIÓN</option>
                            <option value="FÍSICA" <?php echo ($proyecto['categoria'] ?? '') == 'FÍSICA' ? 'selected' : ''; ?>>FÍSICA</option>
                            <option value="INGENIERÍA" <?php echo ($proyecto['categoria'] ?? '') == 'INGENIERÍA' ? 'selected' : ''; ?>>INGENIERÍA</option>
                            <option value="ECONOMÍA" <?php echo ($proyecto['categoria'] ?? '') == 'ECONOMÍA' ? 'selected' : ''; ?>>ECONOMÍA</option>
                        </select>
                    </div>

                    <!-- Título -->
                    <div class="form-group">
                        <label for="titulo"><i class="fas fa-heading me-2"></i>Título del Trabajo *</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" 
                               placeholder="Ingrese el título completo del trabajo" 
                               value="<?php echo htmlspecialchars($proyecto['titulo'] ?? ''); ?>" required>
                        <small class="text-muted">Máximo 200 caracteres</small>
                    </div>

                    <!-- Resumen -->
                    <div class="form-group">
                        <label for="resumen"><i class="fas fa-align-left me-2"></i>Resumen</label>
                        <textarea class="form-control" id="resumen" name="resumen" rows="5" 
                                  placeholder="Ingrese un resumen del trabajo (máximo 500 palabras)"><?php echo htmlspecialchars($proyecto['resumen'] ?? ''); ?></textarea>
                    </div>

                    <!-- Selección de horario para ponencias -->
                    <?php if($proyecto['tipo_trabajo'] == 'ponencia'): ?>
                    <div class="form-group">
                        <div class="horarios-container">
                            <h5><i class="fas fa-clock me-2"></i>Seleccione el horario para su ponencia *</h5>
                            
                            <?php if(empty($horarios_disponibles)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No hay horarios disponibles. El horario actual está reservado para esta ponencia.
                            </div>
                            <?php else: ?>
                                <?php foreach($horarios_por_fecha as $fecha => $horarios): ?>
                                <div class="fecha-grupo">
                                    <h5><?php echo date('d/m/Y', strtotime($fecha)); ?></h5>
                                    <div class="horario-grid">
                                        <?php foreach($horarios as $horario): ?>
                                        <div class="horario-item">
                                            <input type="radio" name="horario" id="horario_<?php echo $horario['id_horario']; ?>" 
                                                   value="<?php echo $horario['id_horario']; ?>"
                                                   <?php echo ($horario_asignado && $horario['id_horario'] == $horario_asignado['id_horario']) ? 'checked' : ''; ?>
                                                   <?php echo ($horario['estado'] == 'ocupado' && (!$horario_asignado || $horario['id_horario'] != $horario_asignado['id_horario'])) ? 'disabled' : ''; ?>>
                                            <label for="horario_<?php echo $horario['id_horario']; ?>">
                                                <div class="horario-hora">
                                                    <?php echo date('H:i', strtotime($horario['hora_inicio'])); ?> - 
                                                    <?php echo date('H:i', strtotime($horario['hora_fin'])); ?>
                                                </div>
                                                <div class="horario-salon">
                                                    <i class="fas fa-door-open me-1"></i>
                                                    <?php echo htmlspecialchars($horario['salon'] ?? 'Salón por asignar'); ?>
                                                </div>
                                                <?php if($horario_asignado && $horario['id_horario'] == $horario_asignado['id_horario']): ?>
                                                <div class="horario-actual">
                                                    <i class="fas fa-check-circle me-1"></i>Horario actual
                                                </div>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sección de Coautores -->
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
                                    <?php if(!empty($participantes_internos)): ?>
                                        <?php foreach($participantes_internos as $participante): ?>
                                        <?php 
                                        // Verificar si es el autor principal
                                        $es_autor_principal = false;
                                        if ($tipo_usuario == 'alumno' && isset($participante['id_alumno']) && $participante['id_alumno'] == $usuario['id_alumno'] && isset($participante['rol']) && $participante['rol'] == 'autor') {
                                            $es_autor_principal = true;
                                        } elseif ($tipo_usuario == 'docente' && isset($participante['id_docente']) && $participante['id_docente'] == $usuario['id_docente']) {
                                            $es_autor_principal = true;
                                        }
                                        
                                        // Solo mostrar si no es el autor principal
                                        if (!$es_autor_principal):
                                        ?>
                                        <div class="coautor-item">
                                            <select class="form-select coautor-select" name="coautores_internos[]">
                                                <option value="">Seleccionar coautor...</option>
                                                <?php foreach($posibles_coautores_internos as $coautor): ?>
                                                <option value="<?php echo $coautor['id_usuario']; ?>" 
                                                    <?php echo ($coautor['id_usuario'] == $participante['id_usuario']) ? 'selected' : ''; ?>>
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
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn-add" id="btn-add-coautor-interno">
                                    <i class="fas fa-plus-circle"></i>
                                    Agregar Coautor Interno
                                </button>
                                
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-internos">
                                    <i class="fas fa-times-circle"></i>
                                    Quitar Todos
                                </button>
                            </div>
                            
                            <!-- Coautores Externos -->
                            <div class="tab-pane fade" id="externos" role="tabpanel">
                                <div id="coautores-externos-list">
                                    <?php if(!empty($coautores_externos)): ?>
                                        <?php foreach($coautores_externos as $coautor): ?>
                                        <div class="coautor-item">
                                            <div class="coautor-externo-grid">
                                                <input type="text" class="form-control" name="coautores_externos[][nombre]" 
                                                       value="<?php echo htmlspecialchars($coautor['nombre']); ?>" 
                                                       placeholder="Nombre completo">
                                                <input type="text" class="form-control" name="coautores_externos[][rfc]" 
                                                       value="<?php echo htmlspecialchars($coautor['rfc'] ?? ''); ?>" 
                                                       placeholder="RFC (opcional)" maxlength="13">
                                                <input type="email" class="form-control" name="coautores_externos[][email]" 
                                                       value="<?php echo htmlspecialchars($coautor['email'] ?? ''); ?>" 
                                                       placeholder="Email (opcional)">
                                                <input type="text" class="form-control" name="coautores_externos[][institucion]" 
                                                       value="<?php echo htmlspecialchars($coautor['institucion'] ?? ''); ?>" 
                                                       placeholder="Institución (opcional)">
                                            </div>
                                            <button type="button" class="btn-remove" onclick="this.closest('.coautor-item').remove()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="btn-add" id="btn-add-coautor-externo">
                                    <i class="fas fa-plus-circle"></i>
                                    Agregar Coautor Externo
                                </button>
                                
                                <button type="button" class="btn-add btn-danger" id="btn-remove-all-externos">
                                    <i class="fas fa-times-circle"></i>
                                    Quitar Todos
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Galería de imágenes existentes -->
                    <?php if(!empty($imagenes)): ?>
                    <div class="galeria-container">
                        <h5><i class="fas fa-images me-2"></i>Imágenes actuales <small class="text-muted">(Opcional)</small></h5>
                        
                        <div class="imagenes-grid">
                            <?php foreach($imagenes as $imagen): ?>
                            <div class="imagen-item <?php echo $imagen['es_principal'] ? 'principal' : ''; ?>">
                                <?php if($imagen['es_principal']): ?>
                                <span class="imagen-badge">
                                    <i class="fas fa-star me-1"></i>Principal
                                </span>
                                <?php endif; ?>
                                
                                <div class="imagen-checkbox">
                                    <input type="checkbox" name="eliminar_imagenes[]" value="<?php echo $imagen['id_imagen']; ?>" id="img_<?php echo $imagen['id_imagen']; ?>">
                                </div>
                                
                                <a href="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" data-lightbox="proyecto">
                                    <img src="uploads/proyectos/<?php echo $imagen['nombre_archivo']; ?>" alt="Imagen del proyecto">
                                </a>
                                
                                <div class="imagen-overlay">
                                    <button type="button" class="btn-principal <?php echo $imagen['es_principal'] ? 'activo' : ''; ?>" 
                                            onclick="setPrincipal(<?php echo $imagen['id_imagen']; ?>)"
                                            <?php echo $imagen['es_principal'] ? 'disabled' : ''; ?>>
                                        <i class="fas <?php echo $imagen['es_principal'] ? 'fa-check' : 'fa-star'; ?> me-1"></i>
                                        <?php echo $imagen['es_principal'] ? 'Principal' : 'Hacer principal'; ?>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <input type="hidden" name="imagen_principal" id="imagen_principal" value="<?php 
                            $principal = array_filter($imagenes, function($img) { return $img['es_principal']; });
                            $principal = reset($principal);
                            echo $principal['id_imagen'] ?? '';
                        ?>">
                    </div>
                    <?php endif; ?>

                    <!-- Subir nuevas imágenes -->
                    <div class="form-group">
                        <label><i class="fas fa-plus-circle me-2"></i>Agregar nuevas imágenes <small class="text-muted">(Opcional)</small></label>
                        <div class="imagenes-container">
                            <input type="file" class="form-control" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/gif" multiple>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB por imagen.
                            </small>
                            <div id="vista-previa" class="row mt-3"></div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="acciones">
                        <div class="acciones-izquierda">
                            <a href="ver_proyecto.php?id=<?php echo $id_proyecto; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                        <div class="acciones-derecha">
                            <button type="button" class="btn btn-danger" onclick="confirmarEliminacion()">
                                <i class="fas fa-trash me-2"></i>Eliminar Proyecto
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Formulario oculto para eliminar -->
                <form method="POST" id="formEliminar" style="display: none;">
                    <input type="hidden" name="accion" value="eliminar_proyecto">
                </form>
            </div>
        </div>
    </div>

    <!-- Templates -->
    <template id="coautor-interno-template">
        <div class="coautor-item">
            <select class="form-select coautor-select" name="coautores_internos[]">
                <option value="">Seleccionar coautor...</option>
                <?php if(!empty($posibles_coautores_internos)): ?>
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
                <?php endif; ?>
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
                       placeholder="RFC (opcional)" maxlength="13">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Configuración de Lightbox
            lightbox.option({
                'resizeDuration': 200,
                'wrapAround': true,
                'albumLabel': 'Imagen %1 de %2',
                'fadeDuration': 300
            });

            // Inicializar Select2 en selects existentes
            setTimeout(function() {
                $('.coautor-select').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Buscar coautor...',
                    allowClear: true,
                    width: '100%'
                });
            }, 100);

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
            const removeAllInternos = document.getElementById('btn-remove-all-internos');
            if (removeAllInternos) {
                removeAllInternos.addEventListener('click', function() {
                    if (confirm('¿Está seguro de eliminar todos los coautores internos?')) {
                        document.getElementById('coautores-internos-list').innerHTML = '';
                    }
                });
            }

            // Coautores externos
            document.getElementById('btn-add-coautor-externo').addEventListener('click', function() {
                const template = document.getElementById('coautor-externo-template');
                if (template) {
                    const clone = template.content.cloneNode(true);
                    document.getElementById('coautores-externos-list').appendChild(clone);
                }
            });

            // Quitar todos los coautores externos
            const removeAllExternos = document.getElementById('btn-remove-all-externos');
            if (removeAllExternos) {
                removeAllExternos.addEventListener('click', function() {
                    if (confirm('¿Está seguro de eliminar todos los coautores externos?')) {
                        document.getElementById('coautores-externos-list').innerHTML = '';
                    }
                });
            }

            // Vista previa de nuevas imágenes
            document.getElementById('imagenes').addEventListener('change', function(e) {
                const vistaPrevia = document.getElementById('vista-previa');
                vistaPrevia.innerHTML = '';
                
                const archivos = e.target.files;
                
                if (archivos.length > 0) {
                    for (let i = 0; i < archivos.length; i++) {
                        const archivo = archivos[i];
                        
                        if (archivo.type.startsWith('image/')) {
                            if (archivo.size > 5 * 1024 * 1024) {
                                alert(`La imagen "${archivo.name}" excede el tamaño máximo de 5MB`);
                                continue;
                            }
                            
                            const reader = new FileReader();
                            
                            reader.onload = function(e) {
                                const col = document.createElement('div');
                                col.className = 'col-md-3 col-6 mb-3';
                                
                                const card = document.createElement('div');
                                card.className = 'card h-100';
                                
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.className = 'card-img-top';
                                img.style.height = '150px';
                                img.style.objectFit = 'cover';
                                
                                const cardBody = document.createElement('div');
                                cardBody.className = 'card-body p-2';
                                
                                const fileName = document.createElement('p');
                                fileName.className = 'small text-muted mb-0 text-truncate';
                                fileName.textContent = archivo.name;
                                
                                cardBody.appendChild(fileName);
                                card.appendChild(img);
                                card.appendChild(cardBody);
                                col.appendChild(card);
                                vistaPrevia.appendChild(col);
                            }
                            
                            reader.readAsDataURL(archivo);
                        }
                    }
                }
            });

            // Auto-resize para textarea
            const textarea = document.getElementById('resumen');
            if (textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = (textarea.scrollHeight) + 'px';
                
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
        });

        // Función para establecer imagen principal
        function setPrincipal(id_imagen) {
            document.getElementById('imagen_principal').value = id_imagen;
            
            document.querySelectorAll('.imagen-item').forEach(item => {
                item.classList.remove('principal');
                
                const badge = item.querySelector('.imagen-badge');
                if (badge) {
                    badge.remove();
                }
                
                const btn = item.querySelector('.btn-principal');
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('activo');
                    btn.innerHTML = '<i class="fas fa-star me-1"></i>Hacer principal';
                }
            });
            
            const imagenSeleccionada = document.querySelector(`.imagen-item:has(input[value="${id_imagen}"])`);
            if (imagenSeleccionada) {
                imagenSeleccionada.classList.add('principal');
                
                const badge = document.createElement('span');
                badge.className = 'imagen-badge';
                badge.innerHTML = '<i class="fas fa-star me-1"></i>Principal';
                imagenSeleccionada.insertBefore(badge, imagenSeleccionada.firstChild);
                
                const btn = imagenSeleccionada.querySelector('.btn-principal');
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('activo');
                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Principal';
                }
            }
            
            document.querySelectorAll('.imagen-checkbox input').forEach(checkbox => {
                if (checkbox.value == id_imagen) {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                } else {
                    checkbox.disabled = false;
                }
            });
        }

        // Función para confirmar eliminación
        function confirmarEliminacion() {
            if (confirm('¿Está seguro de eliminar este proyecto?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('formEliminar').submit();
            }
        }

        // Validación del formulario
        document.getElementById('formEditar').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            if (titulo.length > 200) {
                e.preventDefault();
                alert('El título no puede exceder los 200 caracteres');
                return false;
            }
            
            if (titulo === '') {
                e.preventDefault();
                alert('El título es obligatorio');
                return false;
            }
            
            const categoria = document.getElementById('categoria').value;
            if (!categoria) {
                e.preventDefault();
                alert('Debe seleccionar una categoría');
                return false;
            }
            
            <?php if($proyecto['tipo_trabajo'] == 'ponencia'): ?>
            const horarioSeleccionado = document.querySelector('input[name="horario"]:checked');
            if (!horarioSeleccionado) {
                e.preventDefault();
                alert('Debe seleccionar un horario para la ponencia');
                return false;
            }
            <?php endif; ?>
            
            // Verificar que no se intente eliminar la imagen principal
            const imagenPrincipal = document.getElementById('imagen_principal').value;
            if (imagenPrincipal) {
                const checkboxes = document.querySelectorAll('.imagen-checkbox input:checked');
                for (let checkbox of checkboxes) {
                    if (checkbox.value == imagenPrincipal) {
                        e.preventDefault();
                        alert('No puedes eliminar la imagen principal. Primero selecciona otra imagen como principal.');
                        return false;
                    }
                }
            }
            
            return true;
        });
    </script>
</body>
</html>