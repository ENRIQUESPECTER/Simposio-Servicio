<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
} 
require "../includes/conexion.php";

$sql="SELECT 
a.titulo,
a.hora_inicio,
a.hora_fin,
t.nombre as tipo,
e.titulo as evento
FROM actividad_evento a
JOIN tipo_actividad t ON a.id_tipo=t.id_tipo
JOIN evento e ON a.id_evento=e.id_evento
ORDER BY e.fecha,a.hora_inicio";

$result=$conexion->query($sql);

?>
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
<h2>Agenda completa del Simposio</h2>

<table border="1">

<tr>
<th>Evento</th>
<th>Actividad</th>
<th>Tipo</th>
<th>Hora inicio</th>
<th>Hora fin</th>
</tr>

<?php while($fila=$result->fetch_assoc()): ?>

<tr>

<td><?php echo $fila['evento']; ?></td>

<td><?php echo $fila['titulo']; ?></td>

<td><?php echo $fila['tipo']; ?></td>

<td><?php echo $fila['hora_inicio']; ?></td>

<td><?php echo $fila['hora_fin']; ?></td>

</tr>

<?php endwhile; ?>

</table>