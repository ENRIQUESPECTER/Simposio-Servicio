<?php

require("../conexion.php");
require("../auth.php");

$id_evento = $_POST['id_evento'];
$id_tipo = $_POST['id_tipo'];
$titulo = $_POST['titulo'];
$descripcion = $_POST['descripcion'];
$resumen = $_POST['resumen'];
$referencias = $_POST['referencias'];
$hora_inicio = $_POST['hora_inicio'];

$id_usuario = $_SESSION['id_usuario'];
/* obtener duración del tipo */
$stmt = $conexion->prepare("SELECT duracion_minutos FROM tipo_actividad WHERE id_tipo = ?");
$stmt->bind_param("i",$id_tipo);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$duracion = $res['duracion_minutos'];

/* calcular hora fin */
$hora_fin = date("H:i",strtotime("+$duracion minutes", strtotime($hora_inicio)));

/* obtener fecha del evento */
$stmt = $conexion->prepare("SELECT fecha FROM evento WHERE id_evento = ?");
$stmt->bind_param("i",$id_evento);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$fecha = $evento['fecha'];

/* VALIDAR TRASLAPE */
$stmt = $conexion->prepare("SELECT * FROM actividad_evento WHERE id_evento = ? AND ( (? < hora_fin AND ? > hora_inicio) )");
$stmt->bind_param("iss",$id_evento,$hora_inicio,$hora_fin);
$stmt->execute();
$conflicto = $stmt->get_result();

if($conflicto->num_rows > 0){
    die("Error: el horario se traslapa con otra actividad.");
}

/* SUBIR PDF */ 
$ruta_pdf = NULL;
if(!empty($_FILES['archivo_pdf']['name'])){
    $nombre = time()."_".$_FILES['archivo_pdf']['name'];
    $destino = "uploads/actividades/".$nombre;
    move_uploaded_file( $_FILES['archivo_pdf']['tmp_name'], $destino );
    $ruta_pdf = $destino;
}
    
/* INSERTAR ACTIVIDAD */

$stmt = $conexion->prepare(" 
    INSERT INTO actividad_evento (id_evento, id_usuario, id_tipo, titulo, descripcion, resumen, referencias, archivo_pdf, fecha, 
    hora_inicio, hora_fin )
    VALUES(?,?,?,?,?,?,?,?,?,?,?) ");
$stmt->bind_param("iiissssssss", $id_evento, $id_usuario, $id_tipo, $titulo, $descripcion, $resumen, $referencias, $ruta_pdf, $fecha, $hora_inicio, $hora_fin );
$stmt->execute();

header("Location: ../programa/detalle_programa.php?id=".$id_evento);
?>