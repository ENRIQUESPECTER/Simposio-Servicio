<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
} 
?>
<style>

body{
font-family: Arial;
background:#f4f6f9;
margin:0;
}

.header{
background:#003366;
color:white;
padding:20px;
}

.panel{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:20px;
padding:40px;
}

.card{
background:white;
padding:30px;
border-radius:8px;
box-shadow:0 2px 6px rgba(0,0,0,.2);
text-align:center;
font-size:18px;
}

.card a{
text-decoration:none;
color:#003366;
font-weight:bold;
}

</style>
<!DOCTYPE html>
<html>
    <head>        
        <title>Panel Administrador</title>        
        <link rel="stylesheet" href="../Css/admin.css">
    </head>    
    <body>
        <div class="header"> 
            <h1>Panel Administrador</h1>
            <h2>Bienvenido: <?php echo $_SESSION['usuario']; ?></h2>
            <div class="panel">
                <div class="card">
                    <a href="eventos/evento.php">Crear Evento</a>
                </div>
                <div class="card">
                    <a href="eventos/lista_eventos.php">Gestionar Eventos</a>
                </div>       
                <div class="card">
                    <a href="eventos/gestionar_actividades.php">Gestionar Actividades</a>
                </div>     
                <div class="card">
                    <a href="logout.php">Cerrar Sesion</a>
                </div>
                <div class="card">
                    <a href="../programa/index_programa.php">Ver Agenda</a>
                </div>
            </div>
        </div>        
    </body>
</html>

