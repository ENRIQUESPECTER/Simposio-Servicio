<?php
require_once "auth.php";
require_once "conexion.php";

if(!esta_logeado() || !(es_docente() || es_empresa())){
    header("Location: index_programa.php");
    exit();
}

$id_evento = $_GET['id_evento'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proyecto Seguridad</title>
    <link rel="stylesheet" href="Css/redunistyle.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="Js/funciones.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            height: 100vh;
            background: linear-gradient(135deg, #0a7eeb, #c0902a);
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<form action="guardar_actividad.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="id_evento" value="<?= $id_evento ?>">
    
    <div class="mb-3">
        <label>Título</label>
        <input type="text" name="titulo" class="form-control" required>
    </div>
        
    <div class="mb-3">
        <label>Descripción</label>
        <textarea name="descripcion" class="form-control" required></textarea>
    </div>

    <div class="mb-3">
        <label>Resumen</label>
        <textarea name="resumen" class="form-control" required></textarea>
    </div>

    <div class="mb-3">
        <label>Referencias</label>
        <textarea name="referencias" class="form-control"></textarea>
    </div>

    <div class="mb-3">
        <label>Hora Inicio</label>
        <input type="time" name="hora_inicio" required>
    </div>

    <div class="mb-3">
        <label>Hora Fin</label>
        <input type="time" name="hora_fin" required>
    </div>

    <div class="mb-3">
        <label>Archivo PDF</label>
        <input type="file" name="archivo_pdf" accept="application/pdf" required>
    </div>
        
    <button type="submit" class="btn btn-success">
        Registrar Actividad
    </button>
        
</form>
</body>
</html>