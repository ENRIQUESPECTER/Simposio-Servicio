<?php
session_start();

if(!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] != "admin"){
    header("Location: login_admin.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
    <head>        
        <title>Panel Administrador</title>        
        <link rel="stylesheet" href="../Css/admin.css">        
    </head>    
    <body>        
        <h1>Panel Administrador</h1>        
        <div class="panel-opciones">            
            <a href="evento.php">Crear Evento</a>            
            <a href="lista_eventos.php">Gestionar Eventos</a>            
            <a href="gestionar_actividades.php">Gestionar Actividades</a>     
            <a href="../logout.php">Cerrar sesión</a>            
        </div>        
    </body>
</html>
    