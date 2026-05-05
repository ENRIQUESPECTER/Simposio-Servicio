<?php
session_start();
if(!isset($_SESSION['admin_login'])){
    header("Location: login_admin.html");
    exit();
}
require "../../includes/conexion.php";
require "../../includes/auth.php";

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: lista_actividades.php");
    exit;
}

// Obtener actividad
$sql_act = "SELECT * FROM actividad_evento WHERE id_actividad = ?";
$stmt = $conexion->prepare($sql_act);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$actividad = $result->fetch_assoc();
if (!$actividad) {
    die("Actividad no encontrada.");
}

// Obtener evento
$sql_evento = "SELECT id_evento, titulo, fecha, hora_inicio as ev_hora_inicio, hora_fin as ev_hora_fin FROM evento WHERE id_evento = ?";
$stmt = $conexion->prepare($sql_evento);
$stmt->bind_param("i", $actividad['id_evento']);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();

// Obtener tipos de actividad (para dropdown)
$tipos = $conexion->query("SELECT id_tipo, nombre, duracion_minutos FROM tipo_actividad");

// Horarios ocupados (excepto la actual)
$sql_ocupados = "SELECT hora_inicio, hora_fin FROM actividad_evento WHERE id_evento = ? AND id_actividad != ?";
$stmt = $conexion->prepare($sql_ocupados);
$stmt->bind_param("ii", $actividad['id_evento'], $id);
$stmt->execute();
$ocupados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generar opciones de hora_inicio (cada 30 min, desde inicio_evento hasta fin_evento, excluyendo ocupados y considerando duración)
function generar_opciones_horas_actividad($hora_inicio_evento, $hora_fin_evento, $duracion, $ocupados, $actual) {
    $opciones = '';
    $inicio_ts = strtotime($hora_inicio_evento);
    $fin_ts = strtotime($hora_fin_evento);
    $paso = 1800; // 30 min
    for ($h = $inicio_ts; $h + $duracion*60 <= $fin_ts; $h += $paso) {
        $hora = date('H:i:s', $h);
        $hora_mostrar = date('H:i', $h);
        $ocupado = false;
        foreach ($ocupados as $occ) {
            $occ_inicio = strtotime($occ['hora_inicio']);
            $occ_fin = strtotime($occ['hora_fin']);
            if ($h < $occ_fin && $h + $duracion*60 > $occ_inicio) {
                $ocupado = true;
                break;
            }
        }
        if (!$ocupado || $hora == $actual) {
            $selected = ($hora == $actual) ? 'selected' : '';
            $opciones .= "<option value='$hora' $selected>$hora_mostrar</option>";
        }
    }
    return $opciones;
}

// Obtener duración del tipo actual para previsualizar hora_fin
$tipo_actual = $conexion->query("SELECT duracion_minutos FROM tipo_actividad WHERE id_tipo = " . intval($actividad['id_tipo']))->fetch_assoc();
$duracion_actual = $tipo_actual['duracion_minutos'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Editar Actividad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../Css/admin.css">
    <style>
        .agenda-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .bloque-ocupado {
            background-color: #fb5f6c;
            border-left: 5px solid #dc3545;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 8px;
        }
        .bloque-ocupado span {
            font-weight: bold;
        }
        * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #f0f4fc 0%, #e9eef5 100%);
                min-height: 100vh;
                padding: 4.5rem 2rem 3rem;
                color: #1a2c3e;
            }
            .action-btn {
                background: #f8fafd;
                border: 1px solid #e2edf7;
                padding: 0.50rem 1.5rem;
                border-radius: 3rem;
                font-weight: 600;
                color: #2d8bc5;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                font-size: 0.9rem;
                transition: all 0.25s;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }
            .action-btn i {
                font-size: 1.1rem;
            }
            .action-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav" style="background-color: #293e6b;">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-calculator me-2"></i>SIMPOSIO FESC C4
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="../eventos/lista_eventos.php"><i class="fas fa-scroll me-1"></i>Lista Eventos</a></li>
                    <li class="nav-item"><a class="nav-link" href="../actividades/lista_actividades.php"><i class="fas fa-chalkboard me-1"></i>Agenda Actividades</a></li>
                    <li class="nav-item"><a class="nav-link" href="../trabajos/pendientes.php"><i class="fas fa-calendar me-1"></i>Evaluación de Trabajos</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['usuario']  ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
                            </ul>
                        </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">
        <h2>Editar Actividad</h2>
        <form action="actualizar_actividad.php" method="POST">
            <input type="hidden" name="id_actividad" value="<?php echo $actividad['id_actividad']; ?>">

            <div class="mb-3">
                <label class="form-label">Evento</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($evento['titulo'] . ' (' . $evento['fecha'] . ')'); ?>" readonly disabled>
                <input type="hidden" name="id_evento" value="<?php echo $evento['id_evento']; ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Título de la actividad</label>
                <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($actividad['titulo']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Tipo de actividad</label>
                <select name="id_tipo" id="id_tipo" class="form-select" required>
                    <?php while($tipo = $tipos->fetch_assoc()): ?>
                    <option value="<?php echo $tipo['id_tipo']; ?>" data-duracion="<?php echo $tipo['duracion_minutos']; ?>" <?php echo ($tipo['id_tipo'] == $actividad['id_tipo']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tipo['nombre']); ?> (<?php echo $tipo['duracion_minutos']; ?> min)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Hora de inicio</label>
                <select name="hora_inicio" id="hora_inicio" class="form-select" required>
                    <?php 
                    echo generar_opciones_horas_actividad(
                        $evento['ev_hora_inicio'],
                        $evento['ev_hora_fin'],
                        $duracion_actual,
                        $ocupados,
                        $actividad['hora_inicio']
                    );
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Hora de fin (calculada automáticamente)</label>
                <input type="text" id="hora_fin" class="form-control" readonly value="<?php echo substr($actividad['hora_fin'], 0, 5); ?>">
                <input type="hidden" name="hora_fin" id="hora_fin_hidden" value="<?php echo $actividad['hora_fin']; ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($actividad['descripcion']); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Resumen</label>
                <textarea name="resumen" class="form-control" rows="3"><?php echo htmlspecialchars($actividad['resumen']); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Referencias</label>
                <textarea name="referencias" class="form-control" rows="3"><?php echo htmlspecialchars($actividad['referencias']); ?></textarea>
            </div>

            <?php if (!empty($actividad['archivo_pdf'])): ?>
            <div class="mb-3">
                <label class="form-label">PDF actual</label>
                <div><a href="../../<?php echo $actividad['archivo_pdf']; ?>" target="_blank" class="btn action-btn" style="background-color: #293e6b; color: white;">Ver PDF</a></div>
            </div>
            <div class="mb-3">
                <label class="form-label">Reemplazar PDF (opcional)</label>
                <input type="file" name="archivo_pdf" class="form-control" accept=".pdf">
            </div>
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Archivo PDF (opcional)</label>
                <input type="file" name="archivo_pdf" class="form-control" accept=".pdf">
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between">
                <a href="lista_actividades.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>

        <div class="agenda-preview">
            <h5 class="mb-3">Horarios ocupados en este evento</h5>
            <?php if (count($ocupados) > 0): ?>
                <?php foreach ($ocupados as $occ): ?>
                <div class="bloque-ocupado">
                    <span><?php echo substr($occ['hora_inicio'], 0, 5) . ' - ' . substr($occ['hora_fin'], 0, 5); ?></span> – Actividad existente
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No hay otras actividades en este evento.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const selectTipo = document.getElementById('id_tipo');
        const selectHoraInicio = document.getElementById('hora_inicio');
        const inputHoraFin = document.getElementById('hora_fin');
        const inputHoraFinHidden = document.getElementById('hora_fin_hidden');
        const duraciones = {};

        // Guardar duraciones de cada tipo
        document.querySelectorAll('#id_tipo option').forEach(opt => {
            duraciones[opt.value] = parseInt(opt.dataset.duracion);
        });

        function actualizarHoraFin() {
            const horaInicio = selectHoraInicio.value;
            const duracion = duraciones[selectTipo.value];
            if (horaInicio && duracion) {
                const [h, m] = horaInicio.split(':');
                let fecha = new Date();
                fecha.setHours(parseInt(h), parseInt(m), 0);
                fecha.setMinutes(fecha.getMinutes() + duracion);
                let horaFin = fecha.toTimeString().substring(0,5);
                inputHoraFin.value = horaFin;
                inputHoraFinHidden.value = horaFin + ':00';
            }
        }

        selectTipo.addEventListener('change', function() {
            // Recargar opciones de hora cuando cambia el tipo (por la duración)
            // Para simplificar, recargamos la página con los nuevos parámetros, o mejor, hacemos una petición AJAX.
            // Pero aquí asumimos que al cambiar tipo, el usuario debe recargar? Para evitar complejidad, mostramos alerta.
            alert('Si cambia el tipo de actividad, la disponibilidad de horarios puede cambiar. Guarde y edite nuevamente.');
        });
        selectHoraInicio.addEventListener('change', actualizarHoraFin);
        actualizarHoraFin();
    </script>
</body>
</html>