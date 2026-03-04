<?php
require_once "auth.php";
require_once "conexion.php";

if(!esta_logeado() || !(es_docente() || es_empresa())){
    header("Location: index_programa.php");
    exit();
}

$conexion->begin_transaction();

try {

$id_usuario = $_SESSION['id_usuario'];
$id_evento = $_POST['id_evento'];
$titulo = $_POST['titulo'];
$descripcion = $_POST['descripcion'];
$resumen = $_POST['resumen'];
$referencias = $_POST['referencias'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];

if($hora_inicio >= $hora_fin){
    throw new Exception("La hora fin debe ser mayor a la hora inicio.");
}

# Validar traslapes
$stmt = $conexion->prepare("
    SELECT id_actividad FROM actividad
    WHERE id_evento = ?
    AND (
        (hora_inicio < ? AND hora_fin > ?)
    )
");

$stmt->bind_param("iss", $id_evento, $hora_fin, $hora_inicio);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    throw new Exception("El horario ya está ocupado.");
}

# Validar PDF
if($_FILES['archivo_pdf']['type'] != "application/pdf"){
    throw new Exception("Solo se permiten archivos PDF.");
}

$nombre_archivo = uniqid() . ".pdf";
$ruta = "../uploads/" . $nombre_archivo;

move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $ruta);

# Insertar actividad
$stmt = $conexion->prepare("
    INSERT INTO actividad
    (id_evento, id_usuario, titulo, descripcion, resumen, referencias, archivo_pdf, hora_inicio, hora_fin, fecha_registro)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param("iisssssss",
    $id_evento,
    $id_usuario,
    $titulo,
    $descripcion,
    $resumen,
    $referencias,
    $nombre_archivo,
    $hora_inicio,
    $hora_fin
);

$stmt->execute();

$conexion->commit();

    echo "Actividad registrada correctamente";

} catch(Exception $e){

$conexion->rollback();
    echo "Error: " . $e->getMessage();
}
?>