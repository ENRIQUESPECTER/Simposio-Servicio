<?php 
session_start();
require "../conexion.php";

// Validar que venga del formulario
if(!isset($_POST['usuario'], $_POST['password'])){
    header("Location: login_admin.html");
    exit();
}
$usuario = $_POST['usuario'];

$sql = "SELECT * FROM administrador WHERE usuario = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if($resultado->num_rows === 1){

    $user = $resultado->fetch_assoc();

    if(password_verify($password, $user['password'])){

        $_SESSION['id_admin'] = $user['id_admin'];
        $_SESSION['usuario'] = $user['usuario'];

        // Redirección inteligente
        header("Location: panel.php");
        exit();

    } else {
        header("Location: login_admin.html");
        exit();
        echo "Contraseña incorrecta";
    }

}else{
    echo "Usuario no encontrado";
}

?>
