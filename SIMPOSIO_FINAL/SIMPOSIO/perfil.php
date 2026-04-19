<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';

// Verificar autenticación
if (!esta_logeado()) {
    header('Location: login.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';

// Obtener datos del usuario actual
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

$tipo = $usuario['tipo_usuario'];

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'actualizar') {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $correo = trim($_POST['correo']);
    $direccion = trim($_POST['direccion']);
    
    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
    if (empty($correo)) $errores[] = "El correo es obligatorio.";
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "El correo no es válido.";
    
    // Datos específicos según tipo
    if ($tipo == 'alumno') {
        $matricula = trim($_POST['matricula']);
        $carrera = trim($_POST['carrera']);
        $semestre = intval($_POST['semestre']);
        if (empty($matricula)) $errores[] = "La matrícula es obligatoria.";
    } elseif ($tipo == 'docente') {
        $especialidad = trim($_POST['especialidad']);
        $grado_academico = trim($_POST['grado_academico']);
    } elseif ($tipo == 'empresa') {
        $nombre_empresa = trim($_POST['nombre_empresa']);
        $sector = trim($_POST['sector']);
    }
    
    if (empty($errores)) {
        $conexion->begin_transaction();
        try {
            // Actualizar usuario
            $stmt = $conexion->prepare("UPDATE usuario SET nombre = ?, apellidos = ?, correo = ?, direccion = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssssi", $nombre, $apellidos, $correo, $direccion, $_SESSION['id_usuario']);
            $stmt->execute();
            
            // Actualizar tabla específica
            if ($tipo == 'alumno') {
                $stmt = $conexion->prepare("UPDATE alumno SET matricula = ?, carrera = ?, semestre = ? WHERE id_usuario = ?");
                $stmt->bind_param("ssii", $matricula, $carrera, $semestre, $_SESSION['id_usuario']);
                $stmt->execute();
            } elseif ($tipo == 'docente') {
                $stmt = $conexion->prepare("UPDATE docente SET especialidad = ?, grado_academico = ? WHERE id_usuario = ?");
                $stmt->bind_param("ssi", $especialidad, $grado_academico, $_SESSION['id_usuario']);
                $stmt->execute();
            } elseif ($tipo == 'empresa') {
                $stmt = $conexion->prepare("UPDATE empresa SET nombre_empresa = ?, sector = ? WHERE id_usuario = ?");
                $stmt->bind_param("ssi", $nombre_empresa, $sector, $_SESSION['id_usuario']);
                $stmt->execute();
            }
            
            $conexion->commit();
            $mensaje = "Perfil actualizado correctamente.";
            $tipo_mensaje = "success";
            
            // Recargar datos actualizados
            header("refresh:2;url=perfil.php");
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - SIMPOSIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <style>
        /* Estilos personalizados para un diseño profesional */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .profile-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 2rem;
        }
        .profile-header {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .profile-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 4rem;
        }
        .profile-body {
            padding: 2rem;
        }
        .form-label {
            font-weight: 600;
            color: #293e6b;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #D59F0F;
            box-shadow: 0 0 0 0.2rem rgba(213, 159, 15, 0.25);
        }
        .btn-save {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41,62,107,0.3);
        }
        .btn-cancel {
            background: #6c757d;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .profile-header { padding: 1.5rem; }
            .profile-avatar { width: 80px; height: 80px; font-size: 2.5rem; }
        }
    </style>
</head>
<body>
    <!-- Navbar (mismo que usamos en index.php) -->
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
                            <?php if (es_docente()): ?>
                                <li class="nav-item">
                                    <a class="dropdown-item" href="revisiones.php"><i class="fas fa-tasks me-1"></i>Mis revisiones
                                    <?php if ($revisiones_pendientes > 0): ?>
                                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $revisiones_pendientes; ?></span>
                                    <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
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

    <div style="height: 76px;"></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>Mi Perfil</h3>
                        <p class="mb-0">Gestiona tu información personal</p>
                    </div>
                    <div class="profile-body">
                        <?php if ($mensaje): ?>
                        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                            <?php echo $mensaje; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="accion" value="actualizar">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Apellidos</label>
                                    <input type="text" name="apellidos" class="form-control" value="<?php echo htmlspecialchars($usuario['apellidos'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Correo electrónico *</label>
                                <input type="email" name="correo" class="form-control" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?>">
                            </div>

                            <?php if ($tipo == 'alumno'): ?>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Matrícula *</label>
                                    <input type="text" name="matricula" class="form-control" value="<?php echo htmlspecialchars($usuario['matricula'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Carrera</label>
                                    <input type="text" name="carrera" class="form-control" value="<?php echo htmlspecialchars($usuario['carrera'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Semestre</label>
                                    <input type="number" name="semestre" class="form-control" value="<?php echo htmlspecialchars($usuario['semestre'] ?? ''); ?>" min="1" max="12">
                                </div>
                            </div>
                            <?php elseif ($tipo == 'docente'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Especialidad</label>
                                    <input type="text" name="especialidad" class="form-control" value="<?php echo htmlspecialchars($usuario['especialidad'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Grado académico</label>
                                    <input type="text" name="grado_academico" class="form-control" value="<?php echo htmlspecialchars($usuario['grado_academico'] ?? ''); ?>">
                                </div>
                            </div>
                            <?php elseif ($tipo == 'empresa'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nombre de la empresa</label>
                                    <input type="text" name="nombre_empresa" class="form-control" value="<?php echo htmlspecialchars($usuario['nombre_empresa'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sector</label>
                                    <input type="text" name="sector" class="form-control" value="<?php echo htmlspecialchars($usuario['sector'] ?? ''); ?>">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <a href="index.php" class="btn btn-cancel text-white"><i class="fas fa-times me-2"></i>Cancelar</a>
                                <button type="submit" class="btn btn-save text-white"><i class="fas fa-save me-2"></i>Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="colorazul text-white mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4</h5>
                    <p class="text-white-50">Congreso Internacional sobre la Enseñanza y Aplicación de las Matemáticas</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">Contacto</h5>
                    <p class="text-white-50"><i class="fas fa-envelope me-2"></i>info@simposiofesc.com</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Síguenos</h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white fs-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white fs-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white fs-3"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr class="border-white-50">
            <div class="text-center">
                <p class="mb-0 text-white-50"><i class="far fa-copyright me-2"></i><?php echo date('Y'); ?> Congreso Internacional de Matemáticas. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>