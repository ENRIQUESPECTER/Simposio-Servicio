<?php
session_start();
require "../../conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}

$id_evento = $_POST['id_evento'];
$titulo = $_POST['titulo'];
$descripcion=$_POST['descripcion'];
$fecha=$_POST['fecha'];
$hora_inicio=$_POST['hora_inicio'];
$hora_fin=$_POST['hora_fin'];

$sql = "UPDATE evento SET titulo=?, descripcion=?, fecha=?, hora_inicio=?, hora_fin=?, anio=? WHERE id_evento=?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("sssssii", $titulo, $descripcion, $fecha, $hora_inicio, $hora_fin, $anio, $id_evento);
$stmt->execute();

header("Location: lista_eventos.php");

?>