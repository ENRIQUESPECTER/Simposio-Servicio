<?php
require_once 'includes/conexion.php';
require_once 'includes/funciones.php';


// Obtener proyectos destacados (3 trabajos aprobados con imagen principal)
$proyectos_destacados = [];
$sql = "
    SELECT a.id_articulo, a.titulo, a.tipo_trabajo, a.categoria, a.resumen,
           (SELECT nombre_archivo FROM proyecto_imagen WHERE id_articulo = a.id_articulo AND es_principal = 1 LIMIT 1) as imagen_principal,
           e.id_actividad, e.id_articulo, e.titulo, e.resumen, e.descripcion, e.referencias
    FROM articulo a
    LEFT JOIN actividad_evento e ON a.id_articulo = e.id_articulo
    WHERE a.estado = 'aprobado'
    ORDER BY a.fecha_registro DESC
";
$result = $conexion->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $proyectos_destacados[] = $row;
    }
}

/*$proyecto_info = [];
$stmt = $conexion->prepare("SELECT e.id_actividad, e.id_articulo, e.titulo, e.resumen, e.descripcion, e.referencias FROM actividad_evento e");
$stmt->execute();
$result_info = $stmt->get_result();
if($result_info) {
    while ($row = $result_info->fetch_assoc()) {
        $proyecto_info[] = $row;
    }
}*/

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
            <a class="navbar-brand" href="home.html">
                <i class="fas fa-calculator me-2"></i>UNAM FES CUAUTITLAN C4
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="home.html"><i class="fas fa-home me-1"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="red_universitaria.php"><i class="fas fa-chalkboard me-1"></i>Red Universitaria de Proyectos</a></li>
                    <li class="nav-item"><a class="nav-link" href="convocatoria.php"><i class="fas fa-scroll me-1"></i>Convocatoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="empresas.html"><i class="fas fa-address-card me-1"></i>Empresas</a></li>
                    <li class="nav-item"><a class="nav-link" href="programa/index_programa.php"><i class="fas fa-user me-1"></i>Alumnos</a></li>
                    <li class="nav-item"><a class="nav-link" href="simposio.php"><i class="fas fa-calendar me-1"></i>Eventos</a></li>
                    <li class="nav-item"><a class="nav-link" href="contactanos.php"><i class="fas fa-phone me-1"></i>Contactanos</a></li>
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
        <!-- Proyectos Destacados -->
        <section class="mt-5">
            <h3 class="text-center mb-4 colorazul text-white p-3 rounded">
                <i class="fas fa-star me-2"></i>Sección de Proyectos Publicados
            </h3>
            <div class="row">
                <?php if (count($proyectos_destacados) > 0): ?>
                    <?php foreach ($proyectos_destacados as $proy): ?>
                    <div class="col-md-4" style="margin-bottom: 1.5rem;">
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
                                <p class="card-text"><?php echo ucfirst($proy['resumen']) ?></p>
                                <p class="card-text"><?php echo ucfirst($proy['descripcion']) ?></p>
                                <p class="card-text"><?php echo ucfirst($proy['referencias']) ?></p>
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
                <i class="fas fa-calendar-alt me-2"></i>Próximos Eventos por venir
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
    </div>

    <!-- Footer -->
    <footer class="colorazul text-white mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4</h5>
                    <p class="text-white-50">Congreso Internacional sobre la Enseñanza y Aplicación de las Matemáticas</p>
                    <p class="text-white-50"><i class="fas fa-map-marker-alt me-2"></i><a href="" style="text-decoration: none; color: rgba(255, 255, 255, 0.5);">FES Cuautitlán, UNAM</a></p>
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
                        <a href="https://www.facebook.com/fescunamoficial/about?locale=es_LA" class="text-white fs-3"><i class="fab fa-facebook"></i></a>
                        <a href="https://x.com/FESC_UNAM?fbclid=IwY2xjawQyQHxleHRuA2FlbQIxMABicmlkETFvUEhaR0VMQmo5UEQ1b0M0c3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHunbJB2FGEliNtdbtCRQ5rraIYqxrw-P_F1GfK3vbH2iH1LCVWqhSXpl2LP7_aem_vLlrun1rax8EMbKR0qgxBQ" class="text-white fs-3"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/fescunamoficial?fbclid=IwY2xjawQyQnJleHRuA2FlbQIxMABicmlkETFjOU9lY2lsNWhBREVmV1Nxc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHvwGr8ZN8ksdMDFGCUCpjhMbJJW9cbvuMXJ5qhpo6m2tuK4zV1DqLw3vk0vB_aem_XcaPSOTLV8iGNi3yf750EQ" class="text-white fs-3"><i class="fab fa-instagram"></i></a>
                        <a href="https://youtube.com/@fescunamoficial9877?si=J4aNbVU3BTRfEzd7" class="text-white fs-3"><i class="fab fa-youtube"></i></a>
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