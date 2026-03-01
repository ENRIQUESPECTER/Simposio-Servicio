<?php
require("../conexion.php");
include("../includes/header_programa.php");

$query = "
SELECT id_evento, titulo, descripcion, fecha, hora_inicio, hora_fin
FROM evento
WHERE fecha >= CURDATE()
ORDER BY fecha ASC
";

$result = $conexion->query($query);
?>

<div class="contenedor" style="margin: 169px auto;">

<h1>Programa de Eventos</h1>
<p>Aqui podrás ver toda la información sobre los eventos que vayan asignandose, así como los horarios de las actividades que habrán en ellos</p>

<?php if($result->num_rows > 0): ?>

    <?php while($evento = $result->fetch_assoc()): ?>

        <div class="evento-card">
            <h2><?php echo $evento['titulo']; ?></h2>

            <p><?php echo $evento['descripcion']; ?></p>

            <p><strong>Fecha:</strong>
                <?php echo date("d/m/Y", strtotime($evento['fecha'])); ?>
            </p>

            <p><strong>Horario:</strong>
                <?php echo substr($evento['hora_inicio'],0,5); ?>
                -
                <?php echo substr($evento['hora_fin'],0,5); ?>
            </p>

            <a href="detalle_programa.php?id=<?php echo $evento['id_evento']; ?>">
                Ver agenda
            </a>
        </div>

    <?php endwhile; ?>

<?php else: ?>
    <p>No hay eventos actuales o próximos.</p>
<?php endif; ?>

</div>

</body>
</html>