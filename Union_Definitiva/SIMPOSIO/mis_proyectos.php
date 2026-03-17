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

$mensaje = '';
$tipo_mensaje = '';

// Obtener información del usuario
try {
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
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    
} catch (Exception $e) {
    $mensaje = "Error al obtener información del usuario: " . $e->getMessage();
    $tipo_mensaje = "danger";
}

// Determinar el ID específico según el tipo de usuario
$id_especifico = null;
$tipo_usuario = $usuario['tipo_usuario'] ?? '';

if ($tipo_usuario == 'alumno' && isset($usuario['id_alumno'])) {
    $id_especifico = $usuario['id_alumno'];
} elseif ($tipo_usuario == 'docente' && isset($usuario['id_docente'])) {
    $id_especifico = $usuario['id_docente'];
} elseif ($tipo_usuario == 'empresa' && isset($usuario['id_empresa'])) {
    $id_especifico = $usuario['id_empresa'];
}

// Procesar acciones de edición/eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    try {
        $pdo->beginTransaction();
        
        $id_proyecto = $_POST['id_proyecto'];
        
        if ($tipo_usuario == 'alumno') {
            // Verificar que sea autor
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM proyecto_alumno WHERE id_proyecto = ? AND id_alumno = ? AND rol = 'autor'");
            $stmt->execute([$id_proyecto, $id_especifico]);
            if ($stmt->fetchColumn() > 0) {
                // Liberar horario si es ponencia
                $stmt = $pdo->prepare("UPDATE horario_ponencia SET id_proyecto = NULL, estado = 'disponible' WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                // Eliminar relaciones
                $stmt = $pdo->prepare("DELETE FROM proyecto_alumno WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                $stmt = $pdo->prepare("DELETE FROM proyecto_docente WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                $stmt = $pdo->prepare("DELETE FROM coautor_externo WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                // Eliminar artículo
                $stmt = $pdo->prepare("DELETE FROM articulo WHERE id_articulo = ?");
                $stmt->execute([$id_proyecto]);
                
                $mensaje = "Proyecto eliminado exitosamente";
                $tipo_mensaje = "success";
            }
        } elseif ($tipo_usuario == 'docente') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM proyecto_docente WHERE id_proyecto = ? AND id_docente = ?");
            $stmt->execute([$id_proyecto, $id_especifico]);
            if ($stmt->fetchColumn() > 0) {
                // Liberar horario si es ponencia
                $stmt = $pdo->prepare("UPDATE horario_ponencia SET id_proyecto = NULL, estado = 'disponible' WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                $stmt = $pdo->prepare("DELETE FROM proyecto_docente WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                $stmt = $pdo->prepare("DELETE FROM coautor_externo WHERE id_proyecto = ?");
                $stmt->execute([$id_proyecto]);
                
                $stmt = $pdo->prepare("DELETE FROM articulo WHERE id_articulo = ?");
                $stmt->execute([$id_proyecto]);
                
                $mensaje = "Proyecto eliminado exitosamente";
                $tipo_mensaje = "success";
            }
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener proyectos del usuario
$proyectos = [];

if ($id_especifico) {
    try {
        if ($tipo_usuario == 'alumno') {
            // Consulta para alumnos
            $sql = "
                SELECT 
                    a.id_articulo, 
                    a.titulo, 
                    a.resumen,
                    a.tipo_trabajo,
                    a.categoria,
                    pa.rol,
                    CASE WHEN pa.rol = 'autor' THEN 'Autor' ELSE 'Coautor' END as participacion,
                    (
                        SELECT COUNT(*) 
                        FROM proyecto_alumno pa2 
                        WHERE pa2.id_proyecto = a.id_articulo AND pa2.rol = 'coautor'
                    ) as num_coautores_internos,
                    (
                        SELECT COUNT(*) 
                        FROM coautor_externo ce 
                        WHERE ce.id_proyecto = a.id_articulo
                    ) as num_coautores_externos,
                    (
                        SELECT COUNT(*) 
                        FROM horario_ponencia hp 
                        WHERE hp.id_proyecto = a.id_articulo
                    ) as tiene_horario
                FROM articulo a
                INNER JOIN proyecto_alumno pa ON a.id_articulo = pa.id_proyecto
                WHERE pa.id_alumno = ?
                ORDER BY a.id_articulo DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_especifico]);
            $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } elseif ($tipo_usuario == 'docente') {
            // Consulta para docentes
            $sql = "
                SELECT 
                    a.id_articulo, 
                    a.titulo, 
                    a.resumen,
                    a.tipo_trabajo,
                    a.categoria,
                    'autor' as rol,
                    'Autor' as participacion,
                    0 as num_coautores_internos,
                    (
                        SELECT COUNT(*) 
                        FROM coautor_externo ce 
                        WHERE ce.id_proyecto = a.id_articulo
                    ) as num_coautores_externos,
                    (
                        SELECT COUNT(*) 
                        FROM horario_ponencia hp 
                        WHERE hp.id_proyecto = a.id_articulo
                    ) as tiene_horario
                FROM articulo a
                INNER JOIN proyecto_docente pd ON a.id_articulo = pd.id_proyecto
                WHERE pd.id_docente = ?
                ORDER BY a.id_articulo DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_especifico]);
            $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } elseif ($tipo_usuario == 'empresa') {
            // Consulta para empresas
            $sql = "
                SELECT 
                    a.id_articulo, 
                    a.titulo, 
                    a.resumen,
                    a.tipo_trabajo,
                    a.categoria,
                    'autor' as rol,
                    'Autor' as participacion,
                    0 as num_coautores_internos,
                    (
                        SELECT COUNT(*) 
                        FROM coautor_externo ce 
                        WHERE ce.id_proyecto = a.id_articulo
                    ) as num_coautores_externos,
                    (
                        SELECT COUNT(*) 
                        FROM horario_ponencia hp 
                        WHERE hp.id_proyecto = a.id_articulo
                    ) as tiene_horario
                FROM articulo a
                WHERE a.id_usuario = ?
                ORDER BY a.id_articulo DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['id_usuario']]);
            $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $mensaje = "Error SQL: " . $e->getMessage();
        $tipo_mensaje = "danger";
        error_log("Error al obtener proyectos: " . $e->getMessage());
    }
}

// Contar total de proyectos
$total_proyectos = count($proyectos);

// Contar autor y coautor
$autor_count = 0;
$coautor_count = 0;
foreach ($proyectos as $p) {
    if (($p['participacion'] ?? '') == 'Autor') $autor_count++;
    if (($p['participacion'] ?? '') == 'Coautor') $coautor_count++;
}

// Definir colores para tipos de trabajo
$colores_tipo = [
    'cartel' => ['bg' => '#ffc107', 'icon' => 'fa-image', 'text' => 'Cartel'],
    'ponencia' => ['bg' => '#17a2b8', 'icon' => 'fa-chalkboard-teacher', 'text' => 'Ponencia'],
    'taller' => ['bg' => '#28a745', 'icon' => 'fa-tools', 'text' => 'Taller'],
    'prototipo' => ['bg' => '#6f42c1', 'icon' => 'fa-cube', 'text' => 'Prototipo']
];
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
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="estilo1.css">
    <title>Mis Proyectos - SIMPOSIO FESC C4</title>
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
        
        .proyectos-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .proyectos-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .proyectos-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .proyectos-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .proyectos-body {
            padding: 40px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid #dee2e6;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: #293e6b;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #293e6b;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #e9ecef;
            overflow-x: auto;
        }
        
        .table thead th {
            background: #293e6b;
            color: white;
            font-weight: 500;
            border: none;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .badge-tipo {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-rol {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-autor { background: #293e6b; color: white; }
        .badge-coautor { background: #6c757d; color: white; }
        
        .badge-coautor-count {
            background: #17a2b8;
            color: white;
            margin-right: 5px;
        }
        
        .badge-horario {
            background: #28a745;
            color: white;
        }
        
        .btn-accion {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin: 0 2px;
            border: none;
            cursor: pointer;
        }
        
        .btn-ver {
            background: #17a2b8;
            color: white;
        }
        
        .btn-ver:hover {
            background: #138496;
        }
        
        .btn-editar {
            background: #ffc107;
            color: #000;
        }
        
        .btn-editar:hover {
            background: #e0a800;
        }
        
        .btn-eliminar {
            background: #dc3545;
            color: white;
        }
        
        .btn-eliminar:hover {
            background: #c82333;
        }
        
        .btn-agregar {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .btn-agregar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .coautores-badges {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody td {
                display: block;
                text-align: right;
                padding: 10px;
                border-bottom: 1px solid #dee2e6;
            }
            
            .table tbody td:last-child {
                border-bottom: none;
            }
            
            .table tbody td::before {
                content: attr(data-label);
                float: left;
                font-weight: bold;
                color: #293e6b;
            }
            
            .coautores-badges {
                justify-content: flex-end;
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
                        <a class="nav-link" href="registrar_trabajos.php"><i class="fas fa-upload me-1"></i>Registrar Trabajo</a>
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

    <div class="proyectos-container">
        <div class="proyectos-card">
            <div class="proyectos-header">
                <h2><i class="fas fa-folder-open me-3"></i>Mis Proyectos</h2>
                <p>Gestiona todos tus trabajos registrados en el simposio</p>
            </div>
            
            <div class="proyectos-body">
                <?php if($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                    <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        <div class="stat-number"><?php echo $total_proyectos; ?></div>
                        <div class="stat-label">Total Proyectos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-pen-fancy"></i></div>
                        <div class="stat-number"><?php echo $autor_count; ?></div>
                        <div class="stat-label">Como Autor</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo $coautor_count; ?></div>
                        <div class="stat-label">Como Coautor</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-number"><?php echo max(0, 10 - $total_proyectos); ?></div>
                        <div class="stat-label">Disponibles</div>
                    </div>
                </div>

                <!-- Botón agregar -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Lista de Proyectos</h4>
                    <a href="registrar_trabajos.php" class="btn-agregar">
                        <i class="fas fa-plus-circle"></i>Nuevo Proyecto
                    </a>
                </div>

                <!-- Tabla de proyectos -->
                <?php if(empty($proyectos)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h4>No tienes proyectos registrados</h4>
                    <p>Comienza registrando tu primer trabajo en el simposio</p>
                    <a href="registrar_trabajos.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>Registrar Trabajo
                    </a>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table" id="tabla-proyectos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Participación</th>
                                <th>Coautores</th>
                                <th>Horario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($proyectos as $proyecto): 
                                $tipo = strtolower($proyecto['tipo_trabajo'] ?? 'ponencia');
                                $color_tipo = $colores_tipo[$tipo] ?? $colores_tipo['ponencia'];
                                $total_coautores = ($proyecto['num_coautores_internos'] ?? 0) + ($proyecto['num_coautores_externos'] ?? 0);
                            ?>
                            <tr>
                                <td data-label="ID">#<?php echo $proyecto['id_articulo']; ?></td>
                                <td data-label="Título">
                                    <strong><?php echo htmlspecialchars($proyecto['titulo'] ?? 'Sin título'); ?></strong>
                                    <?php if(!empty($proyecto['categoria']) && $proyecto['categoria'] != 'Sin categoría'): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($proyecto['categoria']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Tipo">
                                    <span class="badge-tipo" style="background: <?php echo $color_tipo['bg']; ?>; color: white;">
                                        <i class="fas <?php echo $color_tipo['icon']; ?> me-1"></i>
                                        <?php echo $color_tipo['text']; ?>
                                    </span>
                                </td>
                                <td data-label="Participación">
                                    <span class="badge-rol <?php echo ($proyecto['participacion'] ?? '') == 'Autor' ? 'badge-autor' : 'badge-coautor'; ?>">
                                        <i class="fas <?php echo ($proyecto['participacion'] ?? '') == 'Autor' ? 'fa-crown' : 'fa-user-friends'; ?> me-1"></i>
                                        <?php echo $proyecto['participacion'] ?? 'Desconocido'; ?>
                                    </span>
                                </td>
                                <td data-label="Coautores">
                                    <?php if($total_coautores > 0): ?>
                                    <div class="coautores-badges">
                                        <?php if(($proyecto['num_coautores_internos'] ?? 0) > 0): ?>
                                        <span class="badge badge-coautor-count" title="Coautores internos">
                                            <i class="fas fa-user-friends me-1"></i> <?php echo $proyecto['num_coautores_internos']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if(($proyecto['num_coautores_externos'] ?? 0) > 0): ?>
                                        <span class="badge bg-secondary text-white" title="Coautores externos">
                                            <i class="fas fa-user-tie me-1"></i> <?php echo $proyecto['num_coautores_externos']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Sin coautores</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Horario">
                                    <?php if(($proyecto['tiene_horario'] ?? 0) > 0): ?>
                                    <span class="badge badge-horario">
                                        <i class="fas fa-clock me-1"></i> Asignado
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Acciones">
                                    <button class="btn-accion btn-ver" onclick="verProyecto(<?php echo $proyecto['id_articulo']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if(($proyecto['participacion'] ?? '') == 'Autor'): ?>
                                    <button class="btn-accion btn-editar" onclick="editarProyecto(<?php echo $proyecto['id_articulo']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de eliminar este proyecto? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_proyecto" value="<?php echo $proyecto['id_articulo']; ?>">
                                        <button type="submit" class="btn-accion btn-eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            if ($('#tabla-proyectos tbody tr').length > 0) {
                $('#tabla-proyectos').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                    },
                    order: [[0, 'desc']],
                    pageLength: 10,
                    responsive: true
                });
            }
        });

        function verProyecto(id) {
            window.location.href = 'ver_proyecto.php?id=' + id;
        }

        function editarProyecto(id) {
            window.location.href = 'editar_proyecto.php?id=' + id;
        }
    </script>
</body>
</html>