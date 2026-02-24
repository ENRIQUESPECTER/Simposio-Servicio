<?php

$host = "localhost";
$user = "root";
$bd = "simposio";
$password = "";

$conexion = mysqli_connect($host,$user,$bd,$password);

if (conexion->connect_error) {
    die ("No se pudo establecer conexión con la base de datos", $conexion->connect_error);
}

// Establecer conjunto de caracteres
if(!$conexion->set_charset("utf8mb4")){
    die("Error al configurar el conjunto de caracteres".$conexion->error);
}

?>