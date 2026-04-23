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

// Procesar respuesta a solicitud de patrocinio (aceptar/rechazar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion_patrocinio'])) {
    $id_patrocinio = intval($_POST['id_patrocinio']);
    $nuevo_estado = $_POST['accion_patrocinio']; // 'aceptado' o 'rechazado'
    $comentarios = trim($_POST['comentarios_respuesta'] ?? '');

    // Verificar que el usuario sea autor del proyecto relacionado
    $stmt = $conexion->prepare("
        SELECT p.id_patrocinio, a.id_usuario as autor_id
        FROM patrocinios p
        JOIN articulo a ON p.id_articulo = a.id_articulo
        WHERE p.id_patrocinio = ?
    ");
    $stmt->bind_param("i", $id_patrocinio);
    $stmt->execute();
    $patrocinio = $stmt->get_result()->fetch_assoc();

    if ($patrocinio && ($patrocinio['autor_id'] == $_SESSION['id_usuario'] || es_autor_proyecto($conexion, $patrocinio['autor_id'], $_SESSION['id_usuario']))) {
        $stmt = $conexion->prepare("UPDATE patrocinios SET estado = ?, comentarios_autor = ?, fecha_respuesta = NOW() WHERE id_patrocinio = ?");
        $stmt->bind_param("ssi", $nuevo_estado, $comentarios, $id_patrocinio);
        if ($stmt->execute()) {
            $mensaje = "Has " . ($nuevo_estado == 'aceptado' ? 'aceptado' : 'rechazado') . " la solicitud de patrocinio.";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al procesar la solicitud.";
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "No tienes permiso para responder a esta solicitud.";
        $tipo_mensaje = "warning";
    }
}

// Función auxiliar para verificar si un usuario es autor de un proyecto (alumno/docente)
function es_autor_proyecto($conexion, $id_articulo, $id_usuario) {
    // Verificar si es alumno autor
    $stmt = $conexion->prepare("SELECT 1 FROM articulo_alumno aa JOIN alumno a ON aa.id_alumno = a.id_alumno WHERE aa.id_articulo = ? AND a.id_usuario = ? AND aa.rol = 'autor'");
    $stmt->bind_param("ii", $id_articulo, $id_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return true;
    
    // Verificar si es docente autor
    $stmt = $conexion->prepare("SELECT 1 FROM articulo_docente ad JOIN docente d ON ad.id_docente = d.id_docente WHERE ad.id_articulo = ? AND d.id_usuario = ?");
    $stmt->bind_param("ii", $id_articulo, $id_usuario);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Procesar eliminación si se solicita
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id_proyecto = intval($_POST['id_proyecto']);
    
    // Verificar que el usuario sea autor (tiene permiso para eliminar)
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
    
    if ($es_autor) {
        // Eliminar imágenes físicas (opcional, podrías implementarlo después)
        // Por ahora solo eliminamos registros
        $conexion->begin_transaction();
        try {
            // Eliminar actividad asociada (si existe)
            $stmt = $conexion->prepare("DELETE FROM actividad_evento WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            // Eliminar relaciones
            $stmt = $conexion->prepare("DELETE FROM articulo_alumno WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            $stmt = $conexion->prepare("DELETE FROM articulo_docente WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            $stmt = $conexion->prepare("DELETE FROM coautor_externo WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            $stmt = $conexion->prepare("DELETE FROM proyecto_imagen WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            // Eliminar artículo
            $stmt = $conexion->prepare("DELETE FROM articulo WHERE id_articulo = ?");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            
            $conexion->commit();
            $mensaje = "Proyecto eliminado exitosamente.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "Error al eliminar: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "No tienes permiso para eliminar este proyecto.";
        $tipo_mensaje = "warning";
    }
}

// Obtener proyectos del usuario
$proyectos = [];

if ($id_especifico) {
    if ($id_especifico['tipo'] == 'alumno') {
        $sql = "
            SELECT 
                a.id_articulo,
                a.titulo,
                a.tipo_trabajo,
                a.categoria,
                a.estado,
                aa.rol,
                CASE WHEN aa.rol = 'autor' THEN 'Autor' ELSE 'Coautor' END as participacion,
                (SELECT COUNT(*) FROM articulo_alumno aa2 WHERE aa2.id_articulo = a.id_articulo AND aa2.rol = 'coautor') as num_coautores_internos,
                (SELECT COUNT(*) FROM coautor_externo ce WHERE ce.id_articulo = a.id_articulo) as num_coautores_externos,
                (SELECT COUNT(*) FROM actividad_evento ae WHERE ae.id_articulo = a.id_articulo) as tiene_horario
            FROM articulo a
            INNER JOIN articulo_alumno aa ON a.id_articulo = aa.id_articulo
            WHERE aa.id_alumno = ?
            ORDER BY a.id_articulo DESC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_especifico['id']);
    } elseif ($id_especifico['tipo'] == 'docente') {
        $sql = "
            SELECT 
                a.id_articulo,
                a.titulo,
                a.tipo_trabajo,
                a.categoria,
                a.estado,
                'autor' as rol,
                'Autor' as participacion,
                0 as num_coautores_internos,
                (SELECT COUNT(*) FROM coautor_externo ce WHERE ce.id_articulo = a.id_articulo) as num_coautores_externos,
                (SELECT COUNT(*) FROM actividad_evento ae WHERE ae.id_articulo = a.id_articulo) as tiene_horario
            FROM articulo a
            INNER JOIN articulo_docente ad ON a.id_articulo = ad.id_articulo
            WHERE ad.id_docente = ?
            ORDER BY a.id_articulo DESC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_especifico['id']);
    } elseif ($id_especifico['tipo'] == 'empresa') {
        $sql = "
            SELECT 
                a.id_articulo,
                a.titulo,
                a.tipo_trabajo,
                a.categoria,
                a.estado,
                'autor' as rol,
                'Autor' as participacion,
                0 as num_coautores_internos,
                (SELECT COUNT(*) FROM coautor_externo ce WHERE ce.id_articulo = a.id_articulo) as num_coautores_externos,
                (SELECT COUNT(*) FROM actividad_evento ae WHERE ae.id_articulo = a.id_articulo) as tiene_horario
            FROM articulo a
            WHERE a.id_usuario = ?
            ORDER BY a.id_articulo DESC
        ";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $_SESSION['id_usuario']);
    }
    
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $proyectos[] = $row;
        }
    }
}

// Estadísticas
$total_proyectos = count($proyectos);
$autor_count = 0;
$coautor_count = 0;
foreach ($proyectos as $p) {
    if ($p['participacion'] == 'Autor') $autor_count++;
    else $coautor_count++;
}

// Colores para tipos de trabajo
$colores_tipo = [
    'cartel'    => ['bg' => '#ffc107', 'icon' => 'fa-image', 'text' => 'Cartel'],
    'ponencia'  => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher', 'text' => 'Ponencia'],
    'taller'    => ['bg' => '#28a745', 'icon' => 'fa-tools', 'text' => 'Taller'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube', 'text' => 'Prototipo']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <title>Mis Proyectos - SIMPOSIO</title>
    <style>
        /* Estilos adicionales (puedes copiar los que ya tenías) */
        .proyectos-container { max-width: 1200px; margin: 100px auto; padding: 0 20px; }
        .proyectos-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .proyectos-header { background: linear-gradient(135deg, #293e6b, #1a2b4a); color: white; padding: 30px; text-align: center; }
        .proyectos-body { padding: 40px; }
        .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; border-radius: 15px; padding: 20px; text-align: center; border: 1px solid #dee2e6; }
        .stat-icon { font-size: 2.5rem; color: #293e6b; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #293e6b; }
        .btn-agregar { background: #28a745; color: white; padding: 12px 30px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .btn-agregar:hover { background: #218838; transform: translateY(-2px); }
        .table-container { background: white; border-radius: 15px; padding: 20px; border: 1px solid #e9ecef; overflow-x: auto; }
        .table thead th { background: #293e6b; color: white; border: none; padding: 15px; }
        .badge-tipo { padding: 5px 10px; border-radius: 20px; color: white; display: inline-block; }
        .badge-autor { background: #293e6b; color: white; }
        .badge-coautor { background: #6c757d; color: white; }
        .btn-accion { padding: 8px 12px; border-radius: 8px; border: none; cursor: pointer; margin: 0 2px; }
        .btn-ver { background: #17a2b8; color: white; }
        .btn-editar { background: #ffc107; color: black; }
        .btn-eliminar { background: #dc3545; color: white; }
        .empty-state { text-align: center; padding: 50px; }
        .empty-state i { font-size: 5rem; color: #dee2e6; }
    </style>
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
                    <!-- Enlaces comunes para todos -->
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="convocatoria.php">
                            <i class="fas fa-scroll me-1"></i>Convocatoria
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ponencias.php">
                            <i class="fas fa-chalkboard me-1"></i>Ponencias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="programa/index_programa.php">
                            <i class="fas fa-calendar me-1"></i>Programa
                        </a>
                    </li>
                    
                    <?php if (esta_logeado()): ?>
                        
                        <?php if (es_empresa()): ?>
                            <!-- EMPRESA: solo ve Patrocinar -->
                            <li class="nav-item">
                                <a class="nav-link" href="patrocinar_proyectos.php">
                                    <i class="fas fa-hand-holding-usd me-1"></i>Patrocinar
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- ALUMNO y DOCENTE: ven Mis Proyectos y Registrar Trabajo -->
                            <li class="nav-item">
                                <a class="nav-link" href="mis_proyectos.php">
                                    <i class="fas fa-project-diagram me-1"></i>Mis Proyectos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="registrar_trabajos.php">
                                    <i class="fas fa-upload me-1"></i>Registrar Trabajo
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Menú desplegable del usuario -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> 
                                <?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <!-- Mi Perfil - visible para TODOS los logueados -->
                                <li>
                                    <a class="dropdown-item" href="perfil.php">
                                        <i class="fas fa-id-card me-2"></i>Mi Perfil
                                    </a>
                                </li>
                                
                                <?php if (es_empresa()): ?>
                                    <!-- EMPRESA: enlace a Patrocinar -->
                                    <li>
                                        <a class="dropdown-item" href="patrocinar_proyectos.php">
                                            <i class="fas fa-hand-holding-usd me-2"></i>Patrocinar
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <!-- ALUMNO y DOCENTE: enlace a Mis Proyectos -->
                                    <li>
                                        <a class="dropdown-item" href="mis_proyectos.php">
                                            <i class="fas fa-project-diagram me-2"></i>Mis Proyectos
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- SOLO PARA DOCENTES: Mis revisiones -->
                                <?php if (es_docente() && !es_empresa()): ?>
                                    <li>
                                        <a class="dropdown-item" href="revisiones.php">
                                            <i class="fas fa-tasks me-2"></i>Mis revisiones
                                            <?php if (isset($revisiones_pendientes) && $revisiones_pendientes > 0): ?>
                                                <span class="badge bg-danger rounded-pill ms-2"><?php echo $revisiones_pendientes; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                    <?php else: ?>
                        <!-- Usuarios NO logueados -->
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="registro.php">
                                <i class="fas fa-user-plus me-1"></i>Registro
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>


    <div class="proyectos-container">
        <div class="proyectos-card">
            <div class="proyectos-header">
                <h2><i class="fas fa-folder-open me-3"></i>Mis Proyectos</h2>
            </div>
            <div class="proyectos-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-number"><?php echo $total_proyectos; ?></div>
                        <div>Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-pen-fancy"></i></div>
                        <div class="stat-number"><?php echo $autor_count; ?></div>
                        <div>Como Autor</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $coautor_count; ?></div>
                        <div>Como Coautor</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-number"><?php echo max(0, 10 - $total_proyectos); ?></div>
                        <div>Disponibles</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Lista de Proyectos</h4>
                    <a href="registrar_trabajos.php" class="btn-agregar">
                        <i class="fas fa-plus-circle"></i> Nuevo Proyecto
                    </a>
                </div>

                <?php if (empty($proyectos)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4>No tienes proyectos registrados</h4>
                    <p>Comienza registrando tu primer trabajo.</p>
                    <a href="registrar_trabajos.php" class="btn btn-primary">Registrar Trabajo</a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table" id="tabla-proyectos">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Participación</th>
                                <th>Coautores</th>
                                <th>Patrocinadores</th>
                                <th>Horario</th>
                                <th>Acciones</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proyectos as $p): 
                                $tipo = strtolower($p['tipo_trabajo'] ?? 'ponencia');
                                $color = $colores_tipo[$tipo] ?? $colores_tipo['ponencia'];
                                $total_coautores = $p['num_coautores_internos'] + $p['num_coautores_externos'];
                            ?>
                            <tr>
                                <!--<td>#<?php echo $p['id_articulo']; ?></td>-->
                                <td>
                                    <strong><?php echo htmlspecialchars($p['titulo']); ?></strong>
                                    <?php if (!empty($p['categoria'])): ?>
                                    <br><small><?php echo htmlspecialchars($p['categoria']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-tipo" style="background: <?php echo $color['bg']; ?>">
                                        <i class="fas <?php echo $color['icon']; ?> me-1"></i>
                                        <?php echo $color['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($p['participacion'] == 'Autor') ? 'badge-autor' : 'badge-coautor'; ?>">
                                        <i class="fas <?php echo ($p['participacion'] == 'Autor') ? 'fa-crown' : 'fa-user-friends'; ?> me-1"></i>
                                        <?php echo $p['participacion']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($total_coautores > 0): ?>
                                        <?php if ($p['num_coautores_internos'] > 0): ?>
                                        <span class="badge bg-info">Internos: <?php echo $p['num_coautores_internos']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($p['num_coautores_externos'] > 0): ?>
                                        <span class="badge bg-secondary">Externos: <?php echo $p['num_coautores_externos']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin coautores</span>
                                    <?php endif; ?>
                                </td>
                                <!-- Celda de Patrocinadores -->
                                <td data-label="Patrocinadores">
                                    <?php
                                    $num_patrocinadores = contar_patrocinadores_proyecto($conexion, $p['id_articulo']);
                                    if ($num_patrocinadores > 0):
                                    ?>
                                        <div class="patrocinadores-badge">
                                            <span class="badge bg-success">
                                                <i class="fas fa-hand-holding-heart me-1"></i>
                                                <?php echo $num_patrocinadores; ?> Patrocinador(es)
                                            </span>
                                            <?php if ($p['participacion'] == 'Autor'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success mt-1" data-bs-toggle="modal" data-bs-target="#modalVerPatrocinadores" data-id="<?php echo $p['id_articulo']; ?>" data-titulo="<?php echo htmlspecialchars($p['titulo']); ?>">
                                                <i class="fas fa-eye"></i> Ver
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-gem me-1"></i>Sin patrocinadores
                                        </span>
                                        <?php if ($p['participacion'] == 'Autor'): ?>
                                        <a href="patrocinar_proyectos.php" class="btn btn-sm btn-outline-secondary mt-1" target="_blank">
                                            <i class="fas fa-search"></i> Buscar
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['tiene_horario'] > 0): ?>
                                    <span class="badge bg-success">Asignado</span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-accion btn-ver" onclick="verProyecto(<?php echo $p['id_articulo']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($p['participacion'] == 'Autor'): ?>
                                    <button class="btn-accion btn-editar" onclick="editarProyecto(<?php echo $p['id_articulo']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este proyecto?')">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_proyecto" value="<?php echo $p['id_articulo']; ?>">
                                        <button type="submit" class="btn-accion btn-eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                        <?php if ($p['estado'] == 'rechazado' && $p['participacion'] == 'Autor'): ?>
                                        <a href="reenviar.php?id=<?php echo $p['id_articulo']; ?>" class="btn-accion btn-warning" onclick="return confirm('¿Reenviar este trabajo para aprobación?')">
                                            <i class="fas fa-paper-plane"></i>
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estado">
                                    <?php
                                    $estado = $p['estado'];
                                    $badge_class = '';
                                    if ($estado == 'pendiente') $badge_class = 'warning';
                                    elseif ($estado == 'aprobado') $badge_class = 'success';
                                    elseif ($estado == 'rechazado') $badge_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo ucfirst($estado); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Modal para responder solicitud de patrocinio -->
    <div class="modal fade" id="modalRespuestaPatrocinio" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Responder Solicitud de Patrocinio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_patrocinio" id="id_patrocinio_modal">
                        <input type="hidden" name="accion_patrocinio" id="accion_patrocinio_modal">
                        <p>¿Estás seguro de <strong id="accion_texto_modal"></strong> la solicitud de patrocinio de la empresa <strong id="empresa_nombre_modal"></strong>?</p>
                        <div class="mb-3">
                            <label for="comentarios_respuesta" class="form-label">Comentarios (opcional)</label>
                            <textarea class="form-control" name="comentarios_respuesta" rows="3" placeholder="Escribe un mensaje para la empresa..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para ver patrocinadores del proyecto -->
    <div class="modal fade" id="modalVerPatrocinadores" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-hand-holding-heart me-2"></i>
                        Patrocinadores del proyecto: <span id="modalProyectoTitulo"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalPatrocinadoresContent">
                    <div class="text-center p-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando información de patrocinadores...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tabla-proyectos').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
                order: [[0, 'desc']],
                pageLength: 10
            });
        });

        function verProyecto(id) {
            window.location.href = 'ver_proyecto.php?id=' + id;
        }
        function editarProyecto(id) {
            window.location.href = 'editar_proyecto.php?id=' + id;
        }
    </script>
    <script>
        // Pasar datos al modal de respuesta
        var modalRespuesta = document.getElementById('modalRespuestaPatrocinio');
        modalRespuesta.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var idPatrocinio = button.getAttribute('data-id');
            var empresaNombre = button.getAttribute('data-empresa');
            var accion = button.getAttribute('data-accion');
            document.getElementById('id_patrocinio_modal').value = idPatrocinio;
            document.getElementById('accion_patrocinio_modal').value = accion;
            document.getElementById('empresa_nombre_modal').textContent = empresaNombre;
            document.getElementById('accion_texto_modal').textContent = accion === 'aceptado' ? 'ACEPTAR' : 'RECHAZAR';
        });
        
        // Función para cargar patrocinadores vía AJAX
        function cargarPatrocinadores(idArticulo, titulo) {
            document.getElementById('modalProyectoTitulo').textContent = titulo;
            document.getElementById('modalPatrocinadoresContent').innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando información de patrocinadores...</p>
                </div>
            `;
            
            fetch(`ajax/obtener_patrocinadores.php?id=${idArticulo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.patrocinadores.length > 0) {
                            let html = '<div class="row">';
                            data.patrocinadores.forEach(patro => {
                                html += `
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-success h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-success rounded-circle p-3 me-3">
                                                        <i class="fas fa-building text-white fa-lg"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="card-title mb-0">${escapeHtml(patro.nombre_empresa)}</h5>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user me-1"></i>${escapeHtml(patro.nombre)} ${escapeHtml(patro.apellidos || '')}
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12 mb-2">
                                                        <i class="fas fa-envelope text-success me-2"></i>
                                                        <a href="mailto:${escapeHtml(patro.correo)}">${escapeHtml(patro.correo)}</a>
                                                    </div>
                                                    <div class="col-12">
                                                        <i class="fas fa-calendar-check text-success me-2"></i>
                                                        Patrocinio aceptado: ${formatDate(patro.fecha_respuesta)}
                                                    </div>
                                                    ${patro.comentarios_empresa ? `
                                                    <div class="col-12 mt-2">
                                                        <i class="fas fa-comment text-success me-2"></i>
                                                        <small>"${escapeHtml(patro.comentarios_empresa)}"</small>
                                                    </div>
                                                    ` : ''}
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent border-success">
                                                <span class="badge bg-success"><i class="fas fa-gem me-1"></i>Patrocinador Oficial</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            document.getElementById('modalPatrocinadoresContent').innerHTML = html;
                        } else {
                            document.getElementById('modalPatrocinadoresContent').innerHTML = `
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <p>Este proyecto no tiene patrocinadores aún.</p>
                                    <a href="patrocinar_proyectos.php" class="btn btn-primary">Buscar patrocinadores</a>
                                </div>
                            `;
                        }
                    } else {
                        document.getElementById('modalPatrocinadoresContent').innerHTML = `
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <p>Error al cargar los patrocinadores.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalPatrocinadoresContent').innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <p>Error de conexión al cargar los patrocinadores.</p>
                        </div>
                    `;
                });
        }

        // Función auxiliar para escapar HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Función auxiliar para formatear fecha
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-MX');
        }

        // Event listener para el modal
        var modalPatrocinadores = document.getElementById('modalVerPatrocinadores');
        if (modalPatrocinadores) {
            modalPatrocinadores.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var idArticulo = button.getAttribute('data-id');
                var titulo = button.getAttribute('data-titulo');
                cargarPatrocinadores(idArticulo, titulo);
            });
        }
    </script>
</body>
</html>