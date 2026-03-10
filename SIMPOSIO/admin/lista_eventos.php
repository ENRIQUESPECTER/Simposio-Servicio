<?php

require("../conexion.php");

$sql = "SELECT * FROM evento ORDER BY fecha DESC";
$resultado = mysqli_query($conexion,$sql);

?>

<!DOCTYPE html>
<html>
    <head>        
        <title>Eventos</title>
        <link rel="stylesheet" href="../Css/admin.css">        
    </head>    
    <body>
        <div class="container">     
        <h2>Eventos registrados</h2>        
        <table>            
            <tr>                
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Horario</th>                
            </tr>            
            <?php while($row = mysqli_fetch_assoc($resultado)): ?>
                
                <tr>
                    <td><?php echo $row['titulo']; ?></td>
                    <td><?php echo $row['fecha']; ?></td>
                    <td>
                        <?php echo $row['hora_inicio']." - ".$row['hora_fin']; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
        </div>
    </body>
</html>