<?php
include "config.php";

$host = "localhost";
$user = "root";
$bd = "simposio";
$password = "";

$conexion = mysqli_connect($host,$user,$password,$bd);

if ($conexion->connect_error) {
    die ("No se pudo establecer conexión con la base de datos". $conexion->connect_error);
}

// Establecer conjunto de caracteres
if(!$conexion->set_charset("utf8mb4")){
    die("Error al configurar el conjunto de caracteres".$conexion->error);
}

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Configurar zona horaria en MySQL
$conexion->query("SET time_zone = '-06:00'");

?>