<?php
session_start();
require "../includes/conexion.php";
if(!isset($_POST['usuario'], $_POST['password'])){
    header("Location: login_admin.html");
    exit();
}

$usuario = trim($_POST['usuario']);
$password = trim($_POST['password']);

$sql = "SELECT * FROM administrador WHERE usuario = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if($resultado->num_rows == 1){

    $admin = $resultado->fetch_assoc();

    if(password_verify($password, $admin['password'])){

        $_SESSION['admin_login'] = true;
        $_SESSION['id_admin'] = $admin['id_admin'];
        $_SESSION['usuario'] = $admin['usuario'];

        header("Location: index.php");
        exit();

    } else {

        header("Location: login_admin.html?error=1");
        exit();

    }

}else{

    header("Location: login_admin.html?error=2");
    exit();
}