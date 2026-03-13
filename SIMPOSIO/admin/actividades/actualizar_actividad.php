<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../conexion.php";

$id = $_POST['id_actividad'];
$titulo = $_POST['titulo'];
$id_evento = $_POST['id_evento'];
$id_tipo = $_POST['id_tipo'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];

/* verificar conflicto */

$sql_conflicto = "SELECT * FROM actividad_evento
WHERE id_evento = ?
AND id_actividad != ?
AND (
    (hora_inicio < ? AND hora_fin > ?)
)";

$stmt = $conexion->prepare($sql_conflicto);

$stmt->bind_param(
"iiss",
$id_evento,
$id,
$hora_fin,
$hora_inicio
);

$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){

echo "Conflicto de horario con otra actividad.";
exit();

}

$sql_tipo = "SELECT duracion_minutos
FROM tipo_actividad
WHERE id_tipo=?";

$stmt = $conexion->prepare($sql_tipo);
$stmt->bind_param("i",$id_tipo);
$stmt->execute();

$result = $stmt->get_result();
$tipo = $result->fetch_assoc();

$duracion = $tipo['duracion_minutos'];

/* calcular hora_fin */

$hora_fin = date(
"H:i:s",
strtotime($hora_inicio) + ($duracion * 60)
);

/* si no hay conflicto se actualiza */

$sql = "UPDATE actividad_evento
SET titulo=?, id_evento=?, id_tipo=?, hora_inicio=?, hora_fin=?
WHERE id_actividad=?";

$stmt = $conexion->prepare($sql);

$stmt->bind_param(
"sisssi",
$titulo,
$id_evento,
$id_tipo,
$hora_inicio,
$hora_fin,
$id
);

$stmt->execute();

header("Location: lista_actividades.php");