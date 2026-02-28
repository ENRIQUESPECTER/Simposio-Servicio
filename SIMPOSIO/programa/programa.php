<?php
session_start();
require "../conexion.php";

/* Obtener evento activo (fecha actual) */
$hoy = date("Y-m-d");

$sql_evento = "SELECT * FROM evento 
               WHERE fecha = ? 
               LIMIT 1";

$stmt = $conexion->prepare($sql_evento);
$stmt->bind_param("s", $hoy);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();

if(!$evento){
    $mensaje = "No hay eventos programados para hoy.";
} else {

    /* Obtener actividades */
    $sql_actividades = "SELECT a.*, t.nombre AS tipo_nombre
                        FROM actividad_evento a
                        JOIN tipo_actividad t ON a.id_tipo = t.id_tipo
                        WHERE a.id_evento = ?
                        ORDER BY a.hora_inicio ASC";

    $stmt2 = $conexion->prepare($sql_actividades);
    $stmt2->bind_param("i", $evento['id_evento']);
    $stmt2->execute();
    $actividades = $stmt2->get_result();
}
?>