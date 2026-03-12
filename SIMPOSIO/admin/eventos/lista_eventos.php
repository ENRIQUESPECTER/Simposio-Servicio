<?php
session_start();
require "../../conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
$sql="SELECT * FROM evento ORDER BY fecha DESC";
$result=$conexion->query($sql);

?>

<!DOCTYPE html>
<html>
    <head>        
        <title>Eventos</title>
        <link rel="stylesheet" href="../../Css/admin.css">        
    </head>    
    <body>
        <div class="container">     
        <h2>Eventos registrados</h2>        
        <table border="1">            
            <tr>                
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Acciones</th>                   
            </tr>            
            <?php while($evento=$result->fetch_assoc()): ?>
                
                <tr>
                    <td><?php echo $evento['titulo']; ?></td>
                    <td><?php echo $evento['fecha']; ?></td>
                    <td>
                        <?php echo $evento['hora_inicio']." - ".$evento['hora_fin']; ?>
                    </td>

                    <td>
                        <a href="editar_evento.php?id_evento=<?php echo $evento['id_evento']; ?>">
                            Editar
                        </a>
                        <a href="eliminar_evento.php?id_evento=<?php echo $evento['id_evento']; ?>">
                            Eliminar
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            <button class="btn"><a href="../dashboard.php" style="" >REGRESAR</a></button>
        </table>
        </div>
    </body>
</html>