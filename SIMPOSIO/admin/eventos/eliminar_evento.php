<?php
session_start();
require "../../conexion.php";
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}

$id_evento = $_GET['id_evento'];

$sql="DELETE FROM evento WHERE id_evento=?";
$stmt=$conexion->prepare($sql);
$stmt->bind_param("i",$id_evento);
$stmt->execute();

header("Location: lista_eventos.php");
?>