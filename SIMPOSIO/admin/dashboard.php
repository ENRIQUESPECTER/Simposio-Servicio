<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
} 
require "../conexion.php";

/* contar eventos */
$sql_eventos = "SELECT COUNT(*) as total FROM evento";
$res_eventos = $conexion->query($sql_eventos);
$eventos = $res_eventos->fetch_assoc()['total'];

/* contar actividades */
$sql_act = "SELECT COUNT(*) as total FROM actividad_evento";
$res_act = $conexion->query($sql_act);
$actividades = $res_act->fetch_assoc()['total'];

/* contar tipos */
$sql_tipo = "SELECT COUNT(*) as total FROM tipo_actividad";
$res_tipo = $conexion->query($sql_tipo);
$tipos = $res_tipo->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html>
<head>

<title>Dashboard Simposio</title>

<style>

body{
font-family:Arial;
background:#f3f5f7;
margin:0;
}

.header{
background:#003366;
color:white;
padding:20px;
}

.dashboard{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:20px;
padding:40px;
}

.card{
background:white;
padding:30px;
border-radius:10px;
box-shadow:0 2px 8px rgba(0,0,0,.2);
text-align:center;
}

.card h2{
font-size:40px;
margin:0;
color:#003366;
}

.menu{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:20px;
padding:40px;
}

.btn{
background:#003366;
color:white;
padding:20px;
text-align:center;
border-radius:8px;
text-decoration:none;
}

</style>

</head>

<body>

<div class="header">
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

</div>

<div class="menu">

<a class="btn" href="eventos/evento.php">Crear Evento</a>

<a class="btn" href="eventos/lista_eventos.php">Gestionar Eventos</a>

<a class="btn" href="actividades/lista_actividades.php">Actividades</a>

<a class="btn" href="tipos/lista_tipos.php">Tipos de actividad</a>

<a class="btn" href="agenda_admin.php">Agenda</a>

<a class="btn" href="logout.php">Cerrar sesión</a>

</div>

</body>
</html>