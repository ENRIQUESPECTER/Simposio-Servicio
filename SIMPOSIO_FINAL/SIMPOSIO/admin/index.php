<?php
session_start();
require "../includes/conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.php");
    exit();
} 

/*contar eventos*/
$sql_eventos = "SELECT COUNT(*) as total FROM evento";
$res_eventos = $conexion->query($sql_eventos);
$eventos = $res_eventos->fetch_assoc()['total'];

/*contar actividades*/
$sql_act = "SELECT COUNT(*) as total FROM actividad_evento";
$res_act = $conexion->query($sql_act);
$actividades = $res_act->fetch_assoc()['total'];

/*contar tipos*/
$sql_tipo = "SELECT COUNT(*) as total FROM tipo_actividad";
$res_tipo = $conexion->query($sql_tipo);
$tipos = $res_tipo->fetch_assoc()['total'];


$stmt = $conexion->prepare("SELECT COUNT(*) as pendientes FROM articulo WHERE estado = 'pendiente'");
$stmt->execute();
$pendientes = $stmt->get_result()->fetch_assoc()['pendientes'];

?>

<!DOCTYPE html>
<html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="Css/interfaz_usuario.css">
    <link rel="stylesheet" href="../Css/admin.css">
    <style>
        /* Estilos adicionales (pueden complementar los existentes) */
        .carousel-item img { height: 400px; object-fit: cover; }
        .stats-number { font-size: 2.5rem; font-weight: bold; color: #293e6b; }
        .card { transition: transform 0.3s; margin-bottom: 20px; }
        .card:hover { transform: translateY(-5px); }
        .btn-primary { background-color: #293e6b; border-color: #293e6b; }
        .btn-primary:hover { background-color: #1a2b4a; border-color: #1a2b4a; }
        .colordorado { background-color: #D59F0F !important; }
        .colorazul { background-color: #293e6b !important; }
    </style>
</head>
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                <li class="nav-item"><a class="nav-link" href="actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                <li class="nav-item"><a class="nav-link" href="trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario']  ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </li>
            </ul>
        </div>
    </div>
</nav>
    <body>
        <div class="header" style="margin-top: 5rem;">
            <h1>Dashboard del Simposio</h1>
            <h2>Administrador: <?php echo $_SESSION['usuario']; ?></h2>
        </div>
        
        <div class="dashboard">        
            <div class="card">
                <h2 class="stats-number"><?php echo $eventos ?></h2>
                <p>Eventos</p>
            </div>
            <div class="card">
                <h2 class="stats-number"><?php echo $actividades ?></h2>
                <p>Actividades</p>
            </div>
            <div class="card">
                <h2 class="stats-number"><?php echo $tipos ?></h2>
                <p>Tipos de actividad</p>
            </div>
            <div class="card">
                <h2 class="stats-number"><?php echo $pendientes; ?></h2>
                <p>Trabajos pendientes de aprobación</p>
                <a href="trabajos/pendientes.php" class="btn btn-primary">Revisar</a>
            </div>
        </div>
        
        <div class="menu">
            <a class="btn" href="eventos/crear_evento.php">Crear Evento</a>
            <a class="btn" href="eventos/lista_eventos.php">Gestionar Eventos</a>
            <a class="btn" href="actividades/lista_actividades.php">Actividades</a>
            <a class="btn" href="tipos/lista_tipos.php">Tipos de actividad</a>
            <a class="btn" href="logout.php">Cerrar sesión</a>
        </div>
        
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>