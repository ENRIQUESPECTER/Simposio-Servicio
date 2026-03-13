<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../conexion.php";

$sql="SELECT * FROM tipo_actividad";

$result=$conexion->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../Css/admin.css">
    <title>Lista Tipos Actividades</title>
</head>
<body>
    <h2>Tipos de Actividad</h2>

<a href="crear_tipo.php">Nuevo tipo</a>

<table border="1">

<tr>
<th>Tipo</th>
<th>Duración</th>
</tr>

<?php while($tipo=$result->fetch_assoc()): ?>

<tr>

<td><?php echo $tipo['nombre']; ?></td>

<td><?php echo $tipo['duracion_minutos']; ?> min</td>

</tr>

<?php endwhile; ?>

</table>
</body>
</html>