<?php
require "conexion.php";

if(!isset($_POST['nombre'], $_POST['correo'], $_POST['password'], $_POST['tipo_usuario'])){
    header("Location: registro.html");
    exit();
}

$nombre = trim($_POST['nombre']);
$apellidos = trim($_POST['apellidos']);
$correo = trim($_POST['correo']);
$direccion = trim($_POST['direccion']);
$tipo = $_POST['tipo_usuario'];
$password = $_POST['password'];

// Verificar si el correo ya existe
$stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$resultado = $stmt->get_result();

if($resultado->num_rows > 0){
    echo "El correo ya está registrado.";
    exit();
}

// Encriptar contraseña
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insertar usuario
$stmt = $conexion->prepare("
INSERT INTO usuario (nombre, apellidos, correo, direccion, tipo_usuario, password)
VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssss", $nombre, $apellidos, $correo, $direccion, $tipo, $password_hash);
$stmt->execute();

echo "Registro exitoso. <a href='login.html'>Iniciar sesión</a>";
?>