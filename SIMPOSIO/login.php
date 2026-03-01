<?php
session_start();
require "conexion.php";

// Validar que venga del formulario
if(!isset($_POST['correo'], $_POST['password'], $_POST['tipo_usuario'])){
    header("Location: login.html");
    exit();
}

$tipo = $_POST['tipo_usuario'];
$correo = $_POST['correo'];
$password = $_POST['password'];

$sql = "SELECT * FROM usuario 
        WHERE correo = ? 
        AND tipo_usuario = ? 
        LIMIT 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $correo, $tipo);
$stmt->execute();

$resultado = $stmt->get_result();

if($resultado->num_rows === 1){

    $usuario = $resultado->fetch_assoc();

    if(password_verify($password, $usuario['password'])){

        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

        // Redirección inteligente
        header("Location: programa/index_programa.php");
        exit();

    } else {
        header("Location: login.html");
        exit();
        echo "Contraseña incorrecta";
    }

}else{
    echo "Usuario no encontrado";
}
?>