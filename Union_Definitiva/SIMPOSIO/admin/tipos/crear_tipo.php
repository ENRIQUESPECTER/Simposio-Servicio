<?php session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
?>

<h2>Crear tipo de actividad</h2>

<form action="guardar_tipo.php" method="POST">

<label>Nombre</label>
<input type="text" name="nombre">

<label>Duración en minutos</label>
<input type="number" name="duracion">

<button type="submit">Guardar</button>

</form>