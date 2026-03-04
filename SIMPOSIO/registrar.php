<?php
require "conexion.php";
require "../auth.php";

if(!esta_logeado() || !(es_docente() || es_empresa())){
    header("Location: index_programa.php");
    exit();
}

if(!isset($_POST['nombre'], $_POST['correo'], $_POST['password'], $_POST['tipo_usuario'])){
    header("Location: registro.html");
    exit();
}

$conexion->begin_transaction();

try {

    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $correo = trim($_POST['correo']);
    $direccion = trim($_POST['direccion']);
    $tipo = $_POST['tipo_usuario'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Verificar correo único
    $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if($resultado->num_rows > 0){
        throw new Exception("El correo ya está registrado.");
    }

    // Insertar en usuario
    $stmt = $conexion->prepare("
        INSERT INTO usuario (nombre, apellidos, correo, direccion, tipo_usuario, password)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssss", $nombre, $apellidos, $correo, $direccion, $tipo, $password);
    $stmt->execute();

    $id_usuario = $conexion->insert_id;
    $matricula = $_POST['matricula'];
    $carrera = $_POST['carrera'];
    $semestre = $_POST['semestre'];
    $especialidad = $_POST['especialidad'];
    $grado = $_POST['grado_academico'];
    $nombre_empresa = $_POST['nombre_empresa'];
    $sector = $_POST['sector'];

    // Insertar en tabla específica según tipo
    if($tipo == "alumno"){

        $stmt = $conexion->prepare("INSERT INTO alumno (id_usuario,matricula,carrera,semestre) VALUES (?,?,?,?)");
        $stmt->bind_param("issi", $id_usuario,$matricula,$carrera,$semestre);
        $stmt->execute();

    } elseif($tipo == "docente"){

        $stmt = $conexion->prepare("INSERT INTO docente (id_usuario,especialidad,grado_academico) VALUES (?,?,?)");
        $stmt->bind_param("iss", $id_usuario,$especialidad,$grado);
        $stmt->execute();

    } elseif($tipo == "empresa"){

        $stmt = $conexion->prepare("INSERT INTO empresa (id_usuario,nombre_empresa,sector) VALUES (?,?,?)");
        $stmt->bind_param("iss", $id_usuario,$nombre_empresa,$sector);
        $stmt->execute();
    }

    $conexion->commit();

    echo "Registro exitoso. <a href='login.html'>Iniciar sesión</a>";

} catch (Exception $e){

    $conexion->rollback();
    echo "Error: " . $e->getMessage();
}
?>