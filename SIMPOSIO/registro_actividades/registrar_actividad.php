<?php
require("../conexion.php");
require("../auth.php");

if(!isset($_GET['id_evento'])){
    die("Evento no especificado");
}

$id_evento = intval($_GET['id_evento']);


/*OBTENER TABLA EVENTO*/
$stmt = $conexion->prepare("SELECT fecha, hora_inicio, hora_fin FROM evento WHERE id_evento = ?");
$stmt->bind_param("i",$id_evento);
$stmt->execute();

$evento = $stmt->get_result()->fetch_assoc();

/*OBTENER LAS ACTIVIDADES YA REGISTRADAS DE LA TABLA*/
$stmt = $conexion->prepare("SELECT hora_inicio, hora_fin FROM actividad_evento WHERE id_evento = ?");
$stmt->bind_param("i",$id_evento);
$stmt->execute();
$result = $stmt->get_result();

$ocupados = [];

while($fila = $result->fetch_assoc()){
    $ocupados[] = $fila;
}



/*GENERACION DE LOS BLOQUES DE 30 MIN AHORA A COMO CORRESPONDE EL REGISTRO EN ACTIVIDAD EVENTO*/

$hora_actual = strtotime($evento['hora_inicio']);
$hora_fin_evento = strtotime($evento['hora_fin']);

$horarios_disponibles = [];

while($hora_actual < $hora_fin_evento){

    $bloque = date("H:i",$hora_actual);

    $ocupado = false;

    foreach($ocupados as $act){

        if(
            $bloque >= substr($act['hora_inicio'],0,5)
            &&
            $bloque < substr($act['hora_fin'],0,5)
        ){
            $ocupado = true;
            break;
        }

    }

    if(!$ocupado){
        $horarios_disponibles[] = $bloque;
    }

    $hora_actual = strtotime("+30 minutes",$hora_actual);

}


/*TABLA TIPOS DE ACTIVIDAD*/

$tipos = $conexion->query("
SELECT * FROM tipo_actividad
");
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Registrar Actividad</title>
        
        <link rel="stylesheet" href="../Css/actividades.css">
        <style>
            body {
                background: linear-gradient(135deg, #0a7eeb, #c0902a);
            }
            </style>
    </head>
    <body>
        
        <div class="container-actividad info-bloque">
            
            <h2>Registrar actividad</h2>
            
            <form class="form-group" action="guardar_actividad.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_evento" value="<?php echo $id_evento; ?>">
                <label>Tipo de actividad</label>
                <select name="id_tipo" required>    
                    <?php while($tipo = $tipos->fetch_assoc()): ?>
                        <option value="<?php echo $tipo['id_tipo']; ?>">    
                            <?php echo $tipo['nombre']; ?>
                            (<?php echo $tipo['duracion_minutos']; ?> min)    
                        </option>
                    <?php endwhile; ?>    
                </select>
                <label>Hora disponible</label>
                <select name="hora_inicio" required>            
                    <?php foreach($horarios_disponibles as $hora): ?>
                        <option value="<?php echo $hora; ?>">
                            <?php echo $hora; ?>
                        </option>
                            <?php if($tipo['duracion_minutos'] > $hora_fin_evento): ?>
                        <option value="<?php echo $tipo['id_tipo']; ?>">
                            <?php echo $tipo['duracion_minutos']; ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>             
                </select>
                
                <label>Título</label>
                <input type="text" name="titulo" required>
                
                <label>Descripción</label>
                <textarea name="descripcion" required></textarea>
                
                <label>Resumen</label>
                <textarea name="resumen" required></textarea>
                
                <label>Referencias</label>
                <textarea name="referencias" required></textarea>
                
                <label>Archivo PDF</label>
                <input type="file" name="archivo_pdf" accept="application/pdf" required>
                
                <button type="submit" class="btn-submit">                
                    Registrar actividad
                </button>
            </form>
        </div>
    </body>
</html>