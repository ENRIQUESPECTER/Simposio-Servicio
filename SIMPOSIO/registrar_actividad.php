<?php
require("conexion.php");
require("auth.php");
include("includes/header_programa.php");

if(!isset($_SESSION['id_usuario'])){
    die("Debes iniciar sesión");
}

if($_SESSION['tipo_usuario'] != "docente" && $_SESSION['tipo_usuario'] != "empresa"){
    die("No tienes permisos para registrar actividades");
}

if(!isset($_GET['id_evento'])){
    die("Evento no especificado");
}

$id_evento = intval($_GET['id_evento']);
$fecha = $_GET['fecha'];
$hora_inicio = $_GET['hora_inicio'];
$hora_fin = $_GET['hora_fin'];

$id_usuario = $_SESSION['id_usuario'];


/*OBTENER TIPOS DE ACTIVIDAD*/
$sql_tipo = "SELECT * FROM tipo_actividad ORDER BY nombre ASC";
$result_tipo = $conexion->query($sql_tipo);


/*REGISTRO XD*/
if($_SERVER['REQUEST_METHOD'] == "POST"){

    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $resumen = $_POST['resumen'];
    $referencias = $_POST['referencias'];
    $id_tipo = $_POST['id_tipo'];

    $archivo_pdf = NULL;

    /* SUBIR PDF */

    if(isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] == 0){

        $nombreArchivo = time() . "_" . $_FILES['archivo_pdf']['name'];
        $ruta = "pdfs/" . $nombreArchivo;

        move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $ruta);

        $archivo_pdf = $nombreArchivo;
    }

    $stmt = $conexion->prepare("
        INSERT INTO actividad_evento
        (id_evento,id_usuario,id_tipo,titulo,descripcion,resumen,referencias,archivo_pdf,fecha,hora_inicio,hora_fin)
        VALUES(?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "iiissssssss",$id_evento,$id_usuario,$id_tipo,$titulo,$descripcion,$resumen,$referencias,$archivo_pdf,$fecha,$hora_inicio,$hora_fin);

    if($stmt->execute()){

        header("Location: programa/detalle_programa.php?id=".$id_evento);
        exit();

    }else{
        echo "Error al registrar actividad";
    }

}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Registrar Actividad</title>
        <link rel="stylesheet" href="Css/actividades.css">
        <style>
        body {
            background: linear-gradient(135deg, #0a7eeb, #c0902a);
        }
    </style>
    </head>
    <body>
        <div class="container-actividad info-bloque">
            <h2>Registrar actividad</h2>
            <p>
                Horario: <?php echo $hora_inicio ?> - <?php echo $hora_fin ?>
            </p>
            <form class="form-group" method="POST" enctype="multipart/form-data">

                <label>Tipo de actividad</label>
                <select name="id_tipo" required>
                    <?php while($tipo = $result_tipo->fetch_assoc()): ?>
                        <option value="<?php echo $tipo['id_tipo']; ?>">
                            <?php echo $tipo['nombre']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Título</label>
                <input type="text" name="titulo" required>

                <label>Descripción</label>
                <textarea name="descripcion"></textarea>

                <label>Resumen</label>
                <textarea name="resumen"></textarea>

                <label>Referencias</label>
                <textarea name="referencias"></textarea>
                <label>Subir PDF</label>
                <input type="file" name="archivo_pdf" accept="application/pdf">
                <button type="submit" class="btn-submit">
                    Registrar actividad
                </button>
            </form>
        </div>
    </body>
</html>