<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';

// Obtener datos del usuario logueado si existe
$usuario_actual = null;
$total_trabajos = 0;
$total_autor = 0;
$total_coautor = 0;

if (esta_logeado()) {
    // Obtener información completa del usuario
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
    $usuario_actual = $stmt->get_result()->fetch_assoc();

    if ($usuario_actual) {
        $tipo = $usuario_actual['tipo_usuario'];
        $id_especifico = obtener_id_especifico($usuario_actual);

        if ($id_especifico) {
            if ($id_especifico['tipo'] == 'alumno') {
                // Contar trabajos donde es autor
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo_alumno WHERE id_alumno = ? AND rol = 'autor'");
                $stmt->bind_param("i", $id_especifico['id']);
                $stmt->execute();
                $total_autor = $stmt->get_result()->fetch_row()[0];

                // Contar trabajos donde es coautor
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo_alumno WHERE id_alumno = ? AND rol = 'coautor'");
                $stmt->bind_param("i", $id_especifico['id']);
                $stmt->execute();
                $total_coautor = $stmt->get_result()->fetch_row()[0];

                $total_trabajos = $total_autor + $total_coautor;
            } elseif ($id_especifico['tipo'] == 'docente') {
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo_docente WHERE id_docente = ?");
                $stmt->bind_param("i", $id_especifico['id']);
                $stmt->execute();
                $total_autor = $stmt->get_result()->fetch_row()[0];
                $total_trabajos = $total_autor;
            } elseif ($id_especifico['tipo'] == 'empresa') {
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM articulo WHERE id_usuario = ?");
                $stmt->bind_param("i", $_SESSION['id_usuario']);
                $stmt->execute();
                $total_autor = $stmt->get_result()->fetch_row()[0];
                $total_trabajos = $total_autor;
            }
        }
    }
}

// Obtener proyectos destacados (3 trabajos aprobados con imagen principal)
$proyectos_destacados = [];
$sql = "
    SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria,
           (SELECT nombre_archivo FROM proyecto_imagen WHERE id_articulo = a.id_articulo AND es_principal = 1 LIMIT 1) as imagen_principal
    FROM articulo a
    WHERE a.estado = 'aprobado'
    ORDER BY a.fecha_registro DESC
    LIMIT 3
";
$result = $conexion->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $proyectos_destacados[] = $row;
    }
}

// Obtener eventos próximos (futuros)
$eventos_proximos = [];
$sql_eventos = "
    SELECT id_evento, titulo, fecha, hora_inicio, hora_fin
    FROM evento
    WHERE fecha >= CURDATE()
    ORDER BY fecha ASC
    LIMIT 3
";
$result_eventos = $conexion->query($sql_eventos);
if ($result_eventos) {
    while ($row = $result_eventos->fetch_assoc()) {
        $eventos_proximos[] = $row;
    }
}

// Verificar si el usuario es administrador
$es_admin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;
if ($es_admin) {
    // Obtener estadísticas para el panel de admin
    $total_usuarios = $conexion->query("SELECT COUNT(*) FROM usuario")->fetch_row()[0];
    $total_articulos = $conexion->query("SELECT COUNT(*) FROM articulo")->fetch_row()[0];
    $total_aprobados = $conexion->query("SELECT COUNT(*) FROM articulo WHERE estado = 'aprobado'")->fetch_row()[0];
    $total_pendientes = $conexion->query("SELECT COUNT(*) FROM articulo WHERE estado = 'pendiente'")->fetch_row()[0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMPOSIO FESC C4 - Congreso Internacional de Matemáticas</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <style>
        /* Estilos adicionales (pueden complementar los existentes) */
        .carousel-item img { height: 400px; object-fit: cover; }
        .stats-card { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stats-number { font-size: 2.5rem; font-weight: bold; color: #293e6b; }
        .card { transition: transform 0.3s; margin-bottom: 20px; }
        .card:hover { transform: translateY(-5px); }
        .btn-primary { background-color: #293e6b; border-color: #293e6b; }
        .btn-primary:hover { background-color: #1a2b4a; border-color: #1a2b4a; }
        .colordorado { background-color: #D59F0F !important; }
        .colorazul { background-color: #293e6b !important; }
    </style>
</head>
<body>

<!-- Navbar (puedes incluir la que ya tienes en includes/navbar.php, pero por ahora la dejamos simple) -->
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
                <li class="nav-item"><a class="nav-link" href="#inicio"><i class="fas fa-home me-1"></i>Inicio</a></li>
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

<!-- Espaciado para el menú fijo -->
<div style="height: 76px;" id="inicio"></div>

<!-- Carrusel de imágenes -->
<div class="container-fluid px-0">
    <div id="carouselExample" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#carouselExample" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#carouselExample" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#carouselExample" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="Assets/fesc4.jpg" class="d-block w-100" alt="Simposio">
                <div class="carousel-caption d-none d-md-block">
                    <h5>Bienvenido al SIMPOSIO FESC C4</h5>
                    <p>Congreso Internacional de Matemáticas</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="Assets/carruselunam1.jpg" class="d-block w-100" alt="UNAM">
                <div class="carousel-caption d-none d-md-block">
                    <h5>Investigación de vanguardia</h5>
                    <p>Comparte tus conocimientos</p>
                </div>
            </div>
            <div class="carousel-item">
                <img src="Assets/carruselunam2.jpg" class="d-block w-100" alt="Matemáticas">
                <div class="carousel-caption d-none d-md-block">
                    <h5>Red de colaboración</h5>
                    <p>Conecta con expertos</p>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</div>

<div class="container mt-4">
    <?php if (esta_logeado() && $usuario_actual): ?>
        <!-- Panel de bienvenida con estadísticas -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="main-content p-4 bg-light rounded">
                    <h2 class="colorazul text-white p-3 rounded mb-4">
                        <i class="fas fa-tachometer-alt me-2"></i>Panel Principal
                    </h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-card">
                                <h4><i class="fas fa-user me-2"></i>Bienvenido</h4>
                                <p class="h4"><?php echo htmlspecialchars($usuario_actual['nombre'] . ' ' . ($usuario_actual['apellidos'] ?? '')); ?></p>
                                <p><strong>Tipo:</strong> 
                                    <span class="badge bg-<?php echo $usuario_actual['tipo_usuario'] == 'docente' ? 'primary' : ($usuario_actual['tipo_usuario'] == 'alumno' ? 'success' : 'info'); ?>">
                                        <?php echo ucfirst($usuario_actual['tipo_usuario']); ?>
                                    </span>
                                </p>
                                <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario_actual['correo']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <i class="fas fa-pen-fancy fa-3x mb-3"></i>
                                        <h4>Trabajos como Autor</h4>
                                        <p class="stats-number"><?php echo $total_autor; ?></p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <h4>Trabajos como Coautor</h4>
                                        <p class="stats-number"><?php echo $total_coautor; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-12 text-center">
                            <a href="registrar_trabajos.php" class="btn colordorado text-white btn-lg me-2">
                                <i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Trabajo
                            </a>
                            <a href="mis_proyectos.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-folder-open me-2"></i>Ver Mis Proyectos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-2"></i>
            Por favor, <a href="login.php" class="alert-link">inicie sesión</a> para ver su información personalizada o <a href="registro.php" class="alert-link">regístrese</a> si no tiene una cuenta.
        </div>
    <?php endif; ?>

    <!-- Proyectos Destacados -->
    <section class="mt-5">
        <h3 class="text-center mb-4 colorazul text-white p-3 rounded">
            <i class="fas fa-star me-2"></i>Proyectos Destacados
        </h3>
        <div class="row">
            <?php if (count($proyectos_destacados) > 0): ?>
                <?php foreach ($proyectos_destacados as $proy): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <?php if (!empty($proy['imagen_principal'])): ?>
                            <img src="uploads/proyectos/<?php echo $proy['imagen_principal']; ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="Imagen del proyecto">
                        <?php else: ?>
                            <div class="card-img-top bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="fas fa-image fa-3x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($proy['titulo']); ?></h5>
                            <p class="card-text">
                                <small class="text-muted"><?php echo ucfirst($proy['tipo_trabajo']); ?> | <?php echo htmlspecialchars($proy['categoria']); ?></small>
                            </p>
                            <div class="text-center">
                                <a href="ver_proyecto.php?id=<?php echo $proy['id_articulo']; ?>" class="btn btn-primary btn-sm">Ver más</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No hay proyectos destacados disponibles.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Eventos Próximos -->
    <section class="mt-5">
        <h3 class="text-center mb-4 colorazul text-white p-3 rounded">
            <i class="fas fa-calendar-alt me-2"></i>Próximos Eventos
        </h3>
        <div class="row">
            <?php if (count($eventos_proximos) > 0): ?>
                <?php foreach ($eventos_proximos as $evento): ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($evento['titulo']); ?></h5>
                            <p class="card-text">
                                <i class="fas fa-calendar-day me-2"></i><?php echo date('d/m/Y', strtotime($evento['fecha'])); ?><br>
                                <i class="fas fa-clock me-2"></i><?php echo substr($evento['hora_inicio'],0,5); ?> - <?php echo substr($evento['hora_fin'],0,5); ?>
                            </p>
                            <div class="text-center">
                                <a href="programa/detalle_programa.php?id=<?php echo $evento['id_evento']; ?>" class="btn btn-primary btn-sm">Ver agenda</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">No hay eventos próximos programados.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Panel de Administración (solo para administradores) -->
    <?php if ($es_admin): ?>
    <div class="row mt-5">
        <div class="col-md-12">
            <div class="main-content p-4 bg-light rounded">
                <h3 class="colorazul text-white p-3 rounded mb-4">
                    <i class="fas fa-cog me-2"></i>Panel de Administración
                </h3>
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-users fa-2x colorazul"></i>
                            <h5>Usuarios</h5>
                            <p class="stats-number"><?php echo $total_usuarios; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-file-alt fa-2x colorazul"></i>
                            <h5>Total Trabajos</h5>
                            <p class="stats-number"><?php echo $total_articulos; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle fa-2x colorazul"></i>
                            <h5>Aprobados</h5>
                            <p class="stats-number"><?php echo $total_aprobados; ?></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-clock fa-2x colorazul"></i>
                            <h5>Pendientes</h5>
                            <p class="stats-number"><?php echo $total_pendientes; ?></p>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="admin/dashboard.php" class="btn btn-primary">Ir al panel completo</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="colorazul text-white mt-5 py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4</h5>
                <p class="text-white-50">Congreso Internacional sobre la Enseñanza y Aplicación de las Matemáticas</p>
                <p class="text-white-50"><i class="fas fa-map-marker-alt me-2"></i>FES Cuautitlán, UNAM</p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3"><i class="fas fa-address-card me-2"></i><a class="text-white" href="contactanos.php" style="text-decoration: none;">Contactanos</a></h5>
                <p class="text-white-50"><i class="fas fa-envelope me-2"></i>info@simposiofesc.com</p>
                <p class="text-white-50"><i class="fas fa-phone me-2"></i>(55) 1234-5678</p>
                <p class="text-white-50"><i class="fas fa-clock me-2"></i>Lun-Vie: 9:00 - 18:00</p>
            </div>
            <div class="col-md-4">
                <h5 class="mb-3"><i class="fas fa-share-alt me-2"></i>Síguenos</h5>
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
            <p class="mb-0 text-white-50"><i class="far fa-copyright me-2"></i><?php echo date('Y'); ?> Congreso Internacional de Matemáticas. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>