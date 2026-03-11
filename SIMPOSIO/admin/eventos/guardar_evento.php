<?php
session_start();
include("../../conexion.php");
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}


$creado_por = $_SESSION['id_admin'];

$titulo = $_POST['titulo'];
$descripcion = $_POST['descripcion'];
$fecha = $_POST['fecha'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fin = $_POST['hora_fin'];

/* calcular año automáticamente */

$anio = date("Y", strtotime($fecha));

$sql = "INSERT INTO evento (titulo, descripcion, fecha, hora_inicio, hora_fin, anio, creado_por) 
VALUES ('$titulo','$descripcion','$fecha','$hora_inicio','$hora_fin','$anio','$creado_por')";

mysqli_query($conexion,$sql);

header("Location: lista_eventos.php");

?>