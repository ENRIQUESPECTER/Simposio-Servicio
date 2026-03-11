<?php
session_start();

if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
    <head>        
        <title>Crear evento</title>
        <link rel="stylesheet" href="../../Css/admin.css">        
    </head>    
    <body>        
        <h2>Crear Evento</h2> 
        <div class="container">       
        <form action="guardar_evento.php" method="POST">
            
            <label>Título del evento</label>
            <input type="text" name="titulo" required>
            
            <label>Descripción</label>
            <textarea name="descripcion"></textarea>
            
            <label>Fecha</label>
            <input type="date" name="fecha" required>
            
            <label>Hora inicio</label>
            <input type="time" name="hora_inicio" required>
            
            <label>Hora fin</label>
            <input type="time" name="hora_fin" required>
            
            <button type="submit">Registrar Evento</button>    
        </form>
        </div>
    </body>
</html>