<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../includes/conexion.php";

$id = $_POST['id_actividad'];
$titulo = $_POST['titulo'];
$id_evento = $_POST['id_evento'];
$id_tipo = $_POST['id_tipo'];
$descripcion = $_POST['descripcion'];
$resumen = $_POST['resumen'];
$referencias = $_POST['referencias'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];
// Validaciones básicas
if (empty($titulo) || empty($id_evento) || empty($id_tipo) || empty($hora_inicio) || empty($hora_fin)) {
    die("Faltan datos obligatorios.");
}

/* verificar conflicto */
$sql_conflicto = "SELECT * FROM actividad_evento
WHERE id_evento = ?
AND id_actividad != ?
AND (
    (hora_inicio < ? AND hora_fin > ?)
)";
$stmt = $conexion->prepare($sql_conflicto);
$stmt->bind_param( "iiss", $id_evento, $id, $hora_fin, $hora_inicio );
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0){
    echo "Conflicto de horario con otra actividad.";
    exit();
}

// Manejo del archivo PDF
$archivo_pdf = null;
if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] == 0) {
    $carpeta_pdf = '../../uploads/actividades/';
    if (!file_exists($carpeta_pdf)) {
        mkdir($carpeta_pdf, 0777, true);
    }
    $nombre_pdf = uniqid() . '_' . time() . '_' . basename($_FILES['archivo_pdf']['name']);
    $ruta_pdf = $carpeta_pdf . $nombre_pdf;
    move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $ruta_pdf);
    $archivo_pdf = 'uploads/actividades/' . $nombre_pdf;
}

$sql_tipo = "SELECT duracion_minutos FROM tipo_actividad WHERE id_tipo=?";
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

$sql = "UPDATE actividad_evento SET titulo=?, id_evento=?, id_tipo=?, descripcion=?, resumen=?, referencias=?, hora_inicio=?, hora_fin=? WHERE id_actividad=?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param( "sissssssi", $titulo, $id_evento, $id_tipo, $descripcion, $resumen, $referencias, $hora_inicio, $hora_fin, $id);
$stmt->execute();

if ($archivo_pdf) {
    $sql .= ", archivo_pdf = ?";
    $params[] = $archivo_pdf;
    $types .= "s";
}
$sql .= " WHERE id_actividad = ?";
$params[] = $id_actividad;
$types .= "i";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

header("Location: lista_actividades.php");