<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../includes/conexion.php";

$nombre=$_POST['nombre'];
$duracion=$_POST['duracion'];

$sql="INSERT INTO tipo_actividad(nombre,duracion_minutos)
VALUES(?,?)";

$stmt=$conexion->prepare($sql);
$stmt->bind_param("si",$nombre,$duracion);
$stmt->execute();

header("Location: lista_tipos.php");

?>