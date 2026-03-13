<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../conexion.php";

$id = $_GET['id'];

$sql = "DELETE FROM actividad_evento
WHERE id_actividad = ?";

$stmt = $conexion->prepare($sql);

$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: lista_actividades.php");

?>