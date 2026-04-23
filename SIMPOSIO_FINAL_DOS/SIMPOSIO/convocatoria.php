<?php
session_start();
require_once 'includes/conexion.php';
require_once 'includes/auth.php';
require_once 'includes/funciones.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convocatoria - SIMPOSIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .convocatoria-hero {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            padding: 4rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .convocatoria-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .convocatoria-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .section-card:hover {
            transform: translateY(-5px);
        }
        .section-card h3 {
            color: #293e6b;
            border-left: 4px solid #D59F0F;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .fechas-lista {
            list-style: none;
            padding-left: 0;
        }
        .fechas-lista li {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
        }
        .fechas-lista i {
            color: #D59F0F;
            font-size: 1.2rem;
            margin-right: 1rem;
            margin-top: 0.2rem;
        }
        .btn-descarga {
            background: linear-gradient(135deg, #293e6b, #1a2b4a);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-descarga:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41,62,107,0.3);
            color: white;
        }
        @media (max-width: 768px) {
            .convocatoria-title { font-size: 1.8rem; }
            .convocatoria-hero { padding: 2rem 0; }
            .section-card { padding: 1.5rem; }
        }
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


    <div style="height: 76px;"></div>

    <div class="convocatoria-hero">
        <div class="container text-center">
            <h1 class="convocatoria-title"><i class="fas fa-scroll me-3"></i>Convocatoria</h1>
            <p class="convocatoria-subtitle">SIMPOSIO INTERNACIONAL DE MATEMÁTICAS FESC C4 2026</p>
            <p>Del 10 al 12 de abril de 2026 · FES Cuautitlán, UNAM</p>
        </div>
    </div>

    <div class="container">
        <div class="row g-4">
            <!-- Columna izquierda: Contenido principal -->
            <div class="col-lg-6">
                <div class="section-card">
                    <h3><i class="fas fa-chalkboard-teacher me-2"></i>Objetivo</h3>
                    <p>Reunir a investigadores, docentes, estudiantes y profesionales del sector empresarial para compartir avances en matemáticas, fomentar la colaboración interdisciplinaria y difundir proyectos de impacto social y tecnológico.</p>

                    <h3 class="mt-4"><i class="fas fa-tasks me-2"></i>Modalidades de participación</h3>
                    <ul>
                        <li><strong>Ponencia oral:</strong> 30 minutos de presentación (incluye 5 minutos de preguntas).</li>
                        <li><strong>Cartel:</strong> Presentación en formato de póster (tamaño 90x120 cm).</li>
                        <li><strong>Taller:</strong> Sesión práctica de 120 minutos, con material didáctico.</li>
                        <li><strong>Prototipo:</strong> Demostración de proyectos aplicados (software, hardware, modelos matemáticos).</li>
                    </ul>

                    <h3 class="mt-4"><i class="fas fa-file-alt me-2"></i>Envío de trabajos</h3>
                    <p>Los interesados deben registrarse en el sistema y subir su trabajo a través de la opción <a href="registrar_trabajos.php" class="text-decoration-none">Registrar Trabajo</a>. Los resúmenes serán evaluados por el comité científico.</p>

                    <a href="registrar_trabajos.php" class="btn-descarga mt-3"><i class="fas fa-upload me-2"></i>Registrar trabajo ahora</a>
                </div>
            </div>

            <!-- Columna derecha: Fechas -->
            <div class="col-lg-6">
                <div class="section-card">
                    <h3><i class="fas fa-calendar-alt me-2"></i>Fechas importantes</h3>
                    <ul class="fechas-lista">
                        <li><i class="fas fa-calendar-check"></i> <strong>Recepción de resúmenes:</strong> 19 de febrero - 13 de mayo de 2026</li>
                        <li><i class="fas fa-envelope-open-text"></i> <strong>Notificación de aceptación:</strong> 20 de mayo de 2026</li>
                        <li><i class="fas fa-user-check"></i> <strong>Registro de participantes:</strong> Hasta el 30 de mayo de 2026</li>
                        <li><i class="fas fa-calendar-week"></i> <strong>Evento:</strong> 10, 11 y 12 de abril de 2026</li>
                    </ul>
                    <hr>
                    <p class="mt-3"><i class="fas fa-info-circle me-2"></i> Para más información, escribe a <a href="mailto:info@simposiofesc.com">info@simposiofesc.com</a> o consulta nuestras redes sociales.</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>