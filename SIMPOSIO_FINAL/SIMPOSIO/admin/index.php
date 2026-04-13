<?php
session_start();
require "../includes/conexion.php";
include '../includes/navbar_admin.php';
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
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
        <title>Dashboard Simposio</title>
        <link rel="stylesheet" href="../Css/admin.css">
    </head>
    
    <body>
        <div class="header" style="margin-top: 5rem;">
            <h1>Dashboard del Simposio</h1>
            <p>Administrador: <?php echo $_SESSION['usuario']; ?></p>
        </div>
        
        <div class="dashboard">        
            <div class="card">
                <h2><?php echo $eventos ?></h2>
                <p>Eventos</p>
            </div>
            <div class="card">
                <h2><?php echo $actividades ?></h2>
                <p>Actividades</p>
            </div>
            <div class="card">
                <h2><?php echo $tipos ?></h2>
                <p>Tipos de actividad</p>
            </div>
            <div class="card">
                <h2>-</h2>
                <p>Espacios libres</p>
            </div>
            <div class="card">
                <h2><?php echo $pendientes; ?></h2>
                <p>Trabajos pendientes de aprobación</p>
                <a href="trabajos/pendientes.php" class="btn btn-primary">Revisar</a>
            </div>
        </div>
        
        <div class="menu">
            <a class="btn" href="eventos/crear_evento.php">Crear Evento</a>
            <a class="btn" href="eventos/lista_eventos.php">Gestionar Eventos</a>
            <a class="btn" href="actividades/lista_actividades.php">Actividades</a>
            <a class="btn" href="tipos/lista_tipos.php">Tipos de actividad</a>
            <a class="btn" href="agenda_admin.php">Agenda</a>
            <a class="btn" href="logout.php">Cerrar sesión</a>
        </div>
        
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>