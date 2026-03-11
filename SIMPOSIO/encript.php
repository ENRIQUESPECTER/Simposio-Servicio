<?php

#$hash = '$2y$10$7bJbzqKgtG5in1IhzGK1r.iGnDwMlrGWvzGp02XnnRXFYJPltfRWe';

#var_dump(password_verify("12345", $hash));
?>

<?php
require "conexion.php";

// Selecciona todos los usuarios actuales
$resultado = $conexion->query("SELECT id_admin, password FROM administrador");

while($fila = $resultado->fetch_assoc()){

    $id = $fila['id_admin'];
    $password_actual = $fila['password'];

    // Si ya parece hash, lo saltamos
    if(strpos($password_actual, '$2y$') === 0){
        continue;
    }

    $nuevo_hash = password_hash($password_actual, PASSWORD_DEFAULT);

    $stmt = $conexion->prepare("UPDATE administrador SET password = ? WHERE id_admin = ?");
    $stmt->bind_param("si", $nuevo_hash, $id);
    $stmt->execute();
}

echo "Passwords actualizadas correctamente";
?>
