<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../conexion.php";

$sql="SELECT 
a.id_actividad,
a.titulo,
a.hora_inicio,
a.hora_fin,
t.nombre as tipo,
e.titulo as evento

FROM actividad_evento a

JOIN tipo_actividad t
ON a.id_tipo=t.id_tipo

JOIN evento e
ON a.id_evento=e.id_evento

ORDER BY e.fecha,a.hora_inicio";

$result=$conexion->query($sql);

?>

<h2>Actividades Registradas</h2>

<table border="1">

<tr>
<th>Evento</th>
<th>Actividad</th>
<th>Tipo</th>
<th>Inicio</th>
<th>Fin</th>
<th>Acciones</th>
</tr>

<?php while($fila=$result->fetch_assoc()): ?>

<tr>

<td><?php echo $fila['evento']; ?></td>

<td><?php echo $fila['titulo']; ?></td>

<td><?php echo $fila['tipo']; ?></td>

<td><?php echo $fila['hora_inicio']; ?></td>

<td><?php echo $fila['hora_fin']; ?></td>

<td>

<a href="../eventos/editar_actividad.php?id=<?php echo $fila['id_actividad']; ?>">Editar</a>

<a href="../eventos/eliminar_actividad.php?id=<?php echo $fila['id_actividad']; ?>">Eliminar</a>

</td>

</tr>

<?php endwhile; ?>

</table>