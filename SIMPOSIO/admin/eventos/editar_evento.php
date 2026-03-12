<?php
session_start();
require "../../conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}

$id_evento = $_GET['id_evento'];
$sql = "SELECT * FROM evento WHERE id_evento = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i",$id_evento);
$stmt->execute();
$result = $stmt->get_result();

$evento=$result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificacion de Eventos</title>
</head>
<body>
    <h2>Editar Evento</h2>
    <form action="actualizar_evento.php" method="POST">
        <input type="hidden" name="id_evento" value="<?php echo $evento['id_evento']; ?>">

        <label>Titulo</label>
        <input type="text" name="titulo" value="<?php echo $evento['titulo']; ?>">
        
        <label>Descripcion</label>
        <textarea name="descripcion"><?php echo $evento['descripcion']; ?></textarea>
        
        <label>Fecha</label>
        <input type="date" name="fecha" value="<?php echo $evento['fecha']; ?>">
        
        <label>Hora inicio</label>
        <input type="time" name="hora_inicio" value="<?php echo $evento['hora_inicio']; ?>">
        
        <label>Hora fin</label>
        <input type="time" name="hora_fin" value="<?php echo $evento['hora_fin']; ?>">
        
        <button type="submit">Actualizar</button>
    </form>
</body>
</html>