<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../conexion.php";

$id = $_GET['id'];

$sql = "SELECT * FROM actividad_evento WHERE id_actividad = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$actividad = $result->fetch_assoc();

/* obtener eventos */
$eventos = $conexion->query("SELECT id_evento, titulo FROM evento");

/* obtener tipos */
$tipos = $conexion->query("SELECT id_tipo, nombre FROM tipo_actividad");

$sql_horarios = "SELECT hora_inicio, hora_fin 
FROM actividad_evento
WHERE id_evento = ?";

$stmt = $conexion->prepare($sql_horarios);
$stmt->bind_param("i", $actividad['id_evento']);
$stmt->execute();

$horarios = $stmt->get_result();

?>

<h2>Editar Actividad</h2>

<form action="actualizar_actividad.php" method="POST">

<input type="hidden" name="id_actividad"
value="<?php echo $actividad['id_actividad']; ?>">

<label>Titulo</label>
<input type="text" name="titulo"
value="<?php echo $actividad['titulo']; ?>" required>

<label>Evento</label>

<select name="id_evento">

<?php while($evento = $eventos->fetch_assoc()): ?>

<option value="<?php echo $evento['id_evento']; ?>"

<?php if($evento['id_evento'] == $actividad['id_evento']) echo "selected"; ?>>

<?php echo $evento['titulo']; ?>

</option>

<?php endwhile; ?>

</select>

<label>Tipo de actividad</label>

<select name="id_tipo">

<?php while($tipo = $tipos->fetch_assoc()): ?>

<option value="<?php echo $tipo['id_tipo']; ?>"

<?php if($tipo['id_tipo'] == $actividad['id_tipo']) echo "selected"; ?>>

<?php echo $tipo['nombre']; ?>

</option>

<?php endwhile; ?>

</select>

<label>Hora inicio</label>
<input type="time" name="hora_inicio"
value="<?php echo $actividad['hora_inicio']; ?>">


<button type="submit">Actualizar actividad</button>

</form>

<h3>Horarios ocupados en este evento</h3>

<div class="agenda-preview">

<?php while($h = $horarios->fetch_assoc()): ?>

<div class="bloque-ocupado">
<?php echo $h['hora_inicio']; ?> - <?php echo $h['hora_fin']; ?>
</div>

<?php endwhile; ?>

</div>