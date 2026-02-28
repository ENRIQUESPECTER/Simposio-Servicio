<?php
require("../conexion.php");
include("../includes/header_programa.php");

if(!isset($_GET['id'])){
    die("Evento no especificado.");
}

$id_evento = intval($_GET['id']);

/* =========================
   OBTENER EVENTO
========================= */
$stmt_evento = $conexion->prepare("
    SELECT * FROM evento
    WHERE id_evento = ?
");
$stmt_evento->bind_param("i", $id_evento);
$stmt_evento->execute();
$result_evento = $stmt_evento->get_result();

if($result_evento->num_rows == 0){
    die("Evento no encontrado.");
}

$evento = $result_evento->fetch_assoc();

/* =========================
   OBTENER ACTIVIDADES
========================= */
$stmt_act = $conexion->prepare("
    SELECT a.*, t.nombre as tipo
    FROM actividad_evento a
    JOIN tipo_actividad t ON a.id_tipo = t.id_tipo
    WHERE a.id_evento = ?
    ORDER BY a.hora_inicio ASC
");

$stmt_act->bind_param("i", $id_evento);
$stmt_act->execute();
$result_act = $stmt_act->get_result();

$actividades = [];

while($fila = $result_act->fetch_assoc()){
    $actividades[] = $fila;
}

/* =========================
   CONFIGURAR HORARIOS
========================= */
$hora_actual = strtotime($evento['hora_inicio']);
$hora_fin_evento = strtotime($evento['hora_fin']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agenda del Evento</title>
    <link rel="stylesheet" href="programa.css">
</head>
<body>

<h1><?php echo $evento['titulo']; ?></h1>
<?php if(isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 'docente'): ?>
    <a href="registrar_actividad.php?id=<?php echo $id_evento; ?>" 
       class="btn-registrar">
       Registrar actividad
    </a>
<?php endif; ?>
<div class="contenedor">

    <p>
        <strong>Fecha:</strong>
        <?php echo date("d/m/Y", strtotime($evento['fecha'])); ?>
    </p>

    <div class="agenda-container">

    <?php while($hora_actual < $hora_fin_evento): ?>

        <?php
            $bloque_inicio = date("H:i", $hora_actual);
            $bloque_fin = date("H:i", strtotime("+30 minutes", $hora_actual));
            $ocupado = false;
            $actividad_actual = null;

            foreach($actividades as $act){
                if(
                    $bloque_inicio >= substr($act['hora_inicio'],0,5)
                    &&
                    $bloque_inicio < substr($act['hora_fin'],0,5)
                ){
                    $ocupado = true;
                    $actividad_actual = $act;
                    break;
                }
            }
        ?>

        <div class="bloque-horario <?php echo $ocupado ? 'bloque-ocupado' : 'bloque-disponible'; ?>">
            <div>
                <strong><?php echo $bloque_inicio . " - " . $bloque_fin; ?></strong>
            </div>

            <div>
                <?php if($ocupado): ?>
                    <?php echo $actividad_actual['titulo']; ?>
                    (<?php echo $actividad_actual['tipo']; ?>)
                <?php else: ?>
                    Disponible
                <?php endif; ?>
            </div>
        </div>

        <?php $hora_actual = strtotime("+30 minutes", $hora_actual); ?>

    <?php endwhile; ?>

    </div>

    <a href="index_programa.php" class="btn-volver">← Volver</a>

</div>

</body>
</html>